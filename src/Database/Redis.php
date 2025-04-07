<?php

namespace Api\Database;

use Predis\Client;
use Exception;

class Redis {
    private ?Client $redis = null;

    public function __construct(
        private string $host,
        private string $port
    ) {
    }

    public function getConnection(): Client {
        if ($this->redis === null) {
            try {
                // Create a connection to Redis
                $this->redis = new Client([
                    'scheme' => 'tcp',
                    'host' => $this->host,
                    'port' => $this->port
                ]);
            } 
            catch (Exception $e) {
                throw new Exception("Failed to connect to Redis: " . $e->getMessage());
            }
        }

        return $this->redis;
    }
}