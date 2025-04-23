<?php

declare(strict_types = 1);
require __DIR__ . '/../vendor/autoload.php';

use Api\Database\Redis;
use Api\Services\ErrorHandler;
use Api\Services\JWTCodec;
use Api\Services\Auth;
use Api\Services\InitApiUtils;
use Api\Controllers\RegisterController;
use Api\Controllers\LoginController;
use Api\Controllers\LogoutController;
use Api\Controllers\RecoverPasswordController;
use Api\Controllers\TaskController;
use Api\Controllers\RefreshTokenController;
use Api\Controllers\QuoteController;
use Api\Controllers\ResetPasswordController;
use Api\Gateways\UserGateway;
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

InitApiUtils::handleAllowedOrigins();
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

// Handle device rotation
$rateLimiter = new RateLimiter(new Redis($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT']));
$rateLimiter->detectDeviceIdRotation($_SERVER['REMOTE_ADDR'], InitApiUtils::getAndVerifyDeviceId());

$router->add('/register', function() {
    InitApiUtils::handleRateLimit('register', 5);
    
    $database = InitApiUtils::getDbInstance();
    $user_gateway = new UserGateway($database);
    $PHPMailer = new PHPMailer(true);
    $mailer = new Mailer($PHPMailer, $_ENV['MAIL_HOST'], $_ENV['SENDER_EMAIL'], $_ENV['SENDER_PASSWORD'], $_ENV['SENDER_USERNAME'], (int) $_ENV['SENDER_PORT']);
    $register_controller = new RegisterController($user_gateway, $mailer);
    
    $register_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/login', function() {
    InitApiUtils::handleRateLimit('login', 5);

    $auth_services = InitApiUtils::getUserAuthServices();
    $login_controller = new LoginController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth']);
    
    $login_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/logout', function() {
    InitApiUtils::handleRateLimit('logout', 5);

    $auth_services = InitApiUtils::getUserAuthServices();
    $logout_controller = new LogoutController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth']);
    
    $logout_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/refresh', function() {
    InitApiUtils::handleRateLimit('refresh', 1, 240, 240);

    $auth_services = InitApiUtils::getUserAuthServices();
    $refresh_token_controller = new RefreshTokenController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth']);
    
    $refresh_token_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/recover-password', function() {
    InitApiUtils::handleRateLimit('recover-password', 5);

    $database = InitApiUtils::getDbInstance();
    $user_gateway = new UserGateway($database);
    $codec = new JWTCodec($_ENV['SECRET_KEY']);
    $auth = new Auth($codec);
    $PHPMailer = new PHPMailer(true);
    $mailer = new Mailer($PHPMailer, $_ENV['MAIL_HOST'], $_ENV['SENDER_EMAIL'], $_ENV['SENDER_PASSWORD'], $_ENV['SENDER_USERNAME'], (int) $_ENV['SENDER_PORT']);
    $recover_password_controller = new RecoverPasswordController($_ENV['APP_ENV'] === 'production' ? $_ENV['CLIENT_URL_PROD'] : $_ENV['CLIENT_URL_DEV'], $user_gateway, $auth, $mailer);
    
    $recover_password_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/reset-password', function() {
    InitApiUtils::handleRateLimit('reset-password', 10);
    
    $database = InitApiUtils::getDbInstance();
    $user_gateway = new UserGateway($database);
    $codec = new JWTCodec($_ENV['SECRET_KEY']);
    $auth = new Auth($codec);
    $PHPMailer = new PHPMailer(true);
    $mailer = new Mailer($PHPMailer, $_ENV['MAIL_HOST'], $_ENV['SENDER_EMAIL'], $_ENV['SENDER_PASSWORD'], $_ENV['SENDER_USERNAME'], (int) $_ENV['SENDER_PORT']);
    $reset_password_controller = new ResetPasswordController($user_gateway, $auth, $mailer);
    
    $reset_password_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/tasks', function() {
    InitApiUtils::handleRateLimit('tasks', 50);
    $codec = new JWTCodec($_ENV['SECRET_KEY']);
    $auth = new Auth($codec);

    if (!$auth->authenticateAccessToken(true, null, 'access')) exit;
    $user_id = $auth->getUserId();

    $database = InitApiUtils::getDbInstance();
    $task_gateway = new TaskGateway($database);
    $task_controller = new TaskController($user_id, $task_gateway);
    
    $task_controller->processRequest($_SERVER['REQUEST_METHOD'], null);
});

$router->add('/tasks/{id}', function($task_id) {
    InitApiUtils::handleRateLimit("tasks:taskId:{$task_id}", 50);

    $codec = new JWTCodec($_ENV['SECRET_KEY']);
    $auth = new Auth($codec);

    if (!$auth->authenticateAccessToken(true, null, 'access')) exit;
    $user_id = $auth->getUserId();

    $database = InitApiUtils::getDbInstance();
    $task_gateway = new TaskGateway($database);
    $task_controller = new TaskController($user_id, $task_gateway);
    
    $task_controller->processRequest($_SERVER['REQUEST_METHOD'], $task_id);
});

$router->add('/quotes', function() {
    InitApiUtils::handleRateLimit('quotes', 1);

    $database = InitApiUtils::getDbInstance();
    $quote_gateway = new QuoteGateway($database); 
    $quote_controller = new QuoteController($quote_gateway);
    
    $quote_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->dispatch($path);