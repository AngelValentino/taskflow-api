<?php

namespace Api\Controllers;

use Api\Gateways\UserGateway;
use Api\Services\Auth;
use Api\Services\AuthFormValidation;
use Api\Services\Mailer;
use Api\Services\Responder;

class ResetPasswordController {
    public function __construct(
        private UserGateway $user_gateway,
        private Auth $auth,
        private Mailer $mailer,
        private AuthFormValidation $auth_form_validation
    ) {

    }

    public function processRequest(string $method) {
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['token'])) {
                Responder::respondBadRequest('Reset password token is required.');
                return;
            }

            $errors = $this->getValidationErrors($data);
            
            if (!empty($errors)) {
                Responder::respondUnprocessableEntity($errors);
                return;
            }

            if (!$this->auth->authenticateAccessToken(false, $data['token'], 'reset')) return;
            $user_id = $this->auth->getUserId();
            $user_email = $this->auth->getUserEmail();
            
            $this->user_gateway->updatePassword($user_id, $data['password']);
            $this->mailer->sendPasswordChangedConfirmation($user_email);
            Responder::respondNoContent();
        }
        else {
            Responder::respondMethodNotAllowed('POST');
        }
    }

    private function getValidationErrors(array $data): array {
        $errors = [
            'password' => $this->auth_form_validation->getPasswordValidationError($data['password'] ?? null),
            'repeated_password' => $this->auth_form_validation->getRepeatedPasswordValidationError($data['password'] ?? null, $data['repeated_password'] ?? null)
        ];

        return array_filter($errors);
    }
}