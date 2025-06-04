<?php

namespace Koala;

use Koala\Router\Router;
use Koala\Response\JsonResponse;
use Koala\Response\Response;
use Koala\Database\DatabaseManager;
use Koala\Request\Request;
use Koala\Container\Container;
use Koala\Config\Config;
use Koala\Utils\Session;
use RuntimeException;
use Throwable;

class Application
{
    protected Router $router;
    protected ?DatabaseManager $db = null;
    protected Request $request;
    protected Response $response;
    protected Container $container;
    protected Config $config;
    protected array $storage = [];
    protected bool $isCliMode = false;

    /**
     * Initialize a new Application instance
     * Sets up the container, response, request, and router
     */
    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->response = new Response();
        $this->request = new Request();
        $this->router = new Router();
    }

    /**
     * Start the application for web requests
     * Loads configuration and handles the request-response cycle
     *
     * @param string $configPath Path to the configuration file
     * @return void
     * @throws \Throwable If an error occurs during request handling
     */
    public function start(string $configPath): void
    {
        $this->config = Config::getInstance();
        $this->config->load($configPath);

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
     * Loads configuration and sets up CLI mode
     *
     * @param string $configPath Path to the configuration file
     * @return void
     */
    public function startCli(string $configPath): void
    {
        $this->isCliMode = true;

        $this->config = Config::getInstance();
        $this->config->load($configPath);

        $this->container->bind('config', $this->config);
        $this->container->bind(\Koala\Application::class, $this);
    }

    /**
     * Get the dependency injection container
     *
     * @return Container The application's container instance
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Check if the application is running in CLI mode
     *
     * @return bool True if running in CLI mode, false otherwise
     */
    public function isCliMode(): bool
    {
        return $this->isCliMode;
    }

    /**
     * Get the router instance
     *
     * @return Router The application's router instance
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Create and initialize the database manager
     *
     * @return DatabaseManager The initialized database manager instance
     */
    protected function createDatabase(): DatabaseManager
    {
        if ($this->db === null) {
            $config = $this->container->get('config');
            $this->db = new DatabaseManager($config->get('database'));
            $this->container->bind(\Koala\Database\DatabaseManager::class, $this->db);
        }
        return $this->db;
    }

    /**
     * Get a database connection or the database manager
     *
     * @param string|null $connection Name of the database connection to get
     * @return mixed DatabaseManager instance or specific database connection
     */
    public function database(?string $connection = null): mixed
    {
        $dbManager = $this->createDatabase();

        if ($connection === null) {
            return $dbManager;
        }

        return $dbManager->connection($connection);
    }

    /**
     * Create and initialize the session
     * Only creates session in web mode, not in CLI
     *
     * @return void
     */
    public function createSession(): void
    {
        if ($this->isCliMode) {
            return;
        }

        $session = new Session();
        $this->container->bind(Session::class, $session);
    }

    /**
     * Get a configuration value
     *
     * @param string $key The configuration key to retrieve
     * @return mixed The configuration value
     */
    public function getConfig(string $key): mixed
    {
        return $this->config->get($key);
    }

    /**
     * Store a value in the application storage
     *
     * @param string $key The storage key
     * @param mixed $value The value to store
     * @return self For method chaining
     */
    public function set(string $key, mixed $value): self
    {
        $this->storage[$key] = $value;
        return $this;
    }

    /**
     * Retrieve a value from the application storage
     *
     * @param string $key The storage key to retrieve
     * @return mixed The stored value or null if not found
     */
    public function get(string $key): mixed
    {
        return $this->storage[$key] ?? null;
    }

    /**
     * Check if a key exists in the application storage
     *
     * @param string $key The storage key to check
     * @return bool True if the key exists, false otherwise
     */
    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }

    /**
     * Handle application errors
     * Throws errors in CLI mode, returns JSON response in web mode
     *
     * @param \Throwable $e The exception to handle
     * @return void
     * @throws \Throwable In CLI mode
     */
    protected function handleError(\Throwable $e): void
    {
        if ($this->isCliMode) {
            throw $e;
        }

        $code = (int)($e->getCode()) ?: 500;
        $response = new JsonResponse(
            ['error' => $e->getMessage()],
            $code
        );
        $response->send();
    }

    /**
     * Magic getter for backward compatibility and convenience
     *
     * @param string $name The property name to get
     * @return mixed The requested property value
     * @throws \RuntimeException If the property doesn't exist
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'Router' => $this->getRouter(),
            'request' => $this->isCliMode ? null : $this->request,
            'response' => $this->isCliMode ? null : $this->response,
            'Database', 'database' => $this->database(),
            default => throw new \RuntimeException("Property $name not found"),
        };
    }
}
