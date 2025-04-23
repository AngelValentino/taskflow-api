<?php

namespace Api\Controllers;

use Api\Gateways\UserGateway;
use Api\Services\Mailer;
use Api\Services\Responder;

class RegisterController {
    public function __construct(
        private UserGateway $gateway,
        private Mailer $mailer
    ) {

    }

    public function processRequest(string $method): void {
        if ($method === 'POST') {
            $data = (array) json_decode(file_get_contents('php://input'), true);

            $errors = $this->getValidationErrors($data);

            if (!empty($errors)) {
                Responder::respondUnprocessableEntity($errors);
                return;
            }

            $this->gateway->create($data['username'], $data['email'], $data['password']);
            $this->mailer->sendWelcomeEmail($data['email'], $data['username']);

            Responder::respondCreated('User created.');
        } 
        else {
            Responder::respondMethodNotAllowed('POST');
        }
    }

    private function getUsernameValidationError(?string $username): ?string {
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

    private function getEmailValidationError(?string $email): ?string {
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

    private function getPasswordValidationError(?string $password): ?string {
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

    private function getRepeatedPasswordValidationError(?string $password, ?string $repeatedPassword): ?string {
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
            'username' => $this->getUsernameValidationError($data['username'] ?? null),
            'email' => $this->getEmailValidationError($data['email'] ?? null),
            'password' => $this->getPasswordValidationError($data['password'] ?? null),
            'repeated_password' => $this->getRepeatedPasswordValidationError($data['password'] ?? null, $data['repeated_password'] ?? null),
            'terms' => isset($data['terms']) ? null : 'You must accept terms and conditions in order to register.'
        ];

        return array_filter($errors);
    }
}