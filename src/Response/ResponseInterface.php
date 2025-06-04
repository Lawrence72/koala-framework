<?php

namespace Koala\Response;

interface ResponseInterface
{
    /**
     * Send the response to the client
     * This method should handle all necessary headers and output the response content
     * 
     * @return void
     */
    public function send(): void;
}
