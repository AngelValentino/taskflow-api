<?php

namespace Api\Controllers;

use Api\Gateways\RefreshTokenGateway;
use Api\Gateways\UserGateway;
use Api\Services\Auth;
use Api\Services\Responder;
use Api\Services\ErrorHandler;

class LoginController {
    public function __construct(
        private UserGateway $user_gateway,
        private RefreshTokenGateway $refresh_token_gateway,
        private Auth $auth
    ) {
        
    }

    public function processRequest(string $method): void {
        if ($method === 'POST') {
            $data = (array) json_decode(file_get_contents('php://input'), true);

            if (empty($data['email']) || empty($data['password'])) {
                Responder::respondBadRequest('Missing login credentials.');
                return;
            }
    
            $user = $this->user_gateway->getByEmail($data['email']);
            $error = $this->getUserValidationErrorMessage($data, $user);

            if (isset($error)) {
                Responder::respondUnauthorized($error);
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
                'username' => htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8')
            ]);
        } 
        else {
            Responder::respondMethodNotAllowed('POST');
        }
    }

    private function getUserValidationErrorMessage(array $data, array | false $user): ?string {
        $dummyPassword = bin2hex(random_bytes(16)); // Random 32-char string
        $dummyHash = password_hash($dummyPassword, PASSWORD_DEFAULT);
        $inputPassword = $data['password'] ?? '';

        // Add a small random delay if no user exists, to further prevent timing attacks
        if ($user === false) {
            usleep(rand(80000, 425000));
        }

        // This should never happen due to the randomness of the dummy password
        if ($user === false && password_verify($inputPassword, $dummyHash)) {
            ErrorHandler::logAudit("RARE_EVENT -> IP {$_SERVER['REMOTE_ADDR']} - Input password matched dummy hash with no associated user");
            return 'An unexpected error occurred. Please try again later.';
        }

        if (!password_verify($inputPassword, $user === false ? $dummyHash : $user['password_hash'])) {
            return 'The e-mail address or password is incorrect.';
        }

        return null;
    }
}