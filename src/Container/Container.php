<?php

namespace Koala\Container;

use RuntimeException;

class Container
{
    protected static $instance = null;
    protected $services = [];

    protected function __construct() {}

    /**
     * 
     * @return Container 
     */
    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 
     * @param string $key 
     * @param mixed $value 
     * @return void 
     */
    public function bind(string $key, mixed $value): void
    {
        $this->services[$key] = $value;
    }

    /**
     * 
     * @param string $class 
     * @return object 
     * @throws RuntimeException 
     */
    public function get(string $class): object
    {
        if (isset($this->services[$class])) {
            return $this->services[$class];
        }

        if (!class_exists($class)) {
            return null;
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            $instance = new $class();
            $this->bind($class, $instance);
            return $instance;
        }

        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $param_class = $param->getType()->getName();

            $dependency = $this->get($param_class);

            if (!$dependency && !$param->isOptional()) {
                throw new \RuntimeException("Could not resolve dependency {$param_class}");
            }

            $params[] = $dependency;
        }

        $instance = new $class(...$params);
        $this->bind($class, $instance);

        return $instance;
    }

    /**
     * 
     * @return void 
     */
    public function clear(): void
    {
        $this->services = [];
    }
}
