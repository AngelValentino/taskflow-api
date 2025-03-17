<?php

namespace Api\Controllers;

use Api\Gateways\UserGateway;

class RegisterController {
    public function __construct(
        private UserGateway $gateway,
    ) {

    }

    public function processRequest(string $method): void {
        if ($method === 'POST') {
            $data = (array) json_decode(file_get_contents('php://input'), true);

            $errors = $this->getValidationErrors($data);

            if (!empty($errors)) {
                $this->respondUnprocessableEntity($errors);
                return;
            }

            $this->gateway->create($data['username'], $data['email'], $data['password']);
            
            http_response_code(201);
            echo json_encode(['message' => 'User created successfully']);
        } 
        else {
            $this->respondMethodNotAllowed('POST');
        }
    }

    private function respondMethodNotAllowed(string $allowed_methods): void {
        http_response_code(405);
        header("Allow: $allowed_methods");
    }

    private function respondUnprocessableEntity(array $errors): void {
        http_response_code(422);
        echo json_encode(['errors' => $errors]);
    }

    private function getUsernameValidationError(string $username): ?string {
        if (empty($username)) {
            return 'Username is required.';
        } 
        else if (strlen($username) > 20) {
            return 'Username cannot exceed 20 characters.';
        }
        else if ($this->gateway->getByUsername($username)) {
            return 'Username is already taken, please try another one.';
        }
        else {
            return null;
        }
    }

    private function getEmailValidationError(string $email): ?string {
        if (empty($email)) {
            return 'Email address is required.';
        }
        else if (strlen($email) > 255) {
            return 'Email address cannot exceed 255 characters.';
        }
        else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Enter a valid email address.';
        }
        else if ($this->gateway->getByEmail($email)) {
            return 'Email address is already taken, please try another one.';
        }
        else {
            return null;
        }
    }

    private function getPasswordValidationError(string $password): ?string {
        if (empty($password)) {
            return 'Password is required.';
        }
        else if (strlen($password) < 8) {
            return 'Password must be at least 8 characters long.';
        } 
        else if (strlen($password) > 75) {
            return 'Password cannot exceed 72 characters.';
        } 
        else {
            return null;
        }
    }

    private function getRepeatedPasswordValidationError(string $password, string $repeatedPassword): ?string {
        if (empty($repeatedPassword)) {
            return 'You must confirm your password.';
        }
        else if ($password !== $repeatedPassword) {
            return 'The passwords entered do not match.';
        }
        return null;
    }

    private function getValidationErrors(array $data): array {
        $errors = [
            'username' => $this->getUsernameValidationError($data['username']),
            'email' => $this->getEmailValidationError($data['email']),
            'password' => $this->getPasswordValidationError($data['password']),
            'repeated_password' => $this->getRepeatedPasswordValidationError($data['password'], $data['repeated_password']),
            'terms' => isset($data['terms']) ? null : 'You must accept terms and conditions in order to register.'
        ];

        return array_filter($errors);
    }
}