<?php

declare(strict_types=1);

namespace Dikki\Micro;

/**
 * Micro - A minimalist PHP micro-framework for building APIs and microservices
 */
class Micro
{
    /** @var array Route definitions */
    private array $routes = [];

    /** @var string Request method */
    private string $requestMethod;

    /** @var string Current URI */
    private string $requestUri;

    /** @var array Response headers */
    private array $headers = ['Content-Type' => 'application/json'];

    /**
     * Constructor - sets up the environment
     */
    public function __construct()
    {
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->requestUri = $this->getCleanUri();
    }

    /**
     * Get clean request URI
     */
    private function getCleanUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $position = strpos($uri, '?');
        if ($position !== false) {
            $uri = substr($uri, 0, $position);
        }
        return rtrim($uri, '/') ?: '/';
    }

    /**
     * Add route for GET method
     */
    public function get(string $route, callable $handler): self
    {
        return $this->addRoute('GET', $route, $handler);
    }

    /**
     * Add route for POST method
     */
    public function post(string $route, callable $handler): self
    {
        return $this->addRoute('POST', $route, $handler);
    }

    /**
     * Add route for PUT method
     */
    public function put(string $route, callable $handler): self
    {
        return $this->addRoute('PUT', $route, $handler);
    }

    /**
     * Add route for DELETE method
     */
    public function delete(string $route, callable $handler): self
    {
        return $this->addRoute('DELETE', $route, $handler);
    }

    /**
     * Add route for PATCH method
     */
    public function patch(string $route, callable $handler): self
    {
        return $this->addRoute('PATCH', $route, $handler);
    }

    /**
     * Add route for OPTIONS method
     */
    public function options(string $route, callable $handler): self
    {
        return $this->addRoute('OPTIONS', $route, $handler);
    }

    /**
     * Add a route to the routes collection
     */
    private function addRoute(string $method, string $route, callable $handler): self
    {
        // Standardize route format
        $route = rtrim($route, '/') ?: '/';

        // Extract route parameters pattern
        $pattern = $this->getRoutePattern($route);

        $this->routes[$method][$route] = [
            'pattern' => $pattern,
            'handler' => $handler
        ];

        return $this;
    }

    /**
     * Convert route definition to regex pattern
     */
    private function getRoutePattern(string $route): string
    {
        // Replace route parameters {param} with regex pattern
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route);
        return '#^' . $pattern . '$#';
    }

    /**
     * Set a response header
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Get JSON input data
     */
    public function getJsonInput(): ?array
    {
        $input = file_get_contents('php://input');
        if (!$input) {
            return null;
        }

        $data = json_decode($input, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        // Check if method exists
        if (!isset($this->routes[$this->requestMethod])) {
            $this->sendError(405, 'Method Not Allowed');
            return;
        }

        // Match the route
        $routeInfo = $this->matchRoute();

        if ($routeInfo === null) {
            $this->sendError(404, 'Not Found');
            return;
        }

        // Execute route handler with parameters
        try {
            $this->sendHeaders();
            call_user_func_array($routeInfo['handler'], $routeInfo['params']);
        } catch (\Throwable $e) {
            $this->sendError(500, $e->getMessage());
        }
    }

    /**
     * Match the current request URI to a route
     */
    private function matchRoute(): ?array
    {
        foreach ($this->routes[$this->requestMethod] as $route => $info) {
            if (preg_match($info['pattern'], $this->requestUri, $matches)) {
                // Filter out numeric indexes from matches
                $params = array_filter($matches, function ($key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);

                return [
                    'handler' => $info['handler'],
                    'params' => $params
                ];
            }
        }

        return null;
    }

    /**
     * Send response headers
     */
    private function sendHeaders(): void
    {
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
    }

    /**
     * Send an error response
     */
    private function sendError(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        $this->sendHeaders();
        echo json_encode([
            'error' => [
                'code' => $statusCode,
                'message' => $message
            ]
        ]);
    }
}