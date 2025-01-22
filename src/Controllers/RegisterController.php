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

            $this->gateway->createUser($_POST['username'], $_POST['email'], $_POST['password']);
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
        else {
            return null;
        }
    }

    private function getEmailValidationErrors(string $email): ?string {
        if (empty($email)) {
            return 'Email is required';
        } 
        else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Email is not valid.';
        }
        else if (strlen($email) > 255) {
           return 'Email must be less than or equal 255 characters.';
        }
        else {
            return null;
        }
    }

    private function getPasswordValidationErrors(string $password): ?string {
        if (empty($password)) {
            return 'Password is required';
        }
        else if (strlen($password) < 8) {
            return 'Password must be at least 8 characters.';
        } 
        else if (strlen($password) > 255) {
            return 'Password must be less than or equal 255 characters.';
        } 
        else {
            return null;
        }
    }

    private function getValidationErrors(array $data): array {
        $errors = [
            'username' => $this->getUsernameValidationErrors($data['username']),
            'email' => $this->getEmailValidationErrors($data['email']),
            'password' => $this->getPasswordValidationErrors($data['password'])
        ];

        return array_filter($errors);
    }
}