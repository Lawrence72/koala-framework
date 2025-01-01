<?php

namespace Koala\Response;

use Koala\Config\Config;

class SimpleResponse implements ResponseInterface
{
    protected string $template_dir;
    protected array $data;

    public function __construct(
        protected string $template,
        array $data = [],
        protected int $status = 200,
        ?string $template_dir = null
    ) {
        $config = Config::getInstance();
        $app_directory = $config->get('paths.app_directory');

        $this->template_dir = $template_dir ?? $app_directory . '/views';

        $this->data = $this->sanitizeData($data);
    }

    /**
     * 
     * @param array $data 
     * @return array 
     */
    protected function sanitizeData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            } else {
                $sanitized[$key] = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
            }
        }
        return $sanitized;
    }

    /**
     * 
     * @param string $content 
     * @return string 
     */
    public function raw(string $content): string
    {
        return html_entity_decode(
            htmlspecialchars_decode($content, ENT_QUOTES)
        );
    }

    /**
     * 
     * @return void 
     */
    public function send(): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        http_response_code($this->status);

        $template_path = $this->template_dir . '/' . $this->template . '.php';
        if (!file_exists($template_path)) {
            throw new \RuntimeException("Template file not found: {$template_path}");
        }

        extract($this->data);
        ob_start();
        include $template_path;
        echo ob_get_clean();
    }
}
