<?php

namespace Koala\Config;

class Config
{
    protected static $instance = null;
    protected $settings = [];

    /**
     * 
     * @return Config 
     */
    public static function getInstance() : Config
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 
     * @param string $config_path 
     * @return void 
     */
    public function load(string $config_path): void
    {
        $this->settings = require $config_path;
    }

    /**
     * 
     * @param string $key 
     * @param mixed|null $default 
     * @return mixed 
     */
    public function get(string $key, mixed $default = null) : mixed
    {
        $parts = explode('.', $key);
        $config = $this->settings;

        foreach ($parts as $part) {
            if (!isset($config[$part])) {
                return $default;
            }
            $config = $config[$part];
        }

        return $config;
    }
}
