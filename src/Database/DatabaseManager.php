<?php

namespace Koala\Database;

use RuntimeException;

class DatabaseManager
{
    protected array $config;
    protected array $connections = [];
    protected string $defaultConnection;

    public function __construct(array $config)
    {
        $this->config = $config;

        // Determine default connection
        if (isset($config['connections'])) {
            // New multi-connection format
            $this->defaultConnection = $config['default'] ?? 'main';
            if (!isset($config['connections'][$this->defaultConnection])) {
                throw new RuntimeException("Default database connection '{$this->defaultConnection}' not found in config");
            }
        } else {
            // Legacy single connection format
            $this->defaultConnection = 'default';
            $this->config['connections'] = ['default' => $config];
        }
    }

    /**
     * Get a specific database connection
     */
    public function connection(string $name): Database
    {
        if (!isset($this->connections[$name])) {
            if (!isset($this->config['connections'][$name])) {
                throw new RuntimeException("Database connection '{$name}' not found in config");
            }

            $this->connections[$name] = new Database($this->config['connections'][$name]);
        }

        return $this->connections[$name];
    }

    /**
     * Get the default database connection
     */
    public function getDefaultConnection(): Database
    {
        return $this->connection($this->defaultConnection);
    }

    /**
     * Magic method to proxy default database methods
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->getDefaultConnection()->$method(...$arguments);
    }

    /**
     * Get all available connection names
     */
    public function getConnectionNames(): array
    {
        return array_keys($this->config['connections']);
    }

    /**
     * Check if a connection exists
     */
    public function hasConnection(string $name): bool
    {
        return isset($this->config['connections'][$name]);
    }

    /**
     * Disconnect all connections
     */
    public function disconnectAll(): void
    {
        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }
        $this->connections = [];
    }

    /**
     * Get connection statistics
     */
    public function getStats(): array
    {
        $stats = [
            'defaultConnection' => $this->defaultConnection,
            'availableConnections' => $this->getConnectionNames(),
            'activeConnections' => array_keys($this->connections),
            'connectionDetails' => []
        ];

        foreach ($this->connections as $name => $connection) {
            $stats['connectionDetails'][$name] = [
                'connected' => $connection->isConnected(),
                'connectionAttempted' => $connection->connectionAttempted()
            ];
        }

        return $stats;
    }
}
