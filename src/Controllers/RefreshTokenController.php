<?php

namespace Api\Controllers;

use Api\Gateways\RefreshTokenGateway;
use Api\Gateways\UserGateway;
use Api\Services\Auth;

class RefreshTokenController {
    public function __construct(
        private UserGateway $user_gateway,
        private RefreshTokenGateway $gateway,
        private Auth $auth
    ) {

    }

    public function processRequest(string $method): void {
        if ($method === 'POST') {
            $data = (array) json_decode(file_get_contents('php://input'), true);

            if (empty($data['token'])) {
                $this->respondBadRequest('Missing token.');
                return;
            }

            if (!$this->auth->authenticateAccessToken(false, $data['token'], 'refresh')) return;
            $user_id = $this->auth->getUserId();

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
            $access_token = $this->auth->getAccessToken($user);

            // Update db with the newly created refresh token
            $this->gateway->delete($data['token']);
            $this->gateway->create($access_token['refresh_token'], $access_token['refresh_token_expiry']);

            // Send JSON
            echo json_encode([
                'access_token' => $access_token['access_token'],
                'refresh_token' => $access_token['refresh_token'],
                'username' => htmlspecialchars($user['username'])
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