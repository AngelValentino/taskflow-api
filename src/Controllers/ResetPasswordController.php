<?php

namespace Api\Controllers;

use Api\Gateways\UserGateway;
use Api\Services\Auth;
use Api\Services\Mailer;

class ResetPasswordController {
    public function __construct(
        private UserGateway $user_gateway,
        private Auth $auth,
        private Mailer $mailer
    ) {

    }

    public function processRequest(string $method) {
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['token'])) {
                $this->respondBadRequest('Reset password token is required.');
                return;
            }

            $errors = $this->getValidationErrors($data);
            
            if (!empty($errors)) {
                $this->respondUnprocessableEntity($errors);
                return;
            }

            if (!$this->auth->authenticateAccessToken(false, $data['token'], 'reset')) return;
            $user_id = $this->auth->getUserId();
            $user_email = $this->auth->getUserEmail();
            
            $this->user_gateway->updatePassword($user_id, $data['password']);
            $this->mailer->sendPasswordChangedConfirmation($user_email);
            http_response_code(204);
        }
        else {
            $this->respondMethodNotAllowed('POST');
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

    private function respondBadRequest(string $message): void {
        http_response_code(400);
        echo json_encode(['message' => $message]);
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
            'password' => $this->getPasswordValidationError($data['password'] ?? null),
            'repeated_password' => $this->getRepeatedPasswordValidationError($data['password'] ?? null, $data['repeated_password'] ?? null)
        ];

        return array_filter($errors);
    }
}