<?php

namespace Api\Gateways;

use PDO;
use Api\Database\Database;

class RefreshTokenGateway {
    private PDO $conn;

    public function __construct(Database $database, private string $key) {
        $this->conn = $database->getConnection();
    }

    public function create(string $token, int $expiry): bool {
        $hash = hash_hmac('sha256', $token, $this->key);

        $sql = "INSERT INTO refresh_tokens (token_hash, expires_at)
                VALUES (:token_hash, :expires_at)";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(':token_hash', $hash, PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $expiry, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function delete(string $token): int {
        $hash = hash_hmac('sha256', $token, $this->key);
        
        $sql = "DELETE FROM refresh_tokens
                WHERE token_hash = :token_hash";
       
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':token_hash', $hash, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    public function getByToken(string $token): array | false {
        $hash = hash_hmac('sha256', $token, $this->key);
        
        $sql = "SELECT *
                FROM refresh_tokens
                WHERE token_hash = :token_hash";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':token_hash', $hash, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteExpired(): int {
        $sql = "DELETE FROM refresh_tokens
                WHERE expires_at < UNIX_TIMESTAMP()";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
}