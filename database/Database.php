<?php

class Database extends mysqli
{
    private static $instance = null;

    private function __construct()
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $name = getenv('DB_DATABASE') ?: 'banking';
        $port = getenv('DB_PORT') ?: 3306;

        parent::__construct($host, $user, $pass, $name, (int) $port);
        $this->set_charset('utf8mb4');
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __clone()
    {
        throw new RuntimeException('Database singleton cannot be cloned');
    }

    public function __wakeup()
    {
        throw new RuntimeException('Database singleton cannot be unserialized');
    }
}
