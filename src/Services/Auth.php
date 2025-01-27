<?php

namespace Api\Services;

use Exception;

class Auth {
    private int $user_id;
    
    public function __construct(
        private JWTCodec $codec
    ) {

    }

    public function getUserId(): int {
        return $this->user_id;
    }

    public function authenticateAccessToken(bool $header = false, string $token = null): bool {
        if (!preg_match("/^Bearer\s+(.*)$/", $_SERVER['HTTP_AUTHORIZATION'], $matches) && $header === true) {
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete authorization header']);
            return false;
        }

        try {
            $payload = $header ? $this->codec->decode($matches[1]) : $this->codec->decode($token);
        } 
        catch (InvalidSignatureException) {
            http_response_code(401);
            echo json_encode(['message' => 'invalid signature']);
            return false;
        }
        catch (TokenExpiredException) {
            http_response_code(401);
            echo json_encode(['message' => 'token has expired']);
            return false;
        }
        catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['message' => $e->getMessage()]);
            return false;
        }
     
        $this->user_id = $payload['sub'];

        return true;
    }

    public function getAccessToken(array $user): array {
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

        return [
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'refresh_token_expiry' => $refresh_token_expiry
        ];
    }
}