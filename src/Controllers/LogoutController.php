<?php

namespace Api\Controllers;

use Api\Gateways\RefreshTokenGateway;
use Api\Gateways\UserGateway;
use Api\Services\Auth;
use Api\Services\Responder;

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
            $data['token'] = trim($data['token'] ?? '');

            if (empty($data['token'])) {
                Responder::respondBadRequest('Missing token.');
                return;
            }

            if (!$this->auth->authenticateAccessToken(false, $data['token'], 'refresh')) return;
            $user_id = $this->auth->getUserId();
            
            // We avoid checking if the refresh token is whitelisted to prevent locking out the user on the client side.
            // If a refresh token request is aborted, the client may end up with an outdated token that is no longer in the whitelist.

            $user = $this->user_gateway->getById($user_id);

            if ($user === false) {
                Responder::respondUnauthorized('Invalid authentication.');
                return;
            }

            $this->refresh_token_gateway->delete($data['token']);
            Responder::respondNoContent();
        }
        else {
            Responder::respondMethodNotAllowed('POST');
        }
    }
}