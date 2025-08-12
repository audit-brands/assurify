<?php

declare(strict_types=1);

namespace App\Services;

use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocketService implements MessageComponentInterface
{
    protected $clients;
    protected $rooms;
    protected array $userConnections;
    protected LoggerService $logger;
    protected AuthService $authService;
    protected JwtService $jwtService;
    
    public function __construct(LoggerService $logger, AuthService $authService, JwtService $jwtService)
    {
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        $this->userConnections = [];
        $this->logger = $logger;
        $this->authService = $authService;
        $this->jwtService = $jwtService;
        
        echo "WebSocket Server initialized\n";
    }
    
    public function onOpen(ConnectionInterface $conn): void
    {
        // Store the new connection
        $this->clients->attach($conn);
        
        echo "New connection! ({$conn->resourceId})\n";
        
        // Send welcome message
        $conn->send(json_encode([
            'type' => 'welcome',
            'message' => 'Connected to Lobsters WebSocket server',
            'connectionId' => $conn->resourceId,
            'timestamp' => time()
        ]));
        
        $this->logger->info('WebSocket connection opened', [
            'connection_id' => $conn->resourceId,
            'total_connections' => count($this->clients)
        ]);
    }
    
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['type'])) {
                $this->sendError($from, 'Invalid message format');
                return;
            }
            
            $this->logger->debug('WebSocket message received', [
                'connection_id' => $from->resourceId,
                'type' => $data['type'],
                'data' => $data
            ]);
            
            switch ($data['type']) {
                case 'auth':
                    $this->handleAuthentication($from, $data);
                    break;
                    
                case 'join_room':
                    $this->handleJoinRoom($from, $data);
                    break;
                    
                case 'leave_room':
                    $this->handleLeaveRoom($from, $data);
                    break;
                    
                case 'story_view':
                    $this->handleStoryView($from, $data);
                    break;
                    
                case 'comment_typing':
                    $this->handleCommentTyping($from, $data);
                    break;
                    
                case 'ping':
                    $this->handlePing($from, $data);
                    break;
                    
                default:
                    $this->sendError($from, 'Unknown message type: ' . $data['type']);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('WebSocket message handling error', [
                'connection_id' => $from->resourceId,
                'message' => $msg,
                'error' => $e->getMessage()
            ]);
            
            $this->sendError($from, 'Message processing failed');
        }
    }
    
    public function onClose(ConnectionInterface $conn): void
    {
        // Remove from all rooms
        foreach ($this->rooms as $roomId => $room) {
            if (isset($room['connections'][$conn->resourceId])) {
                unset($this->rooms[$roomId]['connections'][$conn->resourceId]);
                
                // Notify other users in room about user leaving
                $this->broadcastToRoom($roomId, [
                    'type' => 'user_left',
                    'user' => $room['connections'][$conn->resourceId] ?? null,
                    'timestamp' => time()
                ], $conn->resourceId);
            }
        }
        
        // Remove user connection mapping
        foreach ($this->userConnections as $userId => $connectionId) {
            if ($connectionId === $conn->resourceId) {
                unset($this->userConnections[$userId]);
                break;
            }
        }
        
        // Remove from clients
        $this->clients->detach($conn);
        
        echo "Connection {$conn->resourceId} has disconnected\n";
        
        $this->logger->info('WebSocket connection closed', [
            'connection_id' => $conn->resourceId,
            'total_connections' => count($this->clients)
        ]);
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        
        $this->logger->error('WebSocket error', [
            'connection_id' => $conn->resourceId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $conn->close();
    }
    
    private function handleAuthentication(ConnectionInterface $conn, array $data): void
    {
        if (!isset($data['token'])) {
            $this->sendError($conn, 'Authentication token required');
            return;
        }
        
        try {
            // Verify JWT token
            $user = $this->jwtService->verifyToken($data['token']);
            
            // Store user connection
            $this->userConnections[$user['user_id']] = $conn->resourceId;
            
            // Store user data in connection
            $conn->user = $user;
            
            $conn->send(json_encode([
                'type' => 'auth_success',
                'user' => [
                    'id' => $user['user_id'],
                    'username' => $user['username']
                ],
                'timestamp' => time()
            ]));
            
            $this->logger->info('WebSocket user authenticated', [
                'connection_id' => $conn->resourceId,
                'user_id' => $user['user_id'],
                'username' => $user['username']
            ]);
            
        } catch (\Exception $e) {
            $this->sendError($conn, 'Authentication failed: ' . $e->getMessage());
        }
    }
    
    private function handleJoinRoom(ConnectionInterface $conn, array $data): void
    {
        if (!isset($conn->user)) {
            $this->sendError($conn, 'Authentication required');
            return;
        }
        
        if (!isset($data['room'])) {
            $this->sendError($conn, 'Room ID required');
            return;
        }
        
        $roomId = $data['room'];
        
        // Initialize room if it doesn't exist
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [
                'id' => $roomId,
                'connections' => [],
                'created_at' => time()
            ];
        }
        
        // Add user to room
        $this->rooms[$roomId]['connections'][$conn->resourceId] = $conn->user;
        
        // Notify user they joined the room
        $conn->send(json_encode([
            'type' => 'room_joined',
            'room' => $roomId,
            'users' => array_values($this->rooms[$roomId]['connections']),
            'timestamp' => time()
        ]));
        
        // Notify other users in room
        $this->broadcastToRoom($roomId, [
            'type' => 'user_joined',
            'user' => $conn->user,
            'timestamp' => time()
        ], $conn->resourceId);
        
        $this->logger->info('User joined WebSocket room', [
            'connection_id' => $conn->resourceId,
            'user_id' => $conn->user['user_id'],
            'room' => $roomId,
            'users_in_room' => count($this->rooms[$roomId]['connections'])
        ]);
    }
    
    private function handleLeaveRoom(ConnectionInterface $conn, array $data): void
    {
        if (!isset($data['room'])) {
            $this->sendError($conn, 'Room ID required');
            return;
        }
        
        $roomId = $data['room'];
        
        if (isset($this->rooms[$roomId]['connections'][$conn->resourceId])) {
            $user = $this->rooms[$roomId]['connections'][$conn->resourceId];
            unset($this->rooms[$roomId]['connections'][$conn->resourceId]);
            
            // Notify user they left the room
            $conn->send(json_encode([
                'type' => 'room_left',
                'room' => $roomId,
                'timestamp' => time()
            ]));
            
            // Notify other users in room
            $this->broadcastToRoom($roomId, [
                'type' => 'user_left',
                'user' => $user,
                'timestamp' => time()
            ], $conn->resourceId);
            
            // Clean up empty rooms
            if (empty($this->rooms[$roomId]['connections'])) {
                unset($this->rooms[$roomId]);
            }
        }
    }
    
    private function handleStoryView(ConnectionInterface $conn, array $data): void
    {
        if (!isset($conn->user) || !isset($data['story_id'])) {
            return;
        }
        
        $storyId = $data['story_id'];
        $roomId = "story_{$storyId}";
        
        // Auto-join story room
        $this->handleJoinRoom($conn, ['room' => $roomId]);
        
        // Broadcast story view event
        $this->broadcastToRoom($roomId, [
            'type' => 'story_view',
            'story_id' => $storyId,
            'user' => $conn->user,
            'timestamp' => time()
        ], $conn->resourceId);
    }
    
    private function handleCommentTyping(ConnectionInterface $conn, array $data): void
    {
        if (!isset($conn->user) || !isset($data['story_id'])) {
            return;
        }
        
        $storyId = $data['story_id'];
        $roomId = "story_{$storyId}";
        $isTyping = $data['typing'] ?? false;
        
        // Broadcast typing indicator
        $this->broadcastToRoom($roomId, [
            'type' => 'comment_typing',
            'story_id' => $storyId,
            'user' => $conn->user,
            'typing' => $isTyping,
            'timestamp' => time()
        ], $conn->resourceId);
    }
    
    private function handlePing(ConnectionInterface $conn, array $data): void
    {
        $conn->send(json_encode([
            'type' => 'pong',
            'timestamp' => time(),
            'server_time' => date('c')
        ]));
    }
    
    private function broadcastToRoom(string $roomId, array $message, ?int $excludeConnectionId = null): void
    {
        if (!isset($this->rooms[$roomId])) {
            return;
        }
        
        $messageJson = json_encode($message);
        
        foreach ($this->clients as $client) {
            if ($excludeConnectionId && $client->resourceId === $excludeConnectionId) {
                continue;
            }
            
            if (isset($this->rooms[$roomId]['connections'][$client->resourceId])) {
                $client->send($messageJson);
            }
        }
    }
    
    private function sendError(ConnectionInterface $conn, string $message): void
    {
        $conn->send(json_encode([
            'type' => 'error',
            'message' => $message,
            'timestamp' => time()
        ]));
    }
    
    // Public methods for external use
    
    public function broadcastNewStory(array $story): void
    {
        $message = [
            'type' => 'new_story',
            'story' => $story,
            'timestamp' => time()
        ];
        
        $this->broadcastToAll($message);
    }
    
    public function broadcastNewComment(array $comment, array $story): void
    {
        $storyRoomId = "story_{$story['id']}";
        
        $message = [
            'type' => 'new_comment',
            'comment' => $comment,
            'story' => $story,
            'timestamp' => time()
        ];
        
        $this->broadcastToRoom($storyRoomId, $message);
    }
    
    public function notifyUser(int $userId, array $message): bool
    {
        if (!isset($this->userConnections[$userId])) {
            return false;
        }
        
        $connectionId = $this->userConnections[$userId];
        
        foreach ($this->clients as $client) {
            if ($client->resourceId === $connectionId) {
                $client->send(json_encode($message));
                return true;
            }
        }
        
        return false;
    }
    
    private function broadcastToAll(array $message): void
    {
        $messageJson = json_encode($message);
        
        foreach ($this->clients as $client) {
            $client->send($messageJson);
        }
    }
    
    public function getConnectionStats(): array
    {
        return [
            'total_connections' => count($this->clients),
            'authenticated_users' => count($this->userConnections),
            'active_rooms' => count($this->rooms),
            'rooms' => array_map(function($room) {
                return [
                    'id' => $room['id'],
                    'users' => count($room['connections']),
                    'created_at' => $room['created_at']
                ];
            }, $this->rooms)
        ];
    }
    
    public function startServer(int $port = 8080): void
    {
        $server = IoServer::factory(
            new HttpServer(
                new WsServer($this)
            ),
            $port
        );
        
        echo "WebSocket server started on port {$port}\n";
        
        $this->logger->info('WebSocket server started', ['port' => $port]);
        
        $server->run();
    }
}