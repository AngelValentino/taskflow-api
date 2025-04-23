<?php

namespace Api\Services;
use Api\Database\Database;
use Api\Database\Redis;
use Api\Gateways\UserGateway;
use Api\Gateways\RefreshTokenGateway;

class InitApiUtils {
    public static function getDbInstance(): Database {
        $sslCaPath = null;
        $sslCa = null;
        
        if ($_ENV['DB_SSL'] === 'true' && $_ENV['APP_ENV'] === 'development') {
            $sslCaPath = __dir__ . '/../../certs/ca-certificate.crt';
        }
        else if ($_ENV['DB_SSL'] === 'true' && $_ENV['APP_ENV'] === 'production') {
            $sslCa = $_ENV['DB_SSL_CA'];
        }
    
        return new Database(
            $_ENV['DB_HOST'], 
            $_ENV['DB_NAME'], 
            $_ENV['DB_USER'], 
            $_ENV['DB_PASSWORD'], 
            $_ENV['DB_PORT'], 
            $sslCaPath, 
            $sslCa
        );
    }

    public static function getAndVerifyDeviceId(): string {
        $rateLimiter = new RateLimiter(new Redis($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT']));
        $deviceId = $_SERVER['HTTP_X_DEVICE_ID'] ?? null;
        $rateLimiter->authDeviceId($deviceId);
        return $deviceId;
    }

    public static function handleRateLimit(string $route, int $max_requests = 5, int $window = 60, int $block_window = 60): void {
        $rateLimiter = new RateLimiter(new Redis($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT']));
        $rateLimiter->detectRateLimit($_SERVER['REMOTE_ADDR'], self::getAndVerifyDeviceId(), $route, $window, $max_requests, $block_window);
    }

    public static function handleAllowedOrigins() {
        $allowedOrigins = [
            'development' => explode(',', $_ENV['DEVELOPMENT_ORIGINS']),
            'production' => explode(',', $_ENV['PRODUCTION_ORIGINS'])
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowedOrigins[$_ENV['APP_ENV']])) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } 
        else {
            http_response_code(403);
            exit;
        }
    }
    
    public static function getUserAuthServices(): array {
        $database = self::getDbInstance();
        
        $user_gateway = new UserGateway($database);
        $refresh_token_gateway = new RefreshTokenGateway($database, $_ENV['SECRET_KEY']);
        $codec = new JWTCodec($_ENV['SECRET_KEY']);
        $auth = new Auth($codec);
        
        return [
            'user_gateway' => $user_gateway,
            'refresh_token_gateway' => $refresh_token_gateway,
            'auth' => $auth,
        ];
    }
}