<?php

namespace Koala\Router;

interface RouterInterface
{
    public function addRoute(string $method, string $path, string $controller_class, string $action): void;
    public function dispatch(string $method, string $path): mixed;
}
