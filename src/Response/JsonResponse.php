<?php

namespace Koala\Response;

class JsonResponse implements ResponseInterface
{
    /**
     * Create a new JSON response instance
     * 
     * @param array $data The data to be encoded as JSON
     * @param int $status HTTP status code (defaults to 200 OK)
     */
    public function __construct(
        protected array $data,
        protected int $status = 200
    ) {}

    /**
     * Send the JSON response to the client
     * Sets appropriate headers and encodes the data as JSON
     * 
     * @return void
     */
    public function send(): void
    {
        header('Content-Type: application/json');
        $statusCode = $this->data['status'] ?? $this->status;
        $this->data['status'] = $statusCode;
        http_response_code($statusCode);
        echo json_encode($this->data);
    }

    /**
     * Get the response data array
     * 
     * @return array The data that will be encoded as JSON
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the HTTP status code
     * 
     * @return int The HTTP status code
     */
    public function getStatus(): int
    {
        return $this->status;
    }
}
