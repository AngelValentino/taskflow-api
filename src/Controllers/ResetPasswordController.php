<?php

namespace Api\Controllers;

use Api\Gateways\UserGateway;
use Api\Services\Auth;
use Api\Services\Mailer;
use Api\Services\Responder;

class ResetPasswordController {
    public function __construct(
        private UserGateway $user_gateway,
        private Auth $auth,
        private Mailer $mailer,
        private Responder $responder
    ) {

    }

    public function processRequest(string $method) {
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['token'])) {
                $this->responder->respondBadRequest('Reset password token is required.');
                return;
            }

            $errors = $this->getValidationErrors($data);
            
            if (!empty($errors)) {
                $this->responder->respondUnprocessableEntity($errors);
                return;
            }

            if (!$this->auth->authenticateAccessToken(false, $data['token'], 'reset')) return;
            $user_id = $this->auth->getUserId();
            $user_email = $this->auth->getUserEmail();
            
            $this->user_gateway->updatePassword($user_id, $data['password']);
            $this->mailer->sendPasswordChangedConfirmation($user_email);
            $this->responder->respondNoContent();
        }
        else {
            $this->responder->respondMethodNotAllowed('POST');
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
            'password' => $this->getPasswordValidationError($data['password'] ?? null),
            'repeated_password' => $this->getRepeatedPasswordValidationError($data['password'] ?? null, $data['repeated_password'] ?? null)
        ];

        return array_filter($errors);
    }
}