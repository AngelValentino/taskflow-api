<?php

declare(strict_types = 1);
require __DIR__ . '/vendor/autoload.php';

use Api\Services\ErrorHandler;
use Api\Controllers\RegisterController;
use Api\Database\Database;
use Api\Gateways\UserGateway;

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
  echo 'login';
  exit;
} 
else if ($resource === 'logout') {
  echo 'logout';
  exit;
}
else if ($resource === 'refresh') {
  echo 'refresh';
  exit;
}


http_response_code(404);
exit;