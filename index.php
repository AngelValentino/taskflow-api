<?php

declare(strict_types = 1);
require __DIR__ . '/vendor/autoload.php';


use Api\Services\ErrorHandler;
use Api\Controllers\RegisterController;
use Api\Database\Database;
use Api\Gateways\UserGateway;

use Api\Controllers\LoginController;
use Api\Services\JWTCodec;

use Api\Controllers\RefreshTokenController;
use Api\Gateways\RefreshTokenGateway;


header('Content-type: application/json; charset=UTF-8');
set_error_handler([ErrorHandler::class, 'handleError']);
set_exception_handler([ErrorHandler::class, 'handleException']);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', $path);
$resource = $parts[2];
$resource_id = $parts[3] ?? null;


if ($resource === 'register') {
    $database = new Database($_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);

    $user_gateway = new UserGateway($database);
    $register_controller = new RegisterController($user_gateway);
    $register_controller->processRequest($_SERVER['REQUEST_METHOD']);

    exit;
} 
else if ($resource === 'login') {
    $database = new Database($_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
    $user_gateway = new UserGateway($database);
    $refresh_token_gateway = new RefreshTokenGateway($database, $_ENV['SECRET_KEY']);
    $codec = new JWTCodec($_ENV['SECRET_KEY']);

    $login_controller = new LoginController($user_gateway, $refresh_token_gateway, $codec);
    $login_controller->processRequest($_SERVER['REQUEST_METHOD']);

    exit;
} 
else if ($resource === 'logout') {
    
    

    exit;
}
else if ($resource === 'refresh') {
    $database = new Database($_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
    $refresh_token_gateway = new RefreshTokenGateway($database, $_ENV['SECRET_KEY']);
    $user_gateway = new UserGateway($database);
    $codec = new JWTCodec($_ENV['SECRET_KEY']);

    $refresh_token_controller = new RefreshTokenController($refresh_token_gateway, $user_gateway, $codec);
    $refresh_token_controller->processRequest($_SERVER['REQUEST_METHOD']);

    exit;
}


http_response_code(404);
exit;