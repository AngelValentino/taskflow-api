<?php

namespace Api\Gateways;

use PDO;
use Api\Database\Database;

class TaskGateway {
    private PDO $conn;

    public function __construct(Database $database) {
        $this->conn = $database->getConnection();
    }

    public function getAllForUser(int $user_id, ?bool $is_completed, ?string $search_value): array {
        $sql = "SELECT * FROM tasks WHERE user_id = :user_id";

        if ($is_completed !== null) {
            $sql .= $is_completed ? " AND is_completed = 1" : " AND is_completed = 0";
        }

        if (!empty($search_value)) {
            $sql .= " AND title LIKE :search_value";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        if (!empty($search_value)) {
            $stmt->bindValue(':search_value', "%$search_value%", PDO::PARAM_STR);
        }
        $stmt->execute();
        
        $data = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['is_completed'] = (bool) $row['is_completed'];
            $data[] = $row;
        }

        return $data;
    }

    public function getUserTaskCount(int $user_id, ?bool $is_completed): int {
        $sql = "SELECT COUNT(*) as user_task_count FROM tasks WHERE user_id = :user_id";

        if ($is_completed !== null) {
            if ($is_completed === false) {
                $sql .= ' AND is_completed = 0';
            } 
            else if ($is_completed === true) {
                $sql .= ' AND is_completed = 1';
            }
        } 

        $stmt = $this->conn->prepare($sql);  
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data['user_task_count'];
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

        if ($data['is_completed'] !== null) {
            $fields['is_completed'] = [
                $data['is_completed'],
                PDO::PARAM_BOOL
            ];
        }

        if ($data['completed_at'] !== null) {
            $fields['completed_at'] = [
                $data['completed_at'],
                PDO::PARAM_STR
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

    public function deleteAllForUser(int $user_id, ?bool $is_completed): void {
        $sql = "DELETE FROM tasks
                WHERE user_id = :user_id";

        if ($is_completed !== null) {
            if ($is_completed === false) {
                $sql .= ' AND is_completed = 0';
            } 
            else if ($is_completed === true) {
                $sql .= ' AND is_completed = 1';
            }
        } 

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }
 }