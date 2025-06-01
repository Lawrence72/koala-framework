<?php

namespace Koala;

use Koala\Router\Router;
use Koala\Response\JsonResponse;
use Koala\Response\Response;
use Koala\Database\Database;
use Koala\Request\Request;
use Koala\Container\Container;
use Koala\Config\Config;
use Koala\Utils\Session;
use RuntimeException;
use Throwable;

class Application
{
    protected Router $router;
    protected ?Database $db = null;
    protected Request $request;
    protected Response $response;
    protected Container $container;
    protected Config $config;
    protected array $storage = [];
    protected bool $isCliMode = false;

    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->response = new Response();
        $this->request = new Request();
        $this->router = new Router();
    }

    /**
     * Start the application for web requests
     * @param string $config_path 
     * @return void 
     */
    public function start(string $config_path): void
    {
        $this->config = Config::getInstance();
        $this->config->load($config_path);

        $this->container->bind('config', $this->config);
        $this->container->bind(\Koala\Application::class, $this);

        try {
            $method = $this->request->getMethod();
            $path = $this->request->getRoute();
            $response = $this->router->dispatch($method, $path);
            $response->send();
        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Start the application for CLI usage
     * @param string $config_path 
     * @return void 
     */
    public function startCli(string $config_path): void
    {
        $this->isCliMode = true;
        
        // Load configuration
        $this->config = Config::getInstance();
        $this->config->load($config_path);

        // Bind core services to container
        $this->container->bind('config', $this->config);
        $this->container->bind(\Koala\Application::class, $this);
        
        // Initialize database (but don't create it yet, let it be lazy-loaded)
        // The createDatabase() method will handle this when needed
        
        // Note: We don't initialize Request/Response for CLI as they're HTTP-specific
        // Controllers running in CLI mode should handle this gracefully
    }

    /**
     * Get the dependency injection container
     * @return Container 
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Check if running in CLI mode
     * @return bool 
     */
    public function isCliMode(): bool
    {
        return $this->isCliMode;
    }

    /**
     * 
     * @return Router 
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * 
     * @return Database 
     * @throws RuntimeException 
     */
    protected function createDatabase(): Database
    {
        if ($this->db === null) {
            $config = $this->container->get('config');
            $this->db = new Database($config->get('database'));
            $this->container->bind(\Koala\Database\Database::class, $this->db);
        }
        return $this->db;
    }
    /**
     * 
     * @return void 
     */

    public function createSession(): void
    {
        // Skip session creation in CLI mode
        if ($this->isCliMode) {
            return;
        }
        
        $session = new Session();
        $this->container->bind(Session::class, $session);
    }

    /**
     * @param string $key 
     * @return mixed 
     */
    public function getConfig(string $key): mixed
    {
        return $this->config->get($key);
    }

    /**
     * @param string $key 
     * @param mixed $value 
     * @return Application 
     */
    public function set(string $key, mixed $value): self
    {
        $this->storage[$key] = $value;
        return $this;
    }

    /**
     * @param string $key 
     * @return mixed 
     */
    public function get(string $key): mixed
    {
        return $this->storage[$key] ?? null;
    }

    /**
     * @param string $key 
     * @return bool 
     */
    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }


    /**
     * Handle errors differently for CLI vs web
     * @param Throwable $e 
     * @return void 
     */
    protected function handleError(\Throwable $e): void
    {
        if ($this->isCliMode) {
            // For CLI, just throw the exception to be handled by the calling script
            throw $e;
        }
        
        // For web requests, send JSON response
        $code = (int)($e->getCode()) ?: 500;
        $response = new JsonResponse(
            ['error' => $e->getMessage()],
            $code
        );
        $response->send();
    }

    /**
     * 
     * @param string $name 
     * @return mixed 
     * @throws RuntimeException 
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'Router' => $this->getRouter(),
            'request' => $this->isCliMode ? null : $this->request,
            'response' => $this->isCliMode ? null : $this->response,
            'Database', 'database' => $this->createDatabase(),
            default => throw new \RuntimeException("Property $name not found"),
        };
    }
}