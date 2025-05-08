<?php

namespace Api\Services;

use Exception;

class Auth {
    private int $user_id;
    private ?string $user_email;
    
    public function __construct(
        private JWTCodec $codec
    ) {

    }

    public function getUserId(): int {
        return $this->user_id;
    }

    public function getUserEmail(): string {
        return $this->user_email;
    }

    public function authenticateAccessToken(bool $header = true, string $token = null, string $expected_type = null): bool {
        if (!preg_match("/^Bearer\s+(.*)$/", $_SERVER['HTTP_AUTHORIZATION'], $matches) && $header === true) {
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete authorization header.']);
            return false;
        }

        try {
            $payload = $header ? $this->codec->decode($matches[1]) : $this->codec->decode($token);
        } 
        catch (InvalidSignatureException) {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid token signature.']);
            return false;
        }
        catch (TokenExpiredException) {
            http_response_code(401);
            echo json_encode(['message' => 'Token has expired.']);
            return false;
        }
        catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['message' => $e->getMessage()]);
            return false;
        }

        if ($expected_type !== null && (($payload['type'] ?? null) !== $expected_type)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid token type.']);
            return false;
        }
     
        $this->user_id = $payload['sub'];
        $this->user_email = $payload['email'] ?? null;

        return true;
    }

    public function getAccessToken(array $user): array {
        $payload = [
            'sub' => $user['id'],
            'username' => $user['username'],
            'exp' => time() + 300, # 5 minutes
            'type' => 'access'
        ];

        $access_token = $this->codec->encode($payload);

        $refresh_token_expiry = time() + 432000; # 5 days
        $refresh_token = $this->codec->encode([
            'sub' => $user['id'],
            'exp' => $refresh_token_expiry,
            'type' => 'refresh'
        ]);

        return [
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'refresh_token_expiry' => $refresh_token_expiry
        ];
    }

    public function getRecoverPasswordToken($user): string {
        $payload = [
            'sub' => $user['id'],
            'exp' => time() + 600, // 10 min expiry
            'email' => $user['email'],
            'type' => 'reset-password'
        ];

        $reset_token = $this->codec->encode($payload);
        return $reset_token;
    }
}