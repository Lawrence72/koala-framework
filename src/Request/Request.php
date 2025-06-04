<?php

namespace Koala\Request;

use Koala\Utils\Collection;

class Request
{
    protected Collection $get;
    protected Collection $post;
    protected Collection $server;
    protected ?Collection $json = null;

    /**
     * Initialize a new Request instance
     * Sets up collections for GET, POST, SERVER data and JSON input
     */
    public function __construct()
    {
        $this->get = new Collection($_GET ?? []);
        $this->post = new Collection($_POST ?? []);
        $this->server = new Collection($_SERVER ?? []);

        if ($this->isJson()) {
            $input = file_get_contents('php://input');
            $decoded = json_decode($input, true) ?? [];
            $jsonData = $decoded['data'] ?? $decoded;
            $this->json = new Collection($jsonData);
        }
    }

    /**
     * Get a query parameter from the GET request
     * 
     * @param string $key The parameter name to retrieve
     * @param mixed $default Default value if parameter is not found
     * @return mixed The parameter value or default value
     */
    public function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /**
     * Get all query parameters from the GET request
     * 
     * @return Collection Collection of all GET parameters
     */
    public function getQueryParams(): Collection
    {
        return $this->get;
    }

    /**
     * Get a parameter from the POST request
     * 
     * @param string $key The parameter name to retrieve
     * @param mixed $default Default value if parameter is not found
     * @return mixed The parameter value or default value
     */
    public function getPostParam(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Get all parameters from the POST request
     * 
     * @return Collection Collection of all POST parameters
     */
    public function getPostParams(): Collection
    {
        return $this->post;
    }

    /**
     * Get a parameter from the JSON request body
     * 
     * @param string $key The parameter name to retrieve
     * @param mixed $default Default value if parameter is not found
     * @return mixed The parameter value or default value
     */
    public function getJsonParam(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $default;
    }

    /**
     * Get all parameters from the JSON request body
     * 
     * @return Collection|null Collection of all JSON parameters or null if not a JSON request
     */
    public function getJsonParams(): ?Collection
    {
        return $this->json;
    }

    /**
     * Get all parameters based on the request method
     * Returns GET parameters for GET requests, POST parameters for POST requests,
     * and JSON parameters for JSON requests
     * 
     * @return Collection Collection of all parameters based on request method
     */
    public function getAll(): Collection
    {
        if ($this->getMethod() === 'GET') {
            return $this->get;
        }

        if ($this->isJson()) {
            return $this->json ?? new Collection([]);
        }

        return $this->post;
    }

    /**
     * Check if a parameter exists in the request
     * Checks GET parameters for GET requests, JSON parameters for JSON requests,
     * and POST parameters for other requests
     * 
     * @param string $key The parameter name to check
     * @return bool True if the parameter exists, false otherwise
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
     * Get the HTTP method of the request
     * 
     * @return string The HTTP method (GET, POST, PUT, etc.)
     */
    public function getMethod(): string
    {
        return $this->server['REQUEST_METHOD'];
    }

    /**
     * Get the request route/path
     * 
     * @return string The request path without query parameters
     */
    public function getRoute(): string
    {
        return parse_url($this->server['REQUEST_URI'], PHP_URL_PATH);
    }

    /**
     * Check if the request contains JSON data
     * 
     * @return bool True if the request has JSON content type, false otherwise
     */
    public function isJson(): bool
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        return str_contains($contentType, 'application/json');
    }
}
