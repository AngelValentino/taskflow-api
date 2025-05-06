<?php

namespace Api\Controllers;

use Api\Gateways\UserGateway;
use Api\Services\AuthFormValidation;
use Api\Services\Mailer;
use Api\Services\Responder;
use Api\Services\ErrorHandler;

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
            
            $fields_to_trim = ['username', 'email', 'password', 'repeated_password'];

            foreach ($fields_to_trim as $field) {
                $data[$field] = trim($data[$field] ?? '');
            }

            $errors = $this->getValidationErrors($data);

            if (!empty($errors)) {
                Responder::respondUnprocessableEntity($errors);
                return;
            }

            $this->user_gateway->create($data['username'], $data['email'], $data['password']);
            ErrorHandler::logAudit("USER_CREATED -> IP {$_SERVER['REMOTE_ADDR']} | Email: {$data['email']} | Username: {$data['username']}");
            
            $this->mailer->sendWelcomeEmail($data['email'], $data['username']);
            
            Responder::respondCreated('User created.');
        } 
        else {
            Responder::respondMethodNotAllowed('POST');
        }
    }

    private function getValidationErrors(array $data): array {
        $errors = [
            'username' => $this->auth_form_validation->getUsernameValidationError($data['username']),
            'email' => $this->auth_form_validation->getEmailValidationError($data['email']),
            'password' => $this->auth_form_validation->getPasswordValidationError($data['password']),
            'repeated_password' => $this->auth_form_validation->getRepeatedPasswordValidationError($data['password'], $data['repeated_password']),
            'terms' => !empty($data['terms']) ? null : 'You must accept terms and conditions in order to register.'
        ];

        return array_filter($errors);
    }
}