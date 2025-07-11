<?php

namespace Api\Controllers;

use Api\Gateways\TaskGateway;
use Api\Services\Responder;
use DateTime;

class TaskController {
    public function __construct(
        private int $user_id,
        private TaskGateway $task_gateway
    ) { 

    }

    public function processRequest(string $method, ?string $task_id): void {
        if ($task_id === null) {
            if ($method === 'GET') {
                $is_completed = $_GET['completed'] ?? null;
                $is_counter = filter_var($_GET['counter'] ?? null, FILTER_VALIDATE_BOOLEAN);
                $search_value = isset($_GET['title']) ? trim($_GET['title']) : null;

                if ($is_completed !== null) {
                    $is_completed = filter_var($_GET['completed'] ?? null, FILTER_VALIDATE_BOOLEAN);
                }

                if ($is_counter) {
                    echo json_encode($this->task_gateway->getUserTaskCount($this->user_id, $is_completed));
                }
                else {
                    $tasks = $this->task_gateway->getAllForUser($this->user_id, $is_completed, $search_value);

                    $sanitizedTasks = array_map(function($task) {
                        return array_map(function($value) {
                            return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
                        }, $task);
                    }, $tasks);
    
                    echo json_encode($sanitizedTasks);
                }
            }
            else if ($method === 'POST') {
                $data = (array) json_decode(file_get_contents('php://input'), true);
                
                $fields_to_trim = ['title', 'due_date', 'description'];

                foreach ($fields_to_trim as $field) {
                    $data[$field] = trim($data[$field] ?? '');
                }

                $errors = $this->getValidationErrors($data);
               
                if (!empty($errors)) {
                    Responder::respondUnprocessableEntity($errors);
                    return;
                }

                $task_count = $this->task_gateway->getUserTaskCount($this->user_id, false);

                if ($task_count >= 100) {
                    Responder::respondConflict('Cannot create new tasks, the active task limit of 100 has been reached.');
                    return;
                }

                $task_id = $this->task_gateway->createForUser($this->user_id, $data);
                Responder::respondCreated('Task created.');
            }
            else if ($method === 'DELETE') {
                $is_completed = $_GET['completed'] ?? null;
                if ($is_completed !== null) {
                    $is_completed = filter_var($_GET['completed'] ?? null, FILTER_VALIDATE_BOOLEAN);
                } 

                $this->task_gateway->deleteAllForUser($this->user_id, $is_completed);
                Responder::respondNoContent();
            }
            else {
                Responder::respondMethodNotAllowed('GET, POST, DELETE');
            }
        } 
        else {
            $task = $this->task_gateway->getForUser($this->user_id, $task_id);

            if ($task === false) {
                Responder::respondNotFound('Task not found.');
                return;
            }

            switch ($method) {
                case 'GET':
                    $sanitizedTask = array_map(function($value) {
                        return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
                    }, $task);
                    
                    echo json_encode($sanitizedTask);
                    
                    break;
                
                case 'PATCH':
                    $data = (array) json_decode(file_get_contents('php://input'), true);

                    $data['title'] = array_key_exists('title', $data) ? trim($data['title']) : null;
                    $data['due_date'] = array_key_exists('due_date', $data) ? trim($data['due_date']) : null;
                    $data['description'] = array_key_exists('description', $data) ? trim($data['description']) : null;

                    $data['is_completed'] = isset($data['is_completed']) && $data['is_completed'] ? true : null;
                    $data['completed_at'] = isset($data['is_completed']) && $data['is_completed'] ? date('Y-m-d') : null;

                    $errors = $this->getUpdateValidationErrors($data);
                   
                    if (!empty($errors)) {
                        Responder::respondUnprocessableEntity($errors);
                        return;
                    }

                    $this->task_gateway->updateForUser($this->user_id, $task_id, $data);
                    Responder::respondNoContent();
                    
                    break;
                
                case 'DELETE':
                    $this->task_gateway->deleteForUser($this->user_id, $task_id);
                    Responder::respondNoContent();
                    
                    break;
                
                default:
                    Responder::respondMethodNotAllowed('GET, PATCH, DELETE');
            }
        }
    }

    private function validateDueDate(string $due_date): bool {
        $date = DateTime::createFromFormat('Y-m-d', $due_date);
        return $date && $date->format('Y-m-d') === $due_date;
    }

    private function getUpdateValidationErrors(array $data): array {
        $errors = [];

        if ($data['title'] !== null) {
            if (empty($data['title'])) {
                $errors['title'] = 'Title field is required.';
            }
            else if (strlen($data['title']) > 75) {
                $errors['title'] = 'Title must be less than or equal 75 characters.';
            }
        }
    
        if ($data['due_date'] !== null) {
            if (empty($data['due_date'])) {
                $errors['due_date'] = 'Due date field is required.';
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
            $errors['title'] = 'Title field is required.';
        }
        else if (strlen($data['title']) > 75) {
            $errors['title'] = 'Title must be less than or equal 75 characters.';
        }

        if (empty($data['due_date'])) {
            $errors['due_date'] = 'Due date field is required.';
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