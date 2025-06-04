<?php

namespace Koala\Config;

class Config
{
    protected static $instance = null;
    protected $settings = [];

    /**
     * Get the singleton instance of the Config class
     * Ensures only one instance exists throughout the application
     *
     * @return Config The singleton instance
     */
    public static function getInstance() : Config
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load configuration settings from a PHP file
     * The file should return an array of configuration values
     *
     * @param string $configPath Path to the configuration file
     * @return void
     */
    public function load(string $configPath): void
    {
        $this->settings = require $configPath;
    }

    /**
     * Retrieve a configuration value using dot notation
     * Returns the default value if the key is not found
     *
     * @param string $key The configuration key (dot notation supported)
     * @param mixed|null $default The default value to return if the key is not found
     * @return mixed The configuration value or default
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
