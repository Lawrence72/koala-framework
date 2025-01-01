<?php

namespace Koala\Response;

class Response
{
    protected static string $view_engine = SimpleResponse::class;

    /**
     * 
     * @param string $engine_class 
     * @return void 
     */
    public static function setviewEngine(string $engine_class): void
    {
        $implements = class_implements($engine_class);
        if (!isset($implements[ResponseInterface::class])) {
            throw new \InvalidArgumentException('View engine must implement ResponseInterface');
        }
        
        self::$view_engine = $engine_class;
    }

    /**
     * 
     * @param string $template 
     * @param array $data 
     * @param int $status 
     * @return ResponseInterface 
     */
    public function view(string $template, array $data = [], int $status = 200): ResponseInterface
    {
        $engine_class = self::$view_engine;
        return new $engine_class($template, $data, $status);
    }

    /**
     * 
     * @param array $data 
     * @param int $status 
     * @return JsonResponse 
     */
    public function json(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }
}
