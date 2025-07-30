<?php

namespace App;

class Database
{
    private static $instance = null;
    private $conn;

    private function __construct()
    {
        require_once __DIR__ . '/config.php';
        try {
            $this->conn = new \PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'"
            ]);
        } catch (\PDOException $e) {
            // In a real app, you'd log this error, not echo it.
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->conn;
    }
}
