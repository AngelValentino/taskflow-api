<?php

namespace Api\Controllers;

use Api\Gateways\UserGateway;
use Api\Services\Auth;
use Api\Services\AuthFormValidation;
use Api\Services\Mailer;
use Api\Services\Responder;

class RecoverPasswordController {
    public function __construct(
        private string $client_url,
        private UserGateway $user_gateway,
        private Auth $auth,
        private Mailer $mailer,
        private AuthFormValidation $auth_form_validation
    ) {
        
    }

    public function processRequest(string $method): void {
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $email = $data['email'];

            if (!isset($email)) {
                Responder::respondBadRequest('Email is required');
                return;
            }

            $email_error = $this->auth_form_validation->getEmailValidationError(trim($email), false);
            if ($email_error) {
                Responder::respondUnprocessableEntity(['email_error' => $email_error]);
                return;
            }

            $user = $this->user_gateway->getByEmail($email);
            
            // Add a small random delay if no user exists, to prevent timing attacks
            if (!$user) {
                usleep(rand(80000, 725000));
            }

            // Always return the same response to avoid leaking user existence
            echo json_encode(['message' => "If the account exists, you will receive an email shortly. If you don't see it in your inbox, please check your spam or junk folder. If the email is there, kindly mark it as \"Not Spam\" to ensure you receive future messages from us."]);

            if ($user) {
                $reset_token = $this->auth->getRecoverPasswordToken($user);
                $reset_link = $this->client_url . "/reset-password?token=$reset_token";
                $this->mailer->sendResetEmail($email, $reset_link);
            }
        }
        else {
            Responder::respondMethodNotAllowed('POST');
        }
    }
}