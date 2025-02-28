<?php

namespace Api\Controllers;

use Api\Gateways\TaskGateway;
use DateTime;

class TaskController {
    public function __construct(
        private TaskGateway $gateway,
        private int $user_id
    ) { 

    }

    public function processRequest(string $method, ?string $task_id): void {
        if ($task_id === null) {
            if ($method === 'GET') {
                $whitelist_columns = ['due_date'];
                $sort_by_column = $_GET['sort_by'] ?? null;
                $order = isset($_GET['order']) ? strtoupper($_GET['order'] ?? 'ASC') : null;
                $is_completed = $_GET['completed'] ?? null;
                $is_counter = $is_counter = filter_var($_GET['counter'] ?? null, FILTER_VALIDATE_BOOLEAN);
                
                if ($is_completed !== null) {
                    $is_completed = filter_var($_GET['completed'] ?? null, FILTER_VALIDATE_BOOLEAN);
                } 

                if ($sort_by_column && !in_array($sort_by_column, $whitelist_columns)) {
                    $this->respondBadRequest('Invalid sort column parameter.');
                    return;
                }
        
                if ($order && $order !== 'ASC' && $order !== 'DESC') {
                    $this->respondBadRequest("Invalid order parameter, must be 'ASC' or 'DESC'."); 
                    return;
                }

                if ($is_counter) {
                    echo json_encode($this->gateway->getUserTaskCount($this->user_id, $is_completed));
                } 
                else {
                    echo json_encode($this->gateway->getAllForUser($this->user_id, $sort_by_column, $order, $is_completed));
                }   
            }
            else if ($method === 'POST') {
                $data = (array) json_decode(file_get_contents('php://input'), true);
                
                // Trim inputs
                $data['title'] = trim($data['title'] ?? '');
                $data['due_date'] = trim($data['due_date'] ?? '');
                $data['description'] = trim($data['description'] ?? '');

                $errors = $this->getValidationErrors($data);
               
                if (!empty($errors)) {
                    $this->respondUnprocessableEntity($errors);
                    return;
                }

                $task_id = $this->gateway->createForUser($this->user_id, $data);
                $this->respondCreated($task_id);
            }
            else if ($method === 'DELETE') {
                $this->gateway->deleteAllForUser($this->user_id);
                http_response_code(204);
            }
            else {
                $this->respondMethodNotAllowed('GET, POST, DELETE');
            }
        } 
        else {
            $task = $this->gateway->getForUser($this->user_id, $task_id);

            if ($task === false) {
                $this->respondNotFound($task_id);
                return;
            }

            switch ($method) {
                case 'GET':
                    echo json_encode($task);
                    break;
                case 'PATCH':
                    $data = (array) json_decode(file_get_contents('php://input'), true);

                    // Trim inputs
                    $data['title'] = array_key_exists('title', $data) ? trim($data['title']) : null;
                    $data['due_date'] = array_key_exists('due_date', $data) ? trim($data['due_date']) : null;
                    $data['description'] = array_key_exists('description', $data) ? trim($data['description']) : null;

                    $errors = $this->getUpdateValidationErrors($data);
                   
                    if (!empty($errors)) {
                        $this->respondUnprocessableEntity($errors);
                        return;
                    }

                    $rows = $this->gateway->updateForUser($this->user_id, $task_id, $data);
                    echo json_encode(['message' => $rows]);
                    break;
                case 'DELETE':
                    $this->gateway->deleteForUser($this->user_id, $task_id);
                    http_response_code(204);
                    break;
                default:
                    $this->respondMethodNotAllowed('GET, PATCH, DELETE');
            }
        }
    }

    private function respondUnprocessableEntity(array $errors): void {
        http_response_code(422);
        echo json_encode(['errors' => $errors]);
    }

    private function respondMethodNotAllowed(string $allowed_methods): void {
        http_response_code(405);
        header("Allow: $allowed_methods");
    }

    private function respondNotFound(string $task_id): void {
        http_response_code(404);
        echo json_encode(['message' => "Task with ID $task_id not found"]);
    }

    private function respondBadRequest(string $message): void {
        http_response_code(400);
        echo json_encode(['message' => $message]);
    }

    private function respondCreated(string $task_id): void {
        http_response_code(201);
        echo json_encode(['message' => 'Task Created', 'id' => $task_id]);
    }

    private function validateDueDate(string $due_date): bool {
        $date = DateTime::createFromFormat('Y-m-d', $due_date);
        return $date && $date->format('Y-m-d') === $due_date;
    }

    private function getUpdateValidationErrors(array $data): array {
        $errors = [];

        if ($data['title'] !== null) {
            if (empty($data['title'])) {
                $errors['title'] = 'Title field is required';
            }
            else if (strlen($data['title']) > 75) {
                $errors['title'] = 'Title must be less than or equal 75 characters.';
            }
        }
    
        if ($data['due_date'] !== null) {
            if (empty($data['due_date'])) {
                $errors['due_date'] = 'Due date field is required';
            }
            else if (!$this->validateDueDate($data['due_date'])) {
                $errors['due_date'] = 'Due date must be in YYYY-MM-DD format and also be valid.';
            }
        }
    
        if ($data['description'] !== null) {
            if (strlen($data['description']) > 500) {
                $errors['description'] = 'Description must be less than or equal 500 characters.';
            }
        }

        return $errors;
    }

    private function getValidationErrors(array $data): array {
        $errors = [];

        if (empty($data['title'])) {
            $errors['title'] = 'Title field is required';
        }
        else if (strlen($data['title']) > 75) {
            $errors['title'] = 'Title must be less than or equal 75 characters.';
        }

        if (empty($data['due_date'])) {
            $errors['due_date'] = 'Due date field is required';
        }
        else if (!$this->validateDueDate($data['due_date'])) {
            $errors['due_date'] = 'Due date must be in YYYY-MM-DD format and also be valid.';
        }

        if (!empty($data['description']) && strlen($data['description']) > 500) {
            $errors['description'] = 'Description must be less than or equal 500 characters.';
        }

        return $errors;
    }
}