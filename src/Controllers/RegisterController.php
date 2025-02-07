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
            $errors = $this->getValidationErrors($_POST);

            if (!empty($errors)) {
                $this->respondUnprocessableEntity($errors);
                return;
            }

            $this->gateway->create($_POST['username'], $_POST['email'], $_POST['password']);
            
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

    private function getUsernameValidationErrors(string $username): ?string {
        if (empty($username)) {
            return 'Username is required.';
        } 
        else if (strlen($username) > 32) {
            return 'Username must be less than or equal 32 characters.';
        }
        else if ($this->gateway->getByUsername($username)) {
            return 'Username is already taken, please try another one.';
        }
        else {
            return null;
        }
    }

    private function getEmailValidationErrors(string $email): ?string {
        if (empty($email)) {
            return 'Email is required.';
        } 
        else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Email is not valid.';
        }
        else if (strlen($email) > 255) {
           return 'Email must be less than or equal 255 characters.';
        }
        else if ($this->gateway->getByEmail($email)) {
            return 'Email is already taken, please try another one.';
        }
        else {
            return null;
        }
    }

    private function getPasswordValidationErrors(string $password, string $repeatedPassword): ?string {
        if (empty($password)) {
            return 'Password is required.';
        }
        else if (strlen($password) < 8) {
            return 'Password must be at least 8 characters.';
        } 
        else if (strlen($password) > 255) {
            return 'Password must be less than or equal 255 characters.';
        } 
        else if ($password !== $repeatedPassword) {
            return 'Passwords do not match.';
        }
        else {
            return null;
        }
    }

    private function getValidationErrors(array $data): array {
        $errors = [
            'username' => $this->getUsernameValidationErrors($data['username']),
            'email' => $this->getEmailValidationErrors($data['email']),
            'password' => $this->getPasswordValidationErrors($data['password'], $data['repeated-password'])
        ];

        return array_filter($errors);
    }
}