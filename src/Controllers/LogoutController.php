<?php

namespace Api\Controllers;

use Api\Gateways\RefreshTokenGateway;
use Api\Gateways\UserGateway;
use Api\Services\Auth;

class LogoutController {
    public function __construct(
        private UserGateway $user_gateway,
        private RefreshTokenGateway $refresh_token_gateway,
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
            
            // We avoid checking if the refresh token is whitelisted to prevent locking out the user on the client side.
            // If a refresh token request is aborted, the client may end up with an outdated token that is no longer in the whitelist.

            $user = $this->user_gateway->getById($user_id);

            if ($user === false) {
                $this->respondUnauthorized();
                return;
            }

            $this->refresh_token_gateway->delete($data['token']);
        }
        else {
            $this->respondMethodNotAllowed('POST');
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