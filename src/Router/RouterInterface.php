<?php

namespace Koala\Router;

interface RouterInterface
{
    /**
     * Add a new route to the router
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path URL path pattern
     * @param string $controller_class Fully qualified class name of the controller
     * @param string $action Name of the controller method to call
     * @return void
     */
    public function addRoute(string $method, string $path, string $controller_class, string $action): void;

    /**
     * Dispatch a request to the appropriate route handler
     *
     * @param string $method HTTP method of the request
     * @param string $path URL path of the request
     * @return mixed Response from the route handler
     * @throws \RuntimeException If no matching route is found
     */
    public function dispatch(string $method, string $path): mixed;
}
