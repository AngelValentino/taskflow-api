<?php

namespace Api\Controllers;

use Api\Gateways\RefreshTokenGateway;
use Api\Gateways\UserGateway;
use Api\Services\Auth;
use Api\Services\Responder;

class RefreshTokenController {
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

            $refresh_token = $this->refresh_token_gateway->getByToken($data['token']);

            if ($refresh_token === false) {
                Responder::respondBadRequest('Invalid token(not on whitelist).');
                return;
            }

            // Validate user info
            $user = $this->user_gateway->getById($user_id);

            if ($user === false) {
                Responder::respondUnauthorized('Invalid authentication.');
                return;
            }

            // Regenerate access and refresh token
            $access_token = $this->auth->getAccessToken($user);

            // Update db with the newly created refresh token
            $this->refresh_token_gateway->delete($data['token']);
            $this->refresh_token_gateway->create($access_token['refresh_token'], $access_token['refresh_token_expiry']);

            // Send JSON
            echo json_encode([
                'access_token' => $access_token['access_token'],
                'refresh_token' => $access_token['refresh_token'],
                'username' => htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8')
            ]);
        } 
        else {
            Responder::respondMethodNotAllowed('POST');
            return;
        }
    }
}