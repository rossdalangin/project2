<?php

namespace App;

use PDO;
use PDOStatement;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    private function __construct(array $config = [])
    {
        $host = $config['host'] ?? $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $port = $config['port'] ?? $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
        $dbname = $config['dbname'] ?? $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'lead_db';
        $user = $config['user'] ?? $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
        $pass = $config['pass'] ?? $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Re-throw or handle as needed
            throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
        }
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }

    public static function getInstance(array $config = []): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database($config);
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function fetch(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
