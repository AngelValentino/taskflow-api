<?php

namespace Api\Database;

use PDO;

class Database {
    private ?PDO $conn = null;

    public function __construct(
        private string $host,
        private string $name,
        private string $user,
        private string $password,
        private string $port,
        private ?string $sslCaPath = null,
        private ?string $sslCa = null
    ) {
    }

    public function getConnection(): PDO {
        if ($this->conn === null) {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->name};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false
            ];

            if ($_ENV['DB_SSL'] === 'true' && $_ENV['APP_ENV'] === 'development') {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $this->sslCaPath;
            }
            else if ($_ENV['DB_SSL'] === 'true' && $_ENV['APP_ENV'] === 'production') {
                // For production, write the stringified cert to a temp file
                $tempCertFile = tempnam(sys_get_temp_dir(), 'ssl_cert_');
                file_put_contents($tempCertFile, $this->sslCa); // Save stringified cert to temp file
                
                // Pass the file path to MySQL
                $options[PDO::MYSQL_ATTR_SSL_CA] = $tempCertFile;
            }
            // Establish connection
            $this->conn = new PDO($dsn, $this->user, $this->password, $options);
        }

        return $this->conn;
    }
}