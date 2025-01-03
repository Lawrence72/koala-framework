<?php

namespace Koala\Request;

use Koala\Utils\Collection;

class Request
{
    protected Collection $get;
    protected Collection $post;
    protected Collection $server;
    protected ?Collection $json = null;

    public function __construct()
    {
        $this->get = new Collection($_GET ?? []);
        $this->post = new Collection($_POST ?? []);
        $this->server = new Collection($_SERVER ?? []);

        if ($this->isJson()) {
            $input = file_get_contents('php://input');
            $this->json = new Collection(json_decode($input, true) ?? []);
        }
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }


    /**
     * @return mixed
     */
    public function getQueryParams(): mixed
    {
        return $this->get ?? [];
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getPostParam(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * @return mixed
     */
    public function getPostParams(): mixed
    {
        return $this->post ?? [];
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getJsonParam(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $default;
    }

    /**
     * @return mixed
     */
    public function getJsonParams(): mixed
    {
        return $this->json ?? [];
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        if ($this->getMethod() === 'GET') {
            return $this->get;
        }

        if ($this->isJson()) {
            return $this->json ?? [];
        }

        return $this->post;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        if ($this->getMethod() === 'GET') {
            return isset($this->get[$key]);
        }

        if ($this->isJson()) {
            return isset($this->json[$key]);
        }

        return isset($this->post[$key]);
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->server['REQUEST_METHOD'];
    }

    /**
     * @return string
     */
    public function getRoute(): string
    {
        return parse_url($this->server['REQUEST_URI'], PHP_URL_PATH);
    }

    /**
     * @return bool
     */
    public function isJson(): bool
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        return str_contains($contentType, 'application/json');
    }
}
