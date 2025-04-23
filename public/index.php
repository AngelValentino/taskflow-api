<?php

declare(strict_types = 1);
require __DIR__ . '/../vendor/autoload.php';

use Api\Database\Database;
use Api\Database\Redis;
use Api\Services\ErrorHandler;
use Api\Services\JWTCodec;
use Api\Services\Auth;
use Api\Controllers\RegisterController;
use Api\Controllers\LoginController;
use Api\Controllers\LogoutController;
use Api\Controllers\RecoverPasswordController;
use Api\Controllers\TaskController;
use Api\Controllers\RefreshTokenController;
use Api\Controllers\QuoteController;
use Api\Controllers\ResetPasswordController;
use Api\Gateways\UserGateway;
use Api\Gateways\RefreshTokenGateway;
use Api\Gateways\QuoteGateway;
use Api\Gateways\TaskGateway;
use Api\Services\Mailer;
use Api\Services\Router;
use Api\Services\RateLimiter;
use Api\Services\Responder;
use PHPMailer\PHPMailer\PHPMailer;

set_error_handler([ErrorHandler::class, 'handleError']); // Convert all PHP warnings/notices into ErrorException
set_exception_handler([ErrorHandler::class, 'handleException']); // Handle uncaught exceptions

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

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

header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS'); // Methods allowed for the API
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-ID'); // Headers allowed in the request
header('Content-type: application/json; charset=UTF-8');

// Handle preflight requests (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Return a 200 OK response for OPTIONS
    exit; // Exit early to avoid further processing
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$router = new Router;

function getDbInstance(): Database {
    $sslCaPath = null;
    $sslCa = null;
    
    if ($_ENV['DB_SSL'] === 'true' && $_ENV['APP_ENV'] === 'development') {
        $sslCaPath = __dir__ . '/../certs/ca-certificate.crt';
    }
    else if ($_ENV['DB_SSL'] === 'true' && $_ENV['APP_ENV'] === 'production') {
        $sslCa = $_ENV['DB_SSL_CA'];
    }

    return new Database($_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $_ENV['DB_PORT'], $sslCaPath, $sslCa);
}

function getUserAuthServices(): array {
    $database = getDbInstance();
    
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

function getAndVerifyDeviceId(): string {
    $rateLimiter = new RateLimiter(new Redis($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT']), new Responder, ErrorHandler::class);
    $deviceId = $_SERVER['HTTP_X_DEVICE_ID'] ?? null;
    $rateLimiter->authDeviceId($deviceId);
    return $deviceId;
}

// Handle device rotation
$rateLimiter = new RateLimiter(new Redis($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT']), new Responder, ErrorHandler::class);
$rateLimiter->detectDeviceIdRotation($_SERVER['REMOTE_ADDR'], getAndVerifyDeviceId());

function handleRateLimit(string $route, int $max_requests = 5, int $window = 60, int $block_window = 60): void {
    $rateLimiter = new RateLimiter(new Redis($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT']), new Responder, ErrorHandler::class);
    $rateLimiter->detectRateLimit($_SERVER['REMOTE_ADDR'], getAndVerifyDeviceId(), $route, $window, $max_requests, $block_window);
}

$router->add('/register', function() {
    handleRateLimit('register', 5);
    
    $responder = new Responder;
    $database = getDbInstance();
    $user_gateway = new UserGateway($database);
    $PHPMailer = new PHPMailer(true);
    $mailer = new Mailer($PHPMailer, $_ENV['MAIL_HOST'], $_ENV['SENDER_EMAIL'], $_ENV['SENDER_PASSWORD'], $_ENV['SENDER_USERNAME'], (int) $_ENV['SENDER_PORT']);
    $register_controller = new RegisterController($user_gateway, $mailer, $responder);
    
    $register_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/login', function() {
    handleRateLimit('login', 5);

    $responder = new Responder;
    $auth_services = getUserAuthServices();
    $login_controller = new LoginController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth'], $responder);
    
    $login_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/logout', function() {
    handleRateLimit('logout', 5);

    $responder = new Responder;
    $auth_services = getUserAuthServices();
    $logout_controller = new LogoutController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth'], $responder);
    
    $logout_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/refresh', function() {
    handleRateLimit('refresh', 1, 240);

    $responder = new Responder;
    $auth_services = getUserAuthServices();
    $refresh_token_controller = new RefreshTokenController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth'], $responder);
    
    $refresh_token_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/recover-password', function() {
    handleRateLimit('recover-password', 5);

    $responder = new Responder;
    $database = getDbInstance();
    $user_gateway = new UserGateway($database);
    $codec = new JWTCodec($_ENV['SECRET_KEY']);
    $auth = new Auth($codec);
    $PHPMailer = new PHPMailer(true);
    $mailer = new Mailer($PHPMailer, $_ENV['MAIL_HOST'], $_ENV['SENDER_EMAIL'], $_ENV['SENDER_PASSWORD'], $_ENV['SENDER_USERNAME'], (int) $_ENV['SENDER_PORT']);
    $recover_password_controller = new RecoverPasswordController($_ENV['APP_ENV'] === 'production' ? $_ENV['CLIENT_URL_PROD'] : $_ENV['CLIENT_URL_DEV'], $user_gateway, $auth, $mailer, $responder);
    
    $recover_password_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/reset-password', function() {
    handleRateLimit('reset-password', 10);
    
    $responder = new Responder;
    $database = getDbInstance();
    $user_gateway = new UserGateway($database);
    $codec = new JWTCodec($_ENV['SECRET_KEY']);
    $auth = new Auth($codec);
    $PHPMailer = new PHPMailer(true);
    $mailer = new Mailer($PHPMailer, $_ENV['MAIL_HOST'], $_ENV['SENDER_EMAIL'], $_ENV['SENDER_PASSWORD'], $_ENV['SENDER_USERNAME'], (int) $_ENV['SENDER_PORT']);
    $reset_password_controller = new ResetPasswordController($user_gateway, $auth, $mailer, $responder);
    
    $reset_password_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/tasks', function() {
    handleRateLimit('tasks', 50);
    $codec = new JWTCodec($_ENV['SECRET_KEY']);
    $auth = new Auth($codec);

    if (!$auth->authenticateAccessToken(true, null, 'access')) exit;
    $user_id = $auth->getUserId();

    $responder = new Responder;
    $database = getDbInstance();
    $task_gateway = new TaskGateway($database);
    $task_controller = new TaskController($user_id, $task_gateway, $responder);
    
    $task_controller->processRequest($_SERVER['REQUEST_METHOD'], null);
});

$router->add('/tasks/{id}', function($task_id) {
    handleRateLimit("tasks:taskId:{$task_id}", 50);

    $codec = new JWTCodec($_ENV['SECRET_KEY']);
    $auth = new Auth($codec);

    if (!$auth->authenticateAccessToken(true, null, 'access')) exit;
    $user_id = $auth->getUserId();

    $responder = new Responder;
    $database = getDbInstance();
    $task_gateway = new TaskGateway($database);
    $task_controller = new TaskController($user_id, $task_gateway, $responder);
    
    $task_controller->processRequest($_SERVER['REQUEST_METHOD'], $task_id);
});

$router->add('/quotes', function() {
    handleRateLimit('quotes', 1);

    $responder = new Responder;
    $database = getDbInstance();
    $quote_gateway = new QuoteGateway($database); 
    $quote_controller = new QuoteController($quote_gateway, $responder);
    
    $quote_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->dispatch($path);