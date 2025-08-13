<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assurify API Documentation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
            color: #333;
        }
        
        .header {
            border-bottom: 3px solid #ff6600;
            margin-bottom: 30px;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #ff6600;
            margin: 0;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .endpoint {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .method {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            color: white;
            font-weight: bold;
            font-size: 12px;
            margin-right: 10px;
        }
        
        .method.get { background-color: #28a745; }
        .method.post { background-color: #007bff; }
        .method.put { background-color: #ffc107; color: #000; }
        .method.delete { background-color: #dc3545; }
        
        .path {
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 16px;
            font-weight: bold;
        }
        
        .description {
            margin: 10px 0;
            color: #666;
        }
        
        .params, .response {
            background: white;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .params h4, .response h4 {
            margin-top: 0;
            color: #333;
        }
        
        code {
            background: #f1f3f4;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 14px;
        }
        
        pre {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 15px;
            border-radius: 3px;
            overflow-x: auto;
            font-size: 14px;
        }
        
        .auth-required {
            color: #dc3545;
            font-weight: bold;
        }
        
        .nav {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav li {
            margin-bottom: 5px;
        }
        
        .nav a {
            color: #007bff;
            text-decoration: none;
        }
        
        .nav a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Assurify API Documentation</h1>
        <p>RESTful API for the Assurify community platform. Version: v1</p>
    </div>

    <div class="nav">
        <h3>Quick Navigation</h3>
        <ul>
            <li><a href="#authentication">Authentication</a></li>
            <li><a href="#stories">Stories</a></li>
            <li><a href="#responses">Response Format</a></li>
            <li><a href="#errors">Error Handling</a></li>
            <li><a href="#rate-limiting">Rate Limiting</a></li>
        </ul>
    </div>

    <div class="section" id="authentication">
        <h2>Authentication</h2>
        <p>The API uses JWT (JSON Web Tokens) for authentication. You can authenticate using either:</p>
        <ul>
            <li><strong>Access Tokens</strong> - Short-lived tokens (1 hour) for regular API access</li>
            <li><strong>API Keys</strong> - Long-lived tokens for automated access</li>
        </ul>

        <div class="endpoint">
            <div>
                <span class="method post">POST</span>
                <span class="path">/api/v1/auth/login</span>
            </div>
            <div class="description">Authenticate with email and password to get access tokens.</div>
            <div class="params">
                <h4>Request Body</h4>
                <pre>{
  "email": "user@example.com",
  "password": "your-password"
}</pre>
            </div>
            <div class="response">
                <h4>Response</h4>
                <pre>{
  "success": true,
  "message": "Login successful",
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 123,
      "username": "johndoe",
      "email": "user@example.com"
    }
  }
}</pre>
            </div>
        </div>

        <div class="endpoint">
            <div>
                <span class="method post">POST</span>
                <span class="path">/api/v1/auth/refresh</span>
            </div>
            <div class="description">Refresh an expired access token using a refresh token.</div>
            <div class="params">
                <h4>Request Body</h4>
                <pre>{
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}</pre>
            </div>
        </div>

        <div class="endpoint">
            <div>
                <span class="method post">POST</span>
                <span class="path">/api/v1/auth/api-keys</span>
                <span class="auth-required">🔒 Requires Authentication</span>
            </div>
            <div class="description">Create a new API key for programmatic access.</div>
            <div class="params">
                <h4>Request Headers</h4>
                <pre>Authorization: Bearer YOUR_ACCESS_TOKEN</pre>
                <h4>Request Body</h4>
                <pre>{
  "name": "My API Key",
  "scopes": ["stories:read", "stories:write", "votes"]
}</pre>
            </div>
        </div>

        <h3>Using Tokens</h3>
        <p>Include your token in the Authorization header:</p>
        <pre>Authorization: Bearer YOUR_TOKEN_HERE</pre>
    </div>

    <div class="section" id="stories">
        <h2>Stories</h2>

        <div class="endpoint">
            <div>
                <span class="method get">GET</span>
                <span class="path">/api/v1/stories</span>
            </div>
            <div class="description">Get a paginated list of stories.</div>
            <div class="params">
                <h4>Query Parameters</h4>
                <ul>
                    <li><code>page</code> - Page number (default: 1)</li>
                    <li><code>per_page</code> - Items per page (default: 20, max: 100)</li>
                    <li><code>sort</code> - Sort order: newest, hottest, top (default: newest)</li>
                    <li><code>tag</code> - Filter by tag name</li>
                </ul>
            </div>
            <div class="response">
                <h4>Response</h4>
                <pre>{
  "success": true,
  "message": "Stories retrieved successfully",
  "data": [
    {
      "id": 123,
      "title": "Example Story",
      "url": "https://example.com",
      "description": "Story description",
      "score": 15,
      "comment_count": 7,
      "user": {
        "id": 456,
        "username": "author"
      },
      "tags": ["tech", "programming"],
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "total_pages": 8,
    "has_next_page": true,
    "has_prev_page": false
  }
}</pre>
            </div>
        </div>

        <div class="endpoint">
            <div>
                <span class="method get">GET</span>
                <span class="path">/api/v1/stories/{id}</span>
            </div>
            <div class="description">Get a specific story by ID.</div>
        </div>

        <div class="endpoint">
            <div>
                <span class="method post">POST</span>
                <span class="path">/api/v1/stories</span>
                <span class="auth-required">🔒 Requires Authentication</span>
            </div>
            <div class="description">Create a new story.</div>
            <div class="params">
                <h4>Request Body</h4>
                <pre>{
  "title": "My New Story",
  "url": "https://example.com/article",
  "description": "Description of the story",
  "tags": ["tech", "news"]
}</pre>
            </div>
        </div>

        <div class="endpoint">
            <div>
                <span class="method post">POST</span>
                <span class="path">/api/v1/stories/{id}/vote</span>
                <span class="auth-required">🔒 Requires Authentication</span>
            </div>
            <div class="description">Vote on a story.</div>
            <div class="params">
                <h4>Request Body</h4>
                <pre>{
  "vote": "up"  // "up", "down", or "remove"
}</pre>
            </div>
        </div>
    </div>

    <div class="section" id="responses">
        <h2>Response Format</h2>
        <p>All API responses follow a consistent JSON format:</p>
        <pre>{
  "success": true,
  "message": "Human-readable message",
  "data": { /* Response data */ },
  "timestamp": "2024-01-15T10:30:00Z",
  "version": "v1"
}</pre>

        <h3>Paginated Responses</h3>
        <p>List endpoints include pagination information:</p>
        <pre>{
  "success": true,
  "data": [ /* Array of items */ ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "total_pages": 8,
    "has_next_page": true,
    "has_prev_page": false
  }
}</pre>
    </div>

    <div class="section" id="errors">
        <h2>Error Handling</h2>
        <p>Errors return appropriate HTTP status codes and follow this format:</p>
        <pre>{
  "success": false,
  "message": "Error description",
  "errors": [ /* Array of specific errors */ ],
  "code": "ERROR_CODE",
  "timestamp": "2024-01-15T10:30:00Z"
}</pre>

        <h3>Common HTTP Status Codes</h3>
        <ul>
            <li><strong>200 OK</strong> - Request successful</li>
            <li><strong>201 Created</strong> - Resource created successfully</li>
            <li><strong>400 Bad Request</strong> - Invalid request data</li>
            <li><strong>401 Unauthorized</strong> - Authentication required or invalid</li>
            <li><strong>403 Forbidden</strong> - Insufficient permissions</li>
            <li><strong>404 Not Found</strong> - Resource not found</li>
            <li><strong>429 Too Many Requests</strong> - Rate limit exceeded</li>
            <li><strong>500 Internal Server Error</strong> - Server error</li>
        </ul>
    </div>

    <div class="section" id="rate-limiting">
        <h2>Rate Limiting</h2>
        <p>The API implements rate limiting to ensure fair usage:</p>
        <ul>
            <li><strong>Authentication</strong> - 5 login attempts per hour per IP</li>
            <li><strong>API Requests</strong> - 1000 requests per hour per authenticated user</li>
            <li><strong>Story Creation</strong> - 10 stories per day per user</li>
            <li><strong>Voting</strong> - 100 votes per hour per user</li>
        </ul>

        <p>When rate limited, you'll receive a <code>429 Too Many Requests</code> response.</p>
    </div>

    <div class="section">
        <h2>API Scopes</h2>
        <p>API keys can be restricted to specific scopes:</p>
        <ul>
            <li><code>read</code> - Read access to resources</li>
            <li><code>write</code> - Write access to resources</li>
            <li><code>delete</code> - Delete access to resources</li>
            <li><code>stories:read</code> - Read stories</li>
            <li><code>stories:write</code> - Create and edit stories</li>
            <li><code>votes</code> - Vote on stories and comments</li>
            <li><code>all</code> - Full access to all resources</li>
        </ul>
    </div>
</body>
</html>