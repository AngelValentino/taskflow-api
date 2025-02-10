<?php

namespace Api\Controllers;

use Api\Gateways\RefreshTokenGateway;
use Api\Gateways\UserGateway;
use Api\Services\Auth;

class LoginController {
    public function __construct(
        private UserGateway $gateway,
        private RefreshTokenGateway $refresh_token_gateway,
        private Auth $auth
    ) {
        
    }

    public function processRequest(string $method): void {
        if ($method === 'POST') {
            $data = (array) json_decode(file_get_contents('php://input'), true);

            if (empty($data['email']) || empty($data['password'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Missing login credentials.']);
                return;
            }
    
            $user = $this->gateway->getByEmail($data['email']);
            $error = $this->getUserValidationErrorMessage($data, $user);

            if (isset($error)) {
                $this->respondUnauthorized($error);
                return;
            }

            // Genereate access and refresh token
            $access_token = $this->auth->getAccessToken($user);

            // Store the refresh token in the db
            $this->refresh_token_gateway->create($access_token['refresh_token'], $access_token['refresh_token_expiry']);

            // Send JSON
            echo json_encode([
                'access_token' => $access_token['access_token'],
                'refresh_token' => $access_token['refresh_token'],
                'username' => $user['username']
            ]);
        } 
        else {
            $this->respondMethodNotAllowed('POST');
        }
    }

    private function respondMethodNotAllowed(string $allowed_methods): void {
        http_response_code(405);
        header("Allow: $allowed_methods");
    }

    private function respondUnauthorized(string $error): void {
        http_response_code(401);
        echo json_encode(['message' => $error]);
    }

    private function getUserValidationErrorMessage(array $data, array | false $user): ?string {
        if ($user === false) {
            return 'User does not exist.';
        }

        if (!password_verify($data['password'], $user['password_hash'])) {
            return 'Invalid password.';
        }

        return null;
    }
}