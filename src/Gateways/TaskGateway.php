<?php

namespace Api\Gateways;

use PDO;
use Api\Database\Database;

class TaskGateway {
    private PDO $conn;

    public function __construct(Database $database) {
        $this->conn = $database->getConnection();
    }

    public function getAllForUser(int $user_id, ?string $sort_by_column = null, string $order = 'ASC'): array {
        $sql = "SELECT * FROM tasks
                WHERE user_id = :user_id";

        if ($sort_by_column) {
            $sql .= " ORDER BY $sort_by_column $order";
        }

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
        $description = !empty($data['description']) ? $data['description'] : null;
        
        $sql = "INSERT INTO tasks (user_id, title, due_date, `description`, `is_completed`) 
                VALUES (:user_id, :title, :due_date, :description, :is_completed)";
    
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':due_date', $data['due_date'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':is_completed', $data['is_completed'] ?? false, PDO::PARAM_BOOL);
        $stmt->execute();

        return $this->conn->lastInsertId();
    }

    public function updateForUser(int $user_id, string $task_id, array $data): int {
        $fields = [];

        if ($data['title'] !== null) {
            $fields['title'] = [
                $data['title'],
                PDO::PARAM_STR
            ];
        }

        $description = !empty($data['description']) ? $data['description'] : null;

        if ($data['description'] !== null) {
            $fields['description'] = [
                $description,
                $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR
            ];
        }

        if ($data['due_date'] !== null) {
            $fields['due_date'] = [
                $data['due_date'],
                PDO::PARAM_STR
            ];
        }

        if (array_key_exists('is_completed', $data)) {
            $fields['is_completed'] = [
                $data['is_completed'],
                PDO::PARAM_BOOL
            ];
        }

        if (empty($fields)) {
            return 0;
        } 
        else {
            $set_clauses = array_map(function($column) {
                return "`$column` = :$column";
            }, array_keys($fields));

            $sql = 'UPDATE `tasks`'
                . ' SET ' . implode(', ', $set_clauses)
                . ' WHERE id = :task_id'
                . ' AND user_id = :user_id';

            $stmt = $this->conn->prepare($sql);

            foreach ($fields as $column => $values) {
                $stmt->bindValue(":$column", $values[0], $values[1]);
            }
            $stmt->bindValue(':task_id', $task_id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);

            $stmt->execute();
        }

        return $stmt->rowCount();
    }

    public function deleteForUser(int $user_id, string $task_id): void {
        $sql = "DELETE FROM tasks 
                WHERE id = :task_id AND user_id = :user_id";  
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':task_id', $task_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteAllForUser(int $user_id): void {
        $sql = "DELETE FROM tasks
                WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }
 }