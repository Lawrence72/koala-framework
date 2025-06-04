<?php

namespace Koala\Router;

use InvalidArgumentException;
use Koala\Request\Request;
use Koala\Response\Response;
use Koala\Container\Container;
use ReflectionMethod;
use RuntimeException;

class Router implements RouterInterface
{
    protected array $routes = [];
    protected array $currentGroup = [];
    protected Container $container;

    // Caching properties
    protected array $staticRoutes = [];
    protected array $dynamicRoutes = [];
    protected bool $cacheBuilt = false;

    /**
     * Initialize a new Router instance
     * Sets up the container for dependency injection
     */
    public function __construct()
    {
        $this->container = Container::getInstance();
    }

    /**
     * Group routes with common path prefix and middleware
     *
     * @param string $path Common path prefix for the group
     * @param callable $callback Function containing route definitions
     * @param array $middleware Array of middleware classes to apply to all routes in group
     * @return void
     */
    public function group(string $path, callable $callback, array $middleware = []): void
    {
        $previousGroup = $this->currentGroup;

        // Flatten any nested arrays in middleware
        $flattenedMiddleware = [];
        foreach ($middleware as $m) {
            if (is_array($m)) {
                $flattenedMiddleware = array_merge($flattenedMiddleware, $m);
            } else {
                $flattenedMiddleware[] = $m;
            }
        }

        $this->currentGroup = array_merge($previousGroup, [
            'path' => ($previousGroup['path'] ?? '') . $path,
            'middleware' => array_merge(
                $previousGroup['middleware'] ?? [],
                $flattenedMiddleware
            )
        ]);

        $callback($this);

        $this->currentGroup = $previousGroup;

        // Invalidate cache when routes change
        $this->cacheBuilt = false;
    }

    /**
     * Add a new route to the router
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path URL path pattern
     * @param string $controllerClass Fully qualified class name of the controller
     * @param string $action Name of the controller method to call
     * @return void
     */
    public function addRoute(string $method, string $path, string $controllerClass, string $action): void
    {
        if (isset($this->currentGroup['path'])) {
            $path = '/' . ltrim($this->currentGroup['path'], '/') . $path;
        }

        $pattern = preg_replace_callback(
            '#@(\w+)(?::([^/]+))?#',
            function ($matches) {
                $paramName = $matches[1];
                $regexPattern = $matches[2] ?? '[^/]+';
                return sprintf('(?P<%s>%s)', $paramName, $regexPattern);
            },
            $path
        );

        $pattern = '^' . ltrim($pattern, '/') . '/?$';

        $routeInfo = [
            'controller' => $controllerClass,
            'action' => $action,
            'original_path' => $path,
            'middleware' => $this->currentGroup['middleware'] ?? []
        ];

        $this->routes[$method][$pattern] = $routeInfo;

        // Invalidate cache when routes change
        $this->cacheBuilt = false;
    }

    /**
     * Build the route cache for faster lookups
     * Separates routes into static and dynamic for optimized matching
     *
     * @return void
     */
    protected function buildRouteCache(): void
    {
        if ($this->cacheBuilt) {
            return;
        }

        $this->staticRoutes = [];
        $this->dynamicRoutes = [];

        foreach ($this->routes as $method => $routes) {
            $this->staticRoutes[$method] = [];
            $this->dynamicRoutes[$method] = [];

            foreach ($routes as $pattern => $routeInfo) {
                $originalPath = $routeInfo['original_path'];
                $cleanPath = ltrim($originalPath, '/');

                // Check if route is static (no parameters)
                if (strpos($originalPath, '@') === false) {
                    // Static route - direct lookup
                    $this->staticRoutes[$method][$cleanPath] = $routeInfo;

                    // Also handle with trailing slash
                    $this->staticRoutes[$method][$cleanPath . '/'] = $routeInfo;
                } else {
                    // Dynamic route - needs regex matching
                    $this->dynamicRoutes[$method][$pattern] = $routeInfo;
                }
            }
        }

        $this->cacheBuilt = true;
    }

    /**
     * Dispatch a request to the appropriate route handler
     *
     * @param string $method HTTP method of the request
     * @param string $path URL path of the request
     * @return mixed Response from the route handler
     * @throws RuntimeException If no matching route is found
     */
    public function dispatch(string $method, string $path): mixed
    {
        // Build cache if needed
        $this->buildRouteCache();

        $cleanPath = ltrim($path, '/');

        // Step 1: Try static route lookup (fastest)
        if (isset($this->staticRoutes[$method][$cleanPath])) {
            return $this->executeRoute($this->staticRoutes[$method][$cleanPath], []);
        }

        // Step 2: Try dynamic route matching (slower, but only for dynamic routes)
        foreach ($this->dynamicRoutes[$method] ?? [] as $pattern => $route) {
            if (preg_match('#' . $pattern . '#', $cleanPath, $matches)) {
                $params = array_filter($matches, fn($key) => is_string($key), ARRAY_FILTER_USE_KEY);
                return $this->executeRoute($route, $params);
            }
        }

        throw new \RuntimeException('Route not found', 404);
    }

    /**
     * Execute a matched route with its middleware chain
     *
     * @param array $route Route information including controller and middleware
     * @param array $params Route parameters extracted from the URL
     * @return mixed Response from the route handler
     * @throws RuntimeException If controller or middleware setup fails
     */
    protected function executeRoute(array $route, array $params): mixed
    {
        $controllerClass = $route['controller'];
        $action = $route['action'];
        $request = new Request();

        $controllerAction = function () use ($controllerClass, $action, $params) {
            if (!class_exists($controllerClass)) {
                throw new \RuntimeException("Controller class '$controllerClass' not found", 500);
            }

            $controller = $this->container->get($controllerClass);

            if (!$controller) {
                $reflection = new \ReflectionClass($controllerClass);
                $constructor = $reflection->getConstructor();

                if ($constructor) {
                    $constructorParams = [];

                    foreach ($constructor->getParameters() as $param) {
                        $paramClass = $param->getType()->getName();
                        $dependency = $this->container->get($paramClass);

                        if (!$dependency && !$param->isOptional()) {
                            throw new \RuntimeException("Could not find dependency {$paramClass} for controller {$controllerClass}");
                        }

                        $constructorParams[] = $dependency;
                    }

                    $controller = new $controllerClass(...$constructorParams);
                } else {
                    $controller = new $controllerClass();
                }
            }

            if (!method_exists($controller, $action)) {
                throw new \RuntimeException("Action '$action' not found in controller '$controllerClass'", 500);
            }

            $arguments = $this->findMethodArguments($controllerClass, $action, $params);

            return $controller->$action(...$arguments);
        };

        // Handle middleware
        if (!empty($route['middleware'])) {
            $stack = $controllerAction;

            foreach ($route['middleware'] as $middlewareClass) {
                if (!class_exists($middlewareClass)) {
                    throw new \RuntimeException("Middleware class '$middlewareClass' not found");
                }

                $middlewareInstance = $this->container->get($middlewareClass);

                if (!$middlewareInstance) {
                    $reflection = new \ReflectionClass($middlewareClass);
                    $constructor = $reflection->getConstructor();

                    if ($constructor) {
                        $middlewareParams = [];
                        foreach ($constructor->getParameters() as $param) {
                            $paramClass = $param->getType()->getName();
                            $dependency = $this->container->get($paramClass);

                            if (!$dependency && !$param->isOptional()) {
                                throw new \RuntimeException("Could not find dependency {$paramClass} for middleware {$middlewareClass}");
                            }
                            $middlewareParams[] = $dependency;
                        }
                        $middlewareInstance = new $middlewareClass(...$middlewareParams);
                    } else {
                        $middlewareInstance = new $middlewareClass();
                    }
                }

                if (!method_exists($middlewareInstance, 'handle')) {
                    throw new \RuntimeException("Middleware method 'handle' not found in class '$middlewareClass'");
                }

                $currentStack = $stack;
                $stack = fn() => $middlewareInstance->handle($request, $currentStack);
            }

            return $stack();
        }

        return $controllerAction();
    }

    /**
     * Clear the route cache
     * Forces rebuilding of the cache on next dispatch
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cacheBuilt = false;
        $this->staticRoutes = [];
        $this->dynamicRoutes = [];
    }

    /**
     * Get statistics about the route cache
     *
     * @return array Cache statistics including counts of static and dynamic routes
     */
    public function getCacheStats(): array
    {
        $stats = [
            'total_routes' => 0,
            'static_routes' => 0,
            'dynamic_routes' => 0,
            'methods' => []
        ];

        foreach ($this->routes as $method => $routes) {
            $stats['methods'][$method] = [
                'total' => count($routes),
                'static' => count($this->staticRoutes[$method] ?? []),
                'dynamic' => count($this->dynamicRoutes[$method] ?? [])
            ];
            $stats['total_routes'] += count($routes);
            $stats['static_routes'] += count($this->staticRoutes[$method] ?? []);
            $stats['dynamic_routes'] += count($this->dynamicRoutes[$method] ?? []);
        }

        return $stats;
    }

    /**
     * Find the arguments for a controller method
     *
     * @param string $controllerClass The controller class name
     * @param string $action The action method name
     * @param array $routeParams Route parameters from the URL
     * @return array Arguments to pass to the controller method
     */
    protected function findMethodArguments(string $controllerClass, string $action, array $routeParams): array
    {
        $reflection = new ReflectionMethod($controllerClass, $action);
        $arguments = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if (isset($routeParams[$name])) {
                $arguments[] = $routeParams[$name];
            } elseif ($type && !$type->isBuiltin()) {
                $typeName = $type->getName();
                $dependency = $this->container->get($typeName);
                if ($dependency) {
                    $arguments[] = $dependency;
                } elseif ($param->isOptional()) {
                    $arguments[] = $param->getDefaultValue();
                } else {
                    throw new \RuntimeException("Could not resolve dependency {$typeName} for {$controllerClass}::{$action}");
                }
            } elseif ($param->isOptional()) {
                $arguments[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Required parameter {$name} not provided for {$controllerClass}::{$action}");
            }
        }

        return $arguments;
    }

    /**
     * List all registered routes
     *
     * @return array Array of all registered routes with their methods and paths
     */
    public function listRoutes(): array
    {
        $routes = [];
        foreach ($this->routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $pattern => $route) {
                $routes[] = [
                    'method' => $method,
                    'path' => $route['original_path'],
                    'controller' => $route['controller'],
                    'action' => $route['action'],
                    'middleware' => $route['middleware']
                ];
            }
        }
        return $routes;
    }
}
