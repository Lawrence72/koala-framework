<?php

namespace Koala\Response;

use Koala\Utils\Collection;

class Response
{
    protected static string $viewEngine = SimpleResponse::class;

    /**
     * Set the view engine class to use for rendering views
     * The engine class must implement ResponseInterface
     *
     * @param string $engineClass Fully qualified class name of the view engine
     * @return void
     * @throws \InvalidArgumentException If the engine class doesn't implement ResponseInterface
     */
    public static function setViewEngine(string $engineClass): void
    {
        $implements = class_implements($engineClass);
        if (!isset($implements[ResponseInterface::class])) {
            throw new \InvalidArgumentException('View engine must implement ResponseInterface');
        }

        self::$viewEngine = $engineClass;
    }

    /**
     * Create a view response using the configured view engine
     *
     * @param string $template The template file to render
     * @param array $data Data to pass to the template
     * @param int $status HTTP status code
     * @return ResponseInterface The response instance
     */
    public function view(string $template, array $data = [], int $status = 200): ResponseInterface
    {
        $engineClass = self::$viewEngine;
        return new $engineClass($template, $data, $status);
    }

    /**
     * Create a JSON response
     *
     * @param array|Collection $data Data to be encoded as JSON
     * @param int $status HTTP status code
     * @return JsonResponse The JSON response instance
     */
    public function json(array|Collection $data, int $status = 200): JsonResponse
    {
        if ($data instanceof Collection) {
            $data = $data->jsonSerialize();
        }
        return new JsonResponse($data, $status);
    }

    /**
     * Create a redirect response
     *
     * @param string $url The URL to redirect to
     * @param int $status HTTP status code (defaults to 302 Found)
     * @return RedirectResponse The redirect response instance
     */
    public function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * Create a generic response with plain content
     *
     * @param string $content The response content
     * @param int $status HTTP status code
     * @return GenericResponse The generic response instance
     */
    public function generic(string $content = '', int $status = 200): GenericResponse
    {
        return new GenericResponse($content, $status);
    }
}
