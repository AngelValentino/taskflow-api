<?php

namespace Api\Gateways;

use PDO;
use Api\Database\Database;

class UserGateway {
    private PDO $conn;

    public function __construct(Database $database) {
        $this->conn = $database->getConnection();
    }

    public function create(string $username, string $email, string $password): void {
        $sql = "INSERT INTO users (username, email, password_hash)
                VALUES (:username, :email, :password_hash)";
        
        $stmt = $this->conn->prepare($sql);

        $options = ['cost' => 12];
        $password_hash = password_hash($password, PASSWORD_BCRYPT, $options);

        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $password_hash, PDO::PARAM_STR);
    
        $stmt->execute();
    }

    public function updatePassword(int $user_id, string $password): void {
        $sql = "UPDATE users 
                SET password_hash = :password_hash
                WHERE id = :user_id";

        $stmt = $this->conn->prepare($sql);

        $options = ['cost' => 12];
        $password_hash = password_hash($password, PASSWORD_BCRYPT, $options);

        $stmt->bindValue(':password_hash', $password_hash, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);

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

    public function getById(int $id): array | false {
        $sql = "SELECT * FROM 
                users WHERE id = :id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByEmail(string $email): array | false {
        $sql = "SELECT * FROM
                users WHERE email = :email";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}