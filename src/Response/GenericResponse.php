<?php

namespace Koala\Response;

class GenericResponse implements ResponseInterface
{
    protected array $headers = [];
    protected string $content;
    protected int $statusCode;

    /**
     * Create a new generic response instance
     * 
     * @param string $content The response content (defaults to empty string)
     * @param int $statusCode HTTP status code (defaults to 200 OK)
     */
    public function __construct(string $content = '', int $statusCode = 200)
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
    }

    /**
     * Set a response header
     * 
     * @param string $name The header name
     * @param string $value The header value
     * @return self For method chaining
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set the HTTP status code
     * 
     * @param int $statusCode The HTTP status code
     * @return self For method chaining
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Set the response content
     * 
     * @param string $content The response content
     * @return self For method chaining
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Send the response to the client
     * Sets all headers, status code, and outputs the content
     * 
     * @return void
     */
    public function send(): void
    {
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        http_response_code($this->statusCode);
        echo $this->content;
    }
}
