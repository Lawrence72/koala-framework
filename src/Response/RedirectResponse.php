<?php

namespace Koala\Response;

class RedirectResponse implements ResponseInterface
{
    protected string $url;
    protected int $status;

    /**
     * Create a new redirect response instance
     * 
     * @param string $url The URL to redirect to
     * @param int $status HTTP status code (defaults to 302 Found)
     */
    public function __construct(string $url, int $status = 302)
    {
        $this->url = $url;
        $this->status = $status;
    }

    /**
     * Send the redirect response to the client
     * Sets the Location header and exits the script
     * 
     * @return void
     */
    public function send(): void
    {
        header('Location: ' . $this->url, true, $this->status);
        exit();
    }
}
