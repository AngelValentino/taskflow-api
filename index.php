<?php

declare(strict_types = 1);
require __DIR__ . '/vendor/autoload.php';

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
use PHPMailer\PHPMailer\PHPMailer;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS'); // Methods allowed for the API
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Headers allowed in the request
header('Content-type: application/json; charset=UTF-8');

set_error_handler([ErrorHandler::class, 'handleError']);
set_exception_handler([ErrorHandler::class, 'handleException']);

// Handle preflight requests (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Return a 200 OK response for OPTIONS
    exit; // Exit early to avoid further processing
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$router = new Router;

function getDbInstance(): Database {
    return new Database($_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
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

function handleRateLimit(string $route, int $maxRequests = 100) {
    $redisConnection = new Redis($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT']);
    $rateLimiter = new RateLimiter($redisConnection, $maxRequests);
    $userKey = $_SERVER['REMOTE_ADDR'];
    $rateLimitKey = $route . ":" . $userKey;

    if ($rateLimiter->isRateLimited($rateLimitKey)) {
        header('Retry-After: 60');
        http_response_code(429);
        echo json_encode(['message' => 'Rate limit exceeded. Please try again later.']);
        exit;
    }
}

$router->add('/register', function() {
    handleRateLimit('register', 5);
    $database = getDbInstance();
    $user_gateway = new UserGateway($database);
    $PHPMailer = new PHPMailer();
    $mailer = new Mailer($PHPMailer, $_ENV['MAIL_HOST'], $_ENV['SENDER_EMAIL'], $_ENV['SENDER_PASSWORD']);
    $register_controller = new RegisterController($user_gateway, $mailer);
    $register_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/login', function() {
    handleRateLimit('login', 5);
    $auth_services = getUserAuthServices();
    $login_controller = new LoginController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth']);
    $login_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/logout', function() {
    handleRateLimit('logout', 5);
    $auth_services = getUserAuthServices();
    $logout_controller = new LogoutController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth']);
    $logout_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/refresh', function() {
    handleRateLimit('refresh', 1);
    $auth_services = getUserAuthServices();
    $refresh_token_controller = new RefreshTokenController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth']);
    $refresh_token_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/recover-password', function() {
    handleRateLimit('recover-password', 5);
    $database = getDbInstance();
    $user_gateway = new UserGateway($database);
    $codec = new JWTCodec($_ENV['SECRET_KEY']);
    $auth = new Auth($codec);
    $PHPMailer = new PHPMailer();
    $mailer = new Mailer($PHPMailer, $_ENV['MAIL_HOST'], $_ENV['SENDER_EMAIL'], $_ENV['SENDER_PASSWORD']);

    $recover_password_controller = new RecoverPasswordController($user_gateway, $auth, $mailer, $_ENV['CLIENT_URL']);
    $recover_password_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/reset-password', function() {
    handleRateLimit('reset-password', 10);
    $database = getDbInstance();

    $user_gateway = new UserGateway($database);
    $codec = new JWTCodec($_ENV['SECRET_KEY']);
    $auth = new Auth($codec);
    $PHPMailer = new PHPMailer();
    $mailer = new Mailer($PHPMailer, $_ENV['MAIL_HOST'], $_ENV['SENDER_EMAIL'], $_ENV['SENDER_PASSWORD']);

    $reset_password_controller = new ResetPasswordController($user_gateway, $auth, $mailer);
    $reset_password_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/tasks', function() {
    handleRateLimit('tasks', 50);
    $codec = new JWTCodec($_ENV['SECRET_KEY']);
    $auth = new Auth($codec);

    if (!$auth->authenticateAccessToken(true)) exit;
    $user_id = $auth->getUserId();

    $database = getDbInstance();
    $task_gateway = new TaskGateway($database);
    $task_controller = new TaskController($task_gateway, $user_id);
    $task_controller->processRequest($_SERVER['REQUEST_METHOD'], null);
});

$router->add('/tasks/{id}', function($task_id) {
    handleRateLimit("task:{$task_id}", 50);
    $codec = new JWTCodec($_ENV['SECRET_KEY']);
    $auth = new Auth($codec);

    if (!$auth->authenticateAccessToken(true)) exit;
    $user_id = $auth->getUserId();

    $database = getDbInstance();
    $task_gateway = new TaskGateway($database);
    $task_controller = new TaskController($task_gateway, $user_id);
    $task_controller->processRequest($_SERVER['REQUEST_METHOD'], $task_id);
});

$router->add('/quotes', function() {
    handleRateLimit('quotes', 5);
    $database = getDbInstance();
    $quote_gateway = new QuoteGateway($database);
    $quote_controller = new QuoteController($quote_gateway);
    $quote_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->dispatch($path);