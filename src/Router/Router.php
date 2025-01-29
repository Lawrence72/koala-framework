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
    protected array $current_group = [];
    protected Container $container;

    public function __construct()
    {
        $this->container = Container::getInstance();
    }

    /**
     * 
     * @param string $path 
     * @param callable $callback 
     * @param array $middleware 
     * @return void 
     */
    public function group(string $path, callable $callback, array $middleware = []): void
    {
        $previous_group = $this->current_group;

        // Flatten any nested arrays in middleware
        $flattened_middleware = [];
        foreach ($middleware as $m) {
            if (is_array($m)) {
                $flattened_middleware = array_merge($flattened_middleware, $m);
            } else {
                $flattened_middleware[] = $m;
            }
        }

        $this->current_group = array_merge($previous_group, [
            'path' => ($previous_group['path'] ?? '') . $path,
            'middleware' => array_merge(
                $previous_group['middleware'] ?? [], $flattened_middleware
            )
        ]);

        $callback($this);

        $this->current_group = $previous_group;
    }

    /**
     * 
     * @param string $method 
     * @param string $path 
     * @param string $controller_class 
     * @param string $action 
     * @return void 
     */
    public function addRoute(string $method, string $path, string $controller_class, string $action): void
    {
        if (isset($this->current_group['path'])) {
            $path = '/' . ltrim($this->current_group['path'], '/') . $path;
        }

        $pattern = preg_replace_callback(
            '#@(\w+)(?::([^/]+))?#',
            function ($matches) {
                $param_name = $matches[1];
                $regex_pattern = $matches[2] ?? '[^/]+';
                return sprintf('(?P<%s>%s)', $param_name, $regex_pattern);
            },
            $path
        );

        $pattern = '^' . ltrim($pattern, '/') . '/?$';

        $route_info = [
            'controller' => $controller_class,
            'action' => $action,
            'original_path' => $path,
            'middleware' => $this->current_group['middleware'] ?? []
        ];

        $this->routes[$method][$pattern] = $route_info;
    }

    /**
     * 
     * @param string $method 
     * @param string $path 
     * @return mixed 
     * @throws RuntimeException 
     */
    public function dispatch(string $method, string $path): mixed
    {
        $path = ltrim($path, '/');

        foreach ($this->routes[$method] ?? [] as $pattern => $route) {
            if (preg_match('#' . $pattern . '#', $path, $matches)) {
                $controller_class = $route['controller'];
                $action = $route['action'];

                $request = new Request();

                $controller_action = function () use ($controller_class, $action, $matches) {
                    if (!class_exists($controller_class)) {
                        throw new \RuntimeException("Controller class '$controller_class' not found", 500);
                    }

                    $controller = $this->container->get($controller_class);

                    if (!$controller) {
                        $reflection = new \ReflectionClass($controller_class);
                        $constructor = $reflection->getConstructor();

                        if ($constructor) {
                            $params = [];

                            foreach ($constructor->getParameters() as $param) {
                                $param_class = $param->getType()->getName();
                                $dependency = $this->container->get($param_class);

                                if (!$dependency && !$param->isOptional()) {
                                    throw new \RuntimeException("Could not find dependency {$param_class} for controller {$controller_class}");
                                }

                                $params[] = $dependency;
                            }

                            $controller = new $controller_class(...$params);
                        } else {
                            $controller = new $controller_class();
                        }
                    }

                    if (!method_exists($controller, $action)) {
                        throw new \RuntimeException("Action '$action' not found in controller '$controller_class'", 500);
                    }

                    $params = array_filter($matches, fn($key) => is_string($key), ARRAY_FILTER_USE_KEY);

                    $arguments = $this->findMethodArguments($controller_class, $action, $params);

                    return $controller->$action(...$arguments);
                };

                if (!empty($route['middleware'])) {
                    $stack = $controller_action;

                    foreach ($route['middleware'] as $middleware_class) {
                        if (!class_exists($middleware_class)) {
                            throw new \RuntimeException("Middleware class '$middleware_class' not found");
                        }

                        $middleware_instance = $this->container->get($middleware_class);

                        if (!$middleware_instance) {
                            $reflection = new \ReflectionClass($middleware_class);
                            $constructor = $reflection->getConstructor();

                            if ($constructor) {
                                $params = [];
                                foreach ($constructor->getParameters() as $param) {
                                    $param_class = $param->getType()->getName();
                                    $dependency = $this->container->get($param_class);

                                    if (!$dependency && !$param->isOptional()) {
                                        throw new \RuntimeException("Could not find dependency {$param_class} for middleware {$middleware_class}");
                                    }
                                    $params[] = $dependency;
                                }
                                $middleware_instance = new $middleware_class(...$params);
                            } else {
                                $middleware_instance = new $middleware_class();
                            }
                        }

                        if (!method_exists($middleware_instance, 'handle')) {
                            throw new \RuntimeException("Middleware method 'handle' not found in class '$middleware_class'");
                        }

                        $current_stack = $stack;
                        $stack = fn() => $middleware_instance->handle($request, $current_stack);
                    }

                    $response = $stack();

                    if ($response instanceof Response) {
                        return $response;
                    }

                    return $response;
                }

                return $controller_action();
            }
        }

        throw new \RuntimeException('Route not found', 404);
    }

    /**
     * 
     * @param string $controller_class 
     * @param string $action 
     * @param array $route_params 
     * @return array 
     * @throws InvalidArgumentException 
     */
    protected function findMethodArguments(string $controller_class, string $action, array $route_params): array
    {
        $reflection = new ReflectionMethod($controller_class, $action);
        $arguments = [];

        foreach ($reflection->getParameters() as $param) {
            $paramName = strtolower($param->getName());

            match ($paramName) {
                'request' => $arguments[] = new Request(),
                'response' => $arguments[] = new Response(),
                'args' => $arguments[] = $route_params,
                default => $arguments[] = null
            };
        }

        return $arguments;
    }

    /**
     * 
     * @return array 
     */
    public function listRoutes(): array
    {
        return $this->routes;
    }
}
