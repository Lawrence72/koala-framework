<?php

namespace Koala\Response;

use Koala\Config\Config;

class SimpleResponse implements ResponseInterface
{
    protected string $templateDir;
    protected array $data;

    /**
     * Initialize a new SimpleResponse instance
     * 
     * @param string $template The template file name to render
     * @param array $data The data to pass to the template
     * @param int $status The HTTP status code to return
     * @param string|null $templateDir Custom template directory path
     */
    public function __construct(
        protected string $template,
        array $data = [],
        protected int $status = 200,
        ?string $templateDir = null
    ) {
        $config = Config::getInstance();
        $appDirectory = $config->get('paths.app_directory');

        $this->templateDir = $templateDir ?? $appDirectory . '/views';
        $this->template = $this->sanitizeTemplateName($template);
        $this->data = $this->sanitizeData($data);
    }

    /**
     * Sanitize template name to prevent directory traversal and ensure valid characters
     * 
     * @param string $template The template name to sanitize
     * @return string The sanitized template name
     * @throws \InvalidArgumentException If template name contains invalid characters
     */
    protected function sanitizeTemplateName(string $template): string
    {
        $template = str_replace(['../', '..\\', '../', '..\\'], '', $template);
        $template = ltrim($template, '/\\');
        if (!preg_match('/^[a-zA-Z0-9_\-\/]+$/', $template)) {
            throw new \InvalidArgumentException("Invalid template name: {$template}");
        }
        return $template;
    }

    /**
     * Validate that the template path is within the allowed directory
     * 
     * @param string $templatePath The full path to the template file
     * @return bool True if the template path is valid and within allowed directory
     */
    protected function isValidTemplatePath(string $templatePath): bool
    {
        $realTemplateDir = realpath($this->templateDir);
        $realTemplatePath = realpath($templatePath);
        return $realTemplatePath !== false &&
               $realTemplateDir !== false &&
               str_starts_with($realTemplatePath, $realTemplateDir);
    }

    /**
     * Sanitize data to prevent XSS and ensure safe variable names
     * 
     * @param array $data The data array to sanitize
     * @return array The sanitized data array
     */
    protected function sanitizeData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            if (is_array($value)) {
                $sanitized[$safeKey] = $this->sanitizeData($value);
            } elseif (is_object($value)) {
                $sanitized[$safeKey] = $value;
            } else {
                $sanitized[$safeKey] = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
            }
        }
        return $sanitized;
    }

    /**
     * Convert HTML entities back to their corresponding characters
     * 
     * @param string $content The content to decode
     * @return string The decoded content
     */
    public function raw(string $content): string
    {
        return html_entity_decode(
            htmlspecialchars_decode($content, ENT_QUOTES)
        );
    }

    /**
     * Send the response to the client
     * Renders the template with the provided data and outputs it
     * 
     * @return void
     * @throws \RuntimeException If template file is not found or path is invalid
     */
    public function send(): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        http_response_code($this->status);

        $templatePath = $this->templateDir . '/' . $this->template . '.php';
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template file not found: {$templatePath}");
        }

        if (!$this->isValidTemplatePath($templatePath)) {
            throw new \RuntimeException("Template path is outside allowed directory: {$templatePath}");
        }

        $output = $this->renderTemplate($templatePath, $this->data);
        echo $output;
    }

    /**
     * Render the template with the provided data in an isolated scope
     * 
     * @param string $templatePath The full path to the template file
     * @param array $data The data to pass to the template
     * @return string The rendered template output
     */
    protected function renderTemplate(string $templatePath, array $data): string
    {
        $render = function() use ($templatePath, $data) {
            foreach ($data as $key => $value) {
                $$key = $value;
            }
            ob_start();
            include $templatePath;
            return ob_get_clean();
        };
        return $render();
    }
}