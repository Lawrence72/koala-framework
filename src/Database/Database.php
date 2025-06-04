<?php

namespace Koala\Database;

use PDO;
use PDOException;
use RuntimeException;
use Koala\Config\Config;
use Koala\Utils\Collection;

class Database
{
    protected ?PDO $conn = null;
    protected array $config;
    protected bool $connectionAttempted = false;

    public function __construct(?array $config = null)
    {
        if ($config === null) {
            $this->config = Config::getInstance()->get('database');
        } else {
            $this->config = $config;
        }
    }

    /**
     * Get the PDO connection instance
     *
     * @return PDO
     * @throws RuntimeException If connection fails
     */
    public function getConnection(): PDO
    {
        if ($this->conn === null) {
            $this->connectionAttempted = true;

            try {
                $driver = $this->config['driver'] ?? 'mysql';

                $dsn = match ($driver) {
                    'mysql' => $this->getMySqlDsn(),
                    'pgsql' => $this->getPgSqlDsn(),
                    'sqlite' => $this->getSqliteDsn(),
                    'sqlsrv' => $this->getSqlServerDsn(),
                    default => throw new RuntimeException("Unsupported database driver: $driver")
                };

                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                if ($driver === 'sqlite') {
                    $this->conn = new PDO($dsn, null, null, $options);
                } else {
                    $this->conn = new PDO(
                        $dsn,
                        $this->config['username'] ?? '',
                        $this->config['password'] ?? '',
                        $options
                    );
                }
            } catch (PDOException $e) {
                throw new RuntimeException("Database connection failed: " . $e->getMessage());
            }
        }
        return $this->conn;
    }

    /**
     * Check if the database connection is established
     *
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool
    {
        return $this->conn !== null;
    }

    /**
     * Check if a connection attempt has been made
     *
     * @return bool True if connection was attempted, false otherwise
     */
    public function connectionAttempted(): bool
    {
        return $this->connectionAttempted;
    }

    /**
     * Disconnect from the database
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->conn = null;
        $this->connectionAttempted = false;
    }

    /**
     * Magic method to proxy PDO methods
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return mixed
     * @throws RuntimeException
     */
    public function __call($name, $arguments)
    {
        return $this->getConnection()->$name(...$arguments);
    }

    /**
     * Execute a query with smart return handling
     * Automatically returns data if the query produces results, otherwise returns execution info
     *
     * @param string $sql The SQL query to execute
     * @param array $params Parameters to bind to the query
     * @return mixed Query results if data is returned, execution info otherwise
     * @throws RuntimeException
     */
    public function runQuery(string $sql, array $params = []): mixed
    {
        $statement = $this->getConnection()->prepare($sql);
        $statement->execute($params);

        if ($statement->columnCount() > 0) {
            return $this->handleQueryResults($statement);
        }

       return [
            'rowCount' => $statement->rowCount(),
            'lastInsertId' => $this->getConnection()->lastInsertId() ?: null,
            'statement' => $statement
        ];
    }

    /**
     * Handle results from queries that return data
     *
     * @param \PDOStatement $statement The executed statement
     * @return mixed Appropriate return data based on result count and structure
     */
    protected function handleQueryResults(\PDOStatement $statement): mixed
    {
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            // Query returned columns but no rows
            return [];
        }

        if (count($results) === 1) {
            $row = $results[0];

            // If single row with single column, return just the value
            if (count($row) === 1) {
                return reset($row);
            }

            // Single row with multiple columns, return as Collection
            return new Collection($row);
        }

        // Multiple rows, return array of Collections
        return array_map(fn($row) => new Collection($row), $results);
    }

    /**
     * Fetch a single field value from the first row
     *
     * @param string $sql The SQL query to execute
     * @param array $params Parameters to bind to the query
     * @return mixed The field value or null if no results
     * @throws RuntimeException
     * @throws PDOException
     */
    public function fetchField(string $sql, array $params = []): mixed
    {
        $statement = $this->getConnection()->prepare($sql);
        $statement->execute($params);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result ? reset($result) : null;
    }

    /**
     * Fetch a single row as a Collection
     *
     * @param string $sql The SQL query to execute
     * @param array $params Parameters to bind to the query
     * @return Collection|null The row as a Collection or null if no results
     */
    public function fetchRow(string $sql, array $params = []): ?Collection
    {
        if (stripos($sql, 'LIMIT') === false) {
            $sql .= ' LIMIT 1';
        }
        $results = $this->fetchAll($sql, $params);
        return $results ? $results[0] : null;
    }

    /**
     * Fetch all rows as an array of Collections
     *
     * @param string $sql The SQL query to execute
     * @param array $params Parameters to bind to the query
     * @return array Array of Collection objects representing rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->getConnection()->prepare($sql);
        $statement->execute($params);

        $results = $statement->fetchAll();

        if (empty($results)) {
            return [];
        }

        foreach ($results as &$result) {
            $result = new Collection($result);
        }
        return $results;
    }

    /**
     * Generate MySQL DSN string
     *
     * @return string The MySQL DSN
     */
    protected function getMySqlDsn(): string
    {
        return sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $this->config['host'] ?? 'localhost',
            $this->config['port'] ?? 3306,
            $this->config['dbname'],
            $this->config['charset'] ?? 'utf8mb4'
        );
    }

    /**
     * Generate PostgreSQL DSN string
     *
     * @return string The PostgreSQL DSN
     */
    protected function getPgSqlDsn(): string
    {
        return sprintf(
            "pgsql:host=%s;port=%s;dbname=%s",
            $this->config['host'] ?? 'localhost',
            $this->config['port'] ?? 5432,
            $this->config['dbname']
        );
    }

    /**
     * Generate SQLite DSN string
     *
     * @return string The SQLite DSN
     */
    protected function getSqliteDsn(): string
    {
        return sprintf(
            "sqlite:%s",
            $this->config['path']
        );
    }

    /**
     * Generate SQL Server DSN string
     *
     * @return string The SQL Server DSN
     */
    protected function getSqlServerDsn(): string
    {
        return sprintf(
            "sqlsrv:Server=%s,%s;Database=%s",
            $this->config['host'] ?? 'localhost',
            $this->config['port'] ?? 1433,
            $this->config['dbname']
        );
    }
}
