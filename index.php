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
use Api\Gateways\UserGateway;
use Api\Gateways\RefreshTokenGateway;
use Api\Gateways\TaskGateway;

header('Content-type: application/json; charset=UTF-8');
set_error_handler([ErrorHandler::class, 'handleError']);
set_exception_handler([ErrorHandler::class, 'handleException']);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', $path);
$resource = $parts[2];
$resource_id = $parts[3] ?? null;

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

switch ($resource) {
    case 'register':
        $database = getDbInstance();
        $user_gateway = new UserGateway($database);
        $register_controller = new RegisterController($user_gateway);
        $register_controller->processRequest($_SERVER['REQUEST_METHOD']);

        break;

    case 'login':
        $auth_services = getUserAuthServices();
        $login_controller = new LoginController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth']);
        $login_controller->processRequest($_SERVER['REQUEST_METHOD']);
        
        break;

    case 'logout':        
        $auth_services = getUserAuthServices();
        $logout_controller = new LogoutController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth']);
        $logout_controller->processRequest($_SERVER['REQUEST_METHOD']);
    
        break;

    case 'refresh':
        $auth_services = getUserAuthServices();
        $refresh_token_controller = new RefreshTokenController($auth_services['user_gateway'], $auth_services['refresh_token_gateway'], $auth_services['auth']);
        $refresh_token_controller->processRequest($_SERVER['REQUEST_METHOD']);
        
        break;

    case 'tasks':
        $codec = new JWTCodec($_ENV['SECRET_KEY']);
        $auth = new Auth($codec);
    
        if (!$auth->authenticateAccessToken(true)) exit;
        $user_id = $auth->getUserId();
    
        $database = getDbInstance();
        $task_gateway = new TaskGateway($database);
        $task_controller = new TaskController($task_gateway, $user_id);
        $task_controller->processRequest($_SERVER['REQUEST_METHOD'], $resource_id);

        break;

    case 'quotes':
        echo json_encode(['message' => 'Endpoint under construction.']);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Resource not found.']);
}