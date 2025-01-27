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

    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->response = new Response();
        $this->request = new Request();
        $this->router = new Router();
    }

    /**
     * 
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
        $session = new Session();
        $this->container->bind(Session::class, $session);
    }

    /**
     * 
     * @param string $key 
     * @return mixed 
     */
    public function getConfig(string $key): mixed
    {
        return $this->config->get($key);
    }


    /**
     * 
     * @param Throwable $e 
     * @return void 
     */
    protected function handleError(\Throwable $e): void
    {
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
            'request' => $this->request,
            'response' => $this->response,
            'Database', 'database' => $this->createDatabase(),
            default => throw new \RuntimeException("Property $name not found"),
        };
    }
}
