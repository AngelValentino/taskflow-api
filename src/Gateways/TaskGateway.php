<?php

namespace Api\Gateways;

use PDO;
use Api\Database\Database;

class TaskGateway {
    private PDO $conn;

    public function __construct(Database $database) {
        $this->conn = $database->getConnection();
    }

    public function getAllForUser(int $user_id): array {
        $sql = "SELECT * FROM tasks
                WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $data = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['is_completed'] = (bool) $row['is_completed'];
            $data[] = $row;
        }

        return $data;
    }

    public function getForUser(int $user_id, string $task_id): array | false {
        $sql = "SELECT * FROM tasks 
                WHERE id = :task_id AND user_id = :user_id";
       
        $stmt = $this->conn->prepare($sql);  
        $stmt->bindValue(':task_id', $task_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            $data['is_completed'] = (bool) $data['is_completed'];
        }

        return $data;
    }

    public function createForUser(int $user_id, array $data): string {
        $sql = "INSERT INTO tasks (user_id, title, due_date, `description`, `is_completed`) 
                VALUES (:user_id, :title, :due_date, :description, :is_completed)";
    
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':due_date', $data['due_date'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'], PDO::PARAM_STR);
        $stmt->bindValue(':is_completed', $data['is_completed'] ?? false, PDO::PARAM_BOOL);
        $stmt->execute();

        return $this->conn->lastInsertId();
    }

    public function updateForUser(int $user_id, string $task_id, array $data): int {
        $fields = [
            'title = :title', 
            'description = :description', 
            'due_date = :due_date'
        ];

        if (!empty($data['is_completed'])) {
            $fields[] = 'is_completed = :is_completed';
        }

        $setClause = implode(', ', $fields);

        $sql = "UPDATE tasks 
                SET $setClause 
                WHERE id = :task_id AND user_id = :user_id";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(':task_id', $task_id, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'], PDO::PARAM_STR);
        $stmt->bindValue(':due_date', $data['due_date'], PDO::PARAM_STR);

        if (!empty($data['is_completed'])) {
            $stmt->bindValue(':is_completed', $data['is_completed'], PDO::PARAM_BOOL);
        }

        $stmt->execute();

        return $stmt->rowCount();
    }

    public function deleteForUser(int $user_id, string $task_id): int {
        $sql = "DELETE FROM `tasks` 
                WHERE id = :task_id AND user_id = :user_id";  
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':task_id', $task_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }
}