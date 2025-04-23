<?php

namespace Api\Controllers;

use Api\Gateways\TaskGateway;
use Api\Services\Responder;
use DateTime;

class TaskController {
    public function __construct(
        private int $user_id,
        private TaskGateway $gateway
    ) { 

    }

    public function processRequest(string $method, ?string $task_id): void {
        if ($task_id === null) {
            if ($method === 'GET') {
                $is_completed = $_GET['completed'] ?? null;
                $is_counter = $is_counter = filter_var($_GET['counter'] ?? null, FILTER_VALIDATE_BOOLEAN);
                $search_value = isset($_GET['title']) ? trim($_GET['title']) : null;

                if ($is_completed !== null) {
                    $is_completed = filter_var($_GET['completed'] ?? null, FILTER_VALIDATE_BOOLEAN);
                }

                if ($is_counter) {
                    echo json_encode($this->gateway->getUserTaskCount($this->user_id, $is_completed));
                }
                else {
                    echo json_encode($this->gateway->getAllForUser($this->user_id, $is_completed, $search_value));
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
                    Responder::respondUnprocessableEntity($errors);
                    return;
                }

                $task_id = $this->gateway->createForUser($this->user_id, $data);
                Responder::respondCreated("Task with ID: $task_id created");
            }
            else if ($method === 'DELETE') {
                $is_completed = $_GET['completed'] ?? null;
                if ($is_completed !== null) {
                    $is_completed = filter_var($_GET['completed'] ?? null, FILTER_VALIDATE_BOOLEAN);
                } 

                $this->gateway->deleteAllForUser($this->user_id, $is_completed);
                Responder::respondNoContent();
            }
            else {
                Responder::respondMethodNotAllowed('GET, POST, DELETE');
            }
        } 
        else {
            $task = $this->gateway->getForUser($this->user_id, $task_id);

            if ($task === false) {
                Responder::respondNotFound("Task with ID: $task_id not found");
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

                    $data['is_completed'] = isset($data['is_completed']) && $data['is_completed'] ? true : null;
                    $data['completed_at'] = isset($data['is_completed']) && $data['is_completed'] ? date('Y-m-d') : null;

                    $errors = $this->getUpdateValidationErrors($data);
                   
                    if (!empty($errors)) {
                        Responder::respondUnprocessableEntity($errors);
                        return;
                    }

                    $this->gateway->updateForUser($this->user_id, $task_id, $data);
                    Responder::respondNoContent();
                    break;
                case 'DELETE':
                    $this->gateway->deleteForUser($this->user_id, $task_id);
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