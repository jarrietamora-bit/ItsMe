<?php
// Database connection singleton using PDO
class Database {
    private static ?PDO $instance = null;

    public static function connect(): PDO {
        if (self::$instance === null) {
            $cfg = require __DIR__ . '/config.php';
            $dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            self::$instance = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], $options);
        }
        return self::$instance;
    }

    public static function pdo(): PDO {
        return self::connect();
    }
}

function db(): PDO {
    return Database::connect();
}
