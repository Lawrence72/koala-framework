<?php

namespace Koala\Database;

use Koala\Config\Config;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class Database
{
    protected ?PDO $conn = null;
    protected array $config;

    public function __construct(?array $config = null)
    {
        if ($config === null) {
            $this->config = Config::getInstance()->get('database');
        } else {
            $this->config = $config;
        }
    }

    /**
     * 
     * @return PDO 
     * @throws RuntimeException 
     */
    public function getConnection(): PDO
    {
        if ($this->conn === null) {
            try {
                $driver = $this->config['driver'] ?? 'mysql';

                $dsn = match ($driver) {
                    'mysql' => $this->getMySqlDsn(),
                    'pgsql' => $this->getPgSqlDsn(),
                    'sqlite' => $this->getSqliteDsn(),
                    'sqlsrv' => $this->getSqlServerDsn(),
                    default => throw new \RuntimeException("Unsupported database driver: $driver")
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
                throw new \RuntimeException("Database connection failed: " . $e->getMessage());
            }
        }
        return $this->conn;
    }

    /**
     * 
     * @param string $sql 
     * @param array $params 
     * @return PDOStatement 
     * @throws RuntimeException 
     * @throws PDOException 
     */
    public function runQuery(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->getConnection()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    /**
     * 
     * @param string $sql 
     * @param array $params 
     * @return null|array 
     * @throws RuntimeException 
     * @throws PDOException 
     */
    public function fetchField(string $sql, array $params = []) : ?array
    {
        $result = $this->fetchRow($sql, $params);
        return $result ? reset($result) : null;
    }

    /**
     * 
     * @param string $sql 
     * @param array $params 
     * @return array|null 
     */
    public function fetchRow(string $sql, array $params = []): ?array
    {
        if (stripos($sql, 'LIMIT') === false) {
            $sql .= ' LIMIT 1';
        }
        $statement = $this->runQuery($sql, $params);
        $result = $statement->fetch();
        return $result ?: null;
    }

    /**
     * 
     * @param string $sql 
     * @param array $params 
     * @return array 
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->runQuery($sql, $params);
        return $statement->fetchAll();
    }

    /**
     * 
     * @return string 
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
     * 
     * @return string 
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
     * 
     * @return string 
     */
    protected function getSqliteDsn(): string
    {
        return sprintf(
            "sqlite:%s",
            $this->config['path']
        );
    }

    /**
     * 
     * @return string 
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
