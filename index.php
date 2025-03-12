<?php

declare(strict_types = 1);
require __DIR__ . '/vendor/autoload.php';

use Api\Database\Database;
use Api\Services\ErrorHandler;
use Api\Services\JWTCodec;
use Api\Services\Auth;
use Api\Controllers\RegisterController;
use Api\Controllers\LoginController;
use Api\Controllers\LogoutController;
use Api\Controllers\TaskController;
use Api\Controllers\RefreshTokenController;
use Api\Controllers\QuoteController;
use Api\Gateways\UserGateway;
use Api\Gateways\RefreshTokenGateway;
use Api\Gateways\QuoteGateway;
use Api\Gateways\TaskGateway;
use Api\Services\Router;

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

$router->add('/register', function() {
    $database = getDbInstance();
    $user_gateway = new UserGateway($database);
    $register_controller = new RegisterController($user_gateway);
    $register_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/login', function() {
    $auth_services = getUserAuthServices();
    $login_controller = new LoginController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth']);
    $login_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/logout', function() {
    $auth_services = getUserAuthServices();
    $logout_controller = new LogoutController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth']);
    $logout_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/refresh', function() {
    $auth_services = getUserAuthServices();
    $refresh_token_controller = new RefreshTokenController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth']);
    $refresh_token_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->add('/tasks', function() {
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
    $database = getDbInstance();
    $quote_gateway = new QuoteGateway($database);
    $quote_controller = new QuoteController($quote_gateway);
    $quote_controller->processRequest($_SERVER['REQUEST_METHOD']);
});

$router->dispatch($path);