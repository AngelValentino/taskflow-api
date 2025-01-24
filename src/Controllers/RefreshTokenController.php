<?php

namespace Api\Controllers;

use Exception;
use Api\Gateways\RefreshTokenGateway;
use Api\Gateways\UserGateway;
use Api\Services\JWTCodec;
use Api\Services\InvalidSignatureException;
use Api\Services\TokenExpiredException;

class RefreshTokenController {
    public function __construct(
        private RefreshTokenGateway $gateway,
        private UserGateway $user_gateway,
        private JWTCodec $codec
    ) {

    }

    public function processRequest(string $method): void {
        if ($method === 'POST') {
            $data = (array) json_decode(file_get_contents('php://input'), true);

            if (empty($data['token'])) {
                $this->respondBadRequest('Missing token.');
                return;
            }

            try {
                $payload = $this->codec->decode($data['token']);
            }    
            catch (InvalidSignatureException) {
                http_response_code(401);
                echo json_encode(['message' => 'invalid signature']);
                return;
            }
            catch (TokenExpiredException) {
                http_response_code(401);
                echo json_encode(['message' => 'token has expired']);
                return;
            }
            catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['message' => $e->getMessage()]);
                return;
            }

            $user_id = $payload['sub'];

            $refresh_token = $this->gateway->getByToken($data['token']);

            if ($refresh_token === false) {
                $this->respondBadRequest('Invalid token(not on whitelist).');
                return;
            }

            // validate user info

            $user = $this->user_gateway->getById($user_id);

            if ($user === false) {
                $this->respondUnauthorized();
                return;
            }

            // Regenerate access and refresh token
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

            // Update db with the newly created refresh token
            $this->gateway->delete($data['token']);
            $this->gateway->create($refresh_token, $refresh_token_expiry);

            // Send JSON
            echo json_encode([
                'access_token' => $access_token,
                'refresh_token' => $refresh_token
            ]);
        } 
        else {
            $this->respondMethodNotAllowed('POST');
            return;
        }
    }

    private function respondBadRequest(string $message): void {
        http_response_code(400);
        echo json_encode(['message' => $message]);
    }

    private function respondUnauthorized(): void {
        http_response_code(401);
        echo json_encode(['message' => 'Invalid authentication.']);
    }
    
    private function respondMethodNotAllowed(string $allowed_methods): void {
        http_response_code(405);
        header("Allow: $allowed_methods");
    }
}