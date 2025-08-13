<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\AuthServiceInterface;
use App\Services\InvitationService;
use App\Services\EmailService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class AuthController extends BaseController
{
    public function __construct(
        Engine $templates,
        private AuthServiceInterface $authService,
        private InvitationService $invitationService,
        private EmailService $emailService
    ) {
        parent::__construct($templates);
    }

    public function loginForm(Request $request, Response $response): Response
    {
        // Redirect if already logged in
        if (isset($_SESSION['user_id'])) {
            return $this->redirect($response, '/');
        }

        return $this->render($response, 'auth/login', [
            'title' => 'Login | Assurify',
            'error' => $_SESSION['login_error'] ?? null,
        ]);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $rememberMe = isset($data['remember_me']);

        // Clear previous errors
        unset($_SESSION['login_error']);

        // Validate input
        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'Username and password are required';
            return $this->redirect($response, '/auth/login');
        }

        try {
            // Authenticate user
            $user = $this->authService->authenticateUser($username, $password);

            if (!$user) {
                $_SESSION['login_error'] = 'Invalid username or password';
                return $this->redirect($response, '/auth/login');
            }

            // Set session
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;

            // Set remember me cookie if requested
            if ($rememberMe) {
                setcookie(
                    'remember_token',
                    $user->session_token,
                    time() + (86400 * 30), // 30 days
                    '/',
                    '',
                    true, // secure
                    true  // httponly
                );
            }

            // Redirect to original destination or home
            $redirectTo = $_SESSION['auth_redirect'] ?? '/';
            unset($_SESSION['auth_redirect']);

            return $this->redirect($response, $redirectTo);
        } catch (\Exception $e) {
            $_SESSION['login_error'] = 'Login failed. Please try again.';
            return $this->redirect($response, '/auth/login');
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        if (isset($_SESSION['user_id'])) {
            // Get current user and clear session token
            $user = $this->authService->getUserBySessionToken($_COOKIE['remember_token'] ?? '');
            if ($user) {
                $this->authService->logout($user);
            }
        }

        // Clear session
        session_destroy();

        // Clear remember me cookie
        setcookie('remember_token', '', time() - 3600, '/');

        return $this->redirect($response, '/');
    }

    public function signupForm(Request $request, Response $response): Response
    {
        // Redirect if already logged in
        if (isset($_SESSION['user_id'])) {
            return $this->redirect($response, '/');
        }

        return $this->render($response, 'auth/signup', [
            'title' => 'Sign Up | Lobsters',
            'error' => $_SESSION['signup_error'] ?? null,
        ]);
    }

    public function invitedSignupForm(Request $request, Response $response): Response
    {
        $code = $request->getQueryParams()['code'] ?? '';

        if (empty($code)) {
            return $this->redirect($response, '/auth/signup');
        }

        // Validate invitation code
        $invitation = $this->invitationService->getInvitationByCode($code);
        if (!$invitation || $invitation->used_at) {
            return $this->render($response, 'auth/invalid-invitation', [
                'title' => 'Invalid Invitation | Lobsters'
            ]);
        }

        return $this->render($response, 'auth/invited-signup', [
            'title' => 'Sign Up | Lobsters',
            'invitation' => $invitation,
            'inviter' => $invitation->user,
            'error' => $_SESSION['signup_error'] ?? null,
        ]);
    }

    public function signup(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';
        $about = trim($data['about'] ?? '');
        $invitationCode = trim($data['invitation_code'] ?? '');

        // Clear previous errors
        unset($_SESSION['signup_error']);

        // Validate input
        try {
            $this->validateSignupInput($username, $email, $password, $passwordConfirm, $invitationCode);

            // Register user
            $user = $this->authService->registerUser([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'about' => $about,
            ], $invitationCode);

            // Send welcome email
            $this->emailService->sendWelcomeEmail($user);

            // Auto-login the new user
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;

            return $this->redirect($response, '/');
        } catch (\Exception $e) {
            $_SESSION['signup_error'] = $e->getMessage();
            $redirectUrl = !empty($invitationCode) ? "/signup/invited?code=$invitationCode" : '/auth/signup';
            return $this->redirect($response, $redirectUrl);
        }
    }

    public function forgotPasswordForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/forgot-password', [
            'title' => 'Forgot Password | Lobsters',
            'message' => $_SESSION['password_message'] ?? null,
            'error' => $_SESSION['password_error'] ?? null,
        ]);
    }

    public function sendPasswordReset(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = trim($data['email'] ?? '');

        unset($_SESSION['password_message'], $_SESSION['password_error']);

        if (empty($email)) {
            $_SESSION['password_error'] = 'Email is required';
            return $this->redirect($response, '/auth/forgot-password');
        }

        $user = \App\Models\User::where('email', $email)->first();

        if ($user) {
            $resetToken = $this->authService->generatePasswordResetToken();
            $user->password_reset_token = $resetToken;
            $user->save();

            $this->emailService->sendPasswordReset($user, $resetToken);
        }

        // Always show success message for security
        $_SESSION['password_message'] = 'If that email address is registered, we\'ve sent password reset instructions.';
        return $this->redirect($response, '/auth/forgot-password');
    }

    private function validateSignupInput(string $username, string $email, string $password, string $passwordConfirm, string $invitationCode): void
    {
        if (empty($username) || empty($email) || empty($password) || empty($invitationCode)) {
            throw new \Exception('All fields are required');
        }

        if (!$this->authService->isValidUsername($username)) {
            throw new \Exception('Username must be 3-50 characters and contain only letters, numbers, underscores, and dashes');
        }

        if (!$this->authService->isValidEmail($email)) {
            throw new \Exception('Invalid email address');
        }

        if (strlen($password) < 8) {
            throw new \Exception('Password must be at least 8 characters long');
        }

        if ($password !== $passwordConfirm) {
            throw new \Exception('Passwords do not match');
        }
    }
}
