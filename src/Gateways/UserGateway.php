<?php

namespace Api\Gateways;

use PDO;
use Api\Database\Database;

class UserGateway {
    private PDO $conn;

    public function __construct(Database $database) {
        $this->conn = $database->getConnection();
    }

    public function createUser(string $username, string $email, string $password): void {
        $sql = "INSERT INTO users (username, email, password_hash)
                VALUES (:username, :email, :password_hash)";
        
        $stmt = $this->conn->prepare($sql);

        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $password_hash, PDO::PARAM_STR);
    
        $stmt->execute();
    }

    public function getByUsername(string $username): array | false {
        $sql = "SELECT * FROM users 
                WHERE username = :username";
       
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}