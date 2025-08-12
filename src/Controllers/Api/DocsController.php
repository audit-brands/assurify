<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\Plates\Engine;

class DocsController
{
    private Engine $templates;
    
    public function __construct(Engine $templates)
    {
        $this->templates = $templates;
    }
    
    public function index(Request $request, Response $response): Response
    {
        $html = $this->templates->render('api/docs');
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }
    
    public function openapi(Request $request, Response $response): Response
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Lobsters API',
                'description' => 'RESTful API for the Lobsters community platform',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Lobsters API Support',
                    'url' => 'https://github.com/lobsters/lobsters'
                ],
                'license' => [
                    'name' => 'MIT',
                    'url' => 'https://opensource.org/licenses/MIT'
                ]
            ],
            'servers' => [
                [
                    'url' => '/api/v1',
                    'description' => 'API v1'
                ]
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ]
                ],
                'schemas' => [
                    'Story' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'example' => 123],
                            'title' => ['type' => 'string', 'example' => 'Example Story'],
                            'url' => ['type' => 'string', 'format' => 'uri', 'example' => 'https://example.com'],
                            'description' => ['type' => 'string', 'example' => 'Story description'],
                            'score' => ['type' => 'integer', 'example' => 15],
                            'comment_count' => ['type' => 'integer', 'example' => 7],
                            'user' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'integer', 'example' => 456],
                                    'username' => ['type' => 'string', 'example' => 'johndoe']
                                ]
                            ],
                            'tags' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'example' => ['tech', 'programming']
                            ],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                            'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true]
                        ]
                    ],
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'example' => 123],
                            'username' => ['type' => 'string', 'example' => 'johndoe'],
                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'john@example.com'],
                            'created_at' => ['type' => 'string', 'format' => 'date-time']
                        ]
                    ],
                    'AuthResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'access_token' => ['type' => 'string'],
                            'refresh_token' => ['type' => 'string'],
                            'token_type' => ['type' => 'string', 'example' => 'Bearer'],
                            'expires_in' => ['type' => 'integer', 'example' => 3600],
                            'user' => ['$ref' => '#/components/schemas/User']
                        ]
                    ],
                    'ApiResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => ['type' => 'boolean', 'example' => true],
                            'message' => ['type' => 'string', 'example' => 'Success'],
                            'data' => ['type' => 'object'],
                            'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                            'version' => ['type' => 'string', 'example' => 'v1']
                        ]
                    ],
                    'ErrorResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => ['type' => 'boolean', 'example' => false],
                            'message' => ['type' => 'string', 'example' => 'An error occurred'],
                            'errors' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'code' => ['type' => 'string', 'example' => 'ERROR_CODE'],
                            'timestamp' => ['type' => 'string', 'format' => 'date-time']
                        ]
                    ],
                    'PaginatedResponse' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/ApiResponse'],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'pagination' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'current_page' => ['type' => 'integer', 'example' => 1],
                                            'per_page' => ['type' => 'integer', 'example' => 20],
                                            'total' => ['type' => 'integer', 'example' => 150],
                                            'total_pages' => ['type' => 'integer', 'example' => 8],
                                            'has_next_page' => ['type' => 'boolean', 'example' => true],
                                            'has_prev_page' => ['type' => 'boolean', 'example' => false]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'paths' => [
                '/auth/login' => [
                    'post' => [
                        'summary' => 'Login with email and password',
                        'tags' => ['Authentication'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['email', 'password'],
                                        'properties' => [
                                            'email' => ['type' => 'string', 'format' => 'email'],
                                            'password' => ['type' => 'string', 'minLength' => 8]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Login successful',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'allOf' => [
                                                ['$ref' => '#/components/schemas/ApiResponse'],
                                                [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'data' => ['$ref' => '#/components/schemas/AuthResponse']
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            '401' => [
                                'description' => 'Invalid credentials',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                                    ]
                                ]
                            ],
                            '429' => [
                                'description' => 'Rate limit exceeded',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/stories' => [
                    'get' => [
                        'summary' => 'Get paginated list of stories',
                        'tags' => ['Stories'],
                        'parameters' => [
                            [
                                'name' => 'page',
                                'in' => 'query',
                                'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]
                            ],
                            [
                                'name' => 'per_page',
                                'in' => 'query',
                                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]
                            ],
                            [
                                'name' => 'sort',
                                'in' => 'query',
                                'schema' => ['type' => 'string', 'enum' => ['newest', 'hottest', 'top'], 'default' => 'newest']
                            ],
                            [
                                'name' => 'tag',
                                'in' => 'query',
                                'schema' => ['type' => 'string']
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Stories retrieved successfully',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'allOf' => [
                                                ['$ref' => '#/components/schemas/PaginatedResponse'],
                                                [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'data' => [
                                                            'type' => 'array',
                                                            'items' => ['$ref' => '#/components/schemas/Story']
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'post' => [
                        'summary' => 'Create a new story',
                        'tags' => ['Stories'],
                        'security' => [['bearerAuth' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['title'],
                                        'properties' => [
                                            'title' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 200],
                                            'url' => ['type' => 'string', 'format' => 'uri'],
                                            'description' => ['type' => 'string'],
                                            'tags' => ['type' => 'array', 'items' => ['type' => 'string']]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Story created successfully',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'allOf' => [
                                                ['$ref' => '#/components/schemas/ApiResponse'],
                                                [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'data' => [
                                                            'type' => 'object',
                                                            'properties' => [
                                                                'story' => ['$ref' => '#/components/schemas/Story']
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            '401' => [
                                'description' => 'Authentication required',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                                    ]
                                ]
                            ],
                            '403' => [
                                'description' => 'Insufficient permissions',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/stories/{id}' => [
                    'get' => [
                        'summary' => 'Get a specific story',
                        'tags' => ['Stories'],
                        'parameters' => [
                            [
                                'name' => 'id',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'integer', 'minimum' => 1]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Story retrieved successfully',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'allOf' => [
                                                ['$ref' => '#/components/schemas/ApiResponse'],
                                                [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'data' => [
                                                            'type' => 'object',
                                                            'properties' => [
                                                                'story' => ['$ref' => '#/components/schemas/Story']
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            '404' => [
                                'description' => 'Story not found',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $response->getBody()->write(json_encode($spec, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }
}