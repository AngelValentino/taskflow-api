<?php

namespace Api\Controllers;

use Api\Gateways\UserGateway;
use Api\Services\JWTCodec;

class LoginController {
    public function __construct(
        private UserGateway $gateway,
        private JWTCodec $codec
    ) {
        
    }

    public function processRequest(string $method): void {
        if ($method === 'POST') {
            $data = (array) json_decode(file_get_contents('php://input'), true);

            if (empty($data['username']) || empty($data['password'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Missing login credentials.']);
                return;
            }
    
            $user = $this->gateway->getByUsername($data['username']);
            $error = $this->getUserValidationErrorMessage($data, $user);

            if (isset($error)) {
                $this->respondUnauthorized($error);
                return;
            }

            // Genereate access and refresh token

            $payload = [
                'sub' => $user['id'],
                'username' => $user['username'],
                'exp' => time() + 300 # 5 minutes
            ];

            $access_token = $this->codec->encode($payload);

            $refresh_token_expiry = time() + 432000; # 5 days
            $refresh_token = $this->codec->encode([
                'sub' => $user['id'],
                'exp' => $refresh_token_expiry
            ]);

            echo json_encode([
                'access_token' => $access_token,
                'refresh_token' => $refresh_token
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
            return 'User does not exist';
        }

        if (!password_verify($data['password'], $user['password_hash'])) {
            return 'Invalid password';
        }

        return null;
    }
}