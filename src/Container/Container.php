<?php

namespace Koala\Container;

use RuntimeException;
use ReflectionClass;
use ReflectionException;

class Container
{
    protected static ?Container $instance = null;
    protected array $services = [];
    protected array $instances = [];
    protected array $reflectionCache = [];
    protected array $resolutionStack = [];
   
    /**
     * Get the singleton instance of the Container
     * Ensures only one instance exists throughout the application
     *
     * @return Container The singleton instance
     */
    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Bind a service or value to the container
     * If an object is provided, it is also cached as an instance
     *
     * @param string $key The service identifier
     * @param mixed $value The service definition or instance
     * @return void
     */
    public function bind(string $key, mixed $value): void
    {
        $this->services[$key] = $value;

        // If we're binding an instance, cache it
        if (is_object($value)) {
            $this->instances[$key] = $value;
        }
    }

    /**
     * Bind a singleton service (created once and reused)
     *
     * @param string $key The service identifier
     * @param mixed $value The service definition or instance
     * @return void
     */
    public function singleton(string $key, mixed $value): void
    {
        $this->bind($key, $value);
    }

    /**
     * Retrieve a service or instance from the container
     * If not found, attempts to auto-resolve the class and its dependencies
     *
     * @param string $class The class or service identifier
     * @return mixed The resolved service or instance
     * @throws RuntimeException If the service cannot be resolved or circular dependency detected
     */
    public function get(string $class): mixed
    {
        // Check if we have a manually bound service
        if (isset($this->services[$class])) {
            $service = $this->services[$class];

            // If it's a closure/callable, execute it once and cache the result
            if (is_callable($service) && !is_object($service)) {
                if (!isset($this->instances[$class])) {
                    $this->instances[$class] = $service($this);
                }
                return $this->instances[$class];
            }

            return $service;
        }

        // Check if we already have an instance cached
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        // Check for circular dependencies
        if (in_array($class, $this->resolutionStack)) {
            throw new RuntimeException(
                "Circular dependency detected: " . implode(' -> ', $this->resolutionStack) . " -> {$class}"
            );
        }

        // Try to auto-resolve the class
        return $this->resolve($class);
    }

    /**
     * Automatically resolve a class and its dependencies using reflection
     *
     * @param string $class The class name to resolve
     * @return mixed The resolved class instance
     * @throws RuntimeException If the class does not exist or cannot be resolved
     */
    protected function resolve(string $class): mixed
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Class {$class} does not exist");
        }

        // Add to resolution stack for circular dependency detection
        $this->resolutionStack[] = $class;

        try {
            $reflectionInfo = $this->getReflectionInfo($class);

            if ($reflectionInfo['constructor'] === null) {
                // No constructor, simple instantiation
                $instance = new $class();
            } else {
                // Resolve constructor dependencies
                $params = $this->resolveConstructorParams($reflectionInfo['parameters']);
                $instance = new $class(...$params);
            }

            // Cache the instance for reuse
            $this->instances[$class] = $instance;

            // Remove from resolution stack
            array_pop($this->resolutionStack);

            return $instance;
        } catch (ReflectionException $e) {
            // Remove from resolution stack on error
            array_pop($this->resolutionStack);
            throw new RuntimeException("Could not resolve class {$class}: " . $e->getMessage());
        }
    }

    /**
     * Get reflection information for a class (with caching)
     *
     * @param string $class The class name
     * @return array Reflection info including constructor and parameters
     */
    protected function getReflectionInfo(string $class): array
    {
        if (isset($this->reflectionCache[$class])) {
            return $this->reflectionCache[$class];
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        $info = [
            'constructor' => $constructor,
            'parameters' => $constructor ? $constructor->getParameters() : []
        ];

        // Cache the reflection info
        $this->reflectionCache[$class] = $info;

        return $info;
    }

    /**
     * Resolve constructor parameters for dependency injection
     *
     * @param array $parameters Array of ReflectionParameter objects
     * @return array Resolved parameter values
     * @throws RuntimeException If a parameter cannot be resolved
     */
    protected function resolveConstructorParams(array $parameters): array
    {
        $resolvedParams = [];

        foreach ($parameters as $param) {
            $paramType = $param->getType();

            if ($paramType === null) {
                // No type hint
                if ($param->isOptional()) {
                    $resolvedParams[] = $param->getDefaultValue();
                } else {
                    throw new RuntimeException(
                        "Cannot resolve parameter '{$param->getName()}' - no type hint provided"
                    );
                }
            } elseif ($paramType->isBuiltin()) {
                // Built-in type (string, int, etc.)
                if ($param->isOptional()) {
                    $resolvedParams[] = $param->getDefaultValue();
                } else {
                    throw new RuntimeException(
                        "Cannot resolve parameter '{$param->getName()}' - built-in type without default value"
                    );
                }
            } else {
                // Class type - resolve recursively
                $paramClass = $paramType->getName();
                try {
                    $resolvedParams[] = $this->get($paramClass);
                } catch (RuntimeException $e) {
                    if ($param->isOptional()) {
                        $resolvedParams[] = null;
                    } else {
                        throw new RuntimeException(
                            "Could not resolve dependency '{$paramClass}' for parameter '{$param->getName()}': " . $e->getMessage()
                        );
                    }
                }
            }
        }

        return $resolvedParams;
    }

    /**
     * Check if a service is bound or can be resolved
     *
     * @param string $key The service identifier or class name
     * @return bool True if the service is bound or resolvable, false otherwise
     */
    public function has(string $key): bool
    {
        return isset($this->services[$key]) || isset($this->instances[$key]) || class_exists($key);
    }

    /**
     * Clear all cached instances, bindings, and reflection data
     *
     * @return void
     */
    public function clear(): void
    {
        $this->services = [];
        $this->instances = [];
        $this->reflectionCache = [];
        $this->resolutionStack = [];
    }

    /**
     * Clear only cached instances (keep bindings and reflection cache)
     *
     * @return void
     */
    public function clearInstances(): void
    {
        $this->instances = [];
    }

    /**
     * Get container statistics for debugging purposes
     *
     * @return array Statistics including counts and resolution stack
     */
    public function getStats(): array
    {
        return [
            'boundServices' => count($this->services),
            'cachedInstances' => count($this->instances),
            'reflectionCache' => count($this->reflectionCache),
            'resolutionStack' => $this->resolutionStack
        ];
    }

    /**
     * Get all cached instance keys (for debugging)
     *
     * @return array List of cached instance keys
     */
    public function getCachedInstances(): array
    {
        return array_keys($this->instances);
    }
}
