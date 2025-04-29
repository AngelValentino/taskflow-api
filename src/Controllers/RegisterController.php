<?php

namespace Api\Controllers;

use Api\Gateways\UserGateway;
use Api\Services\AuthFormValidation;
use Api\Services\Mailer;
use Api\Services\Responder;

class RegisterController {
    public function __construct(
        private UserGateway $user_gateway,
        private Mailer $mailer,
        private AuthFormValidation $auth_form_validation
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

            $this->user_gateway->create($data['username'], $data['email'], $data['password']);
            $this->mailer->sendWelcomeEmail($data['email'], $data['username']);

            Responder::respondCreated('User created.');
        } 
        else {
            Responder::respondMethodNotAllowed('POST');
        }
    }

    private function getValidationErrors(array $data): array {
        $errors = [
            'username' => $this->auth_form_validation->getUsernameValidationError($data['username'] ?? null),
            'email' => $this->auth_form_validation->getEmailValidationError($data['email'] ?? null),
            'password' => $this->auth_form_validation->getPasswordValidationError($data['password'] ?? null),
            'repeated_password' => $this->auth_form_validation->getRepeatedPasswordValidationError($data['password'] ?? null, $data['repeated_password'] ?? null),
            'terms' => isset($data['terms']) ? null : 'You must accept terms and conditions in order to register.'
        ];

        return array_filter($errors);
    }
}