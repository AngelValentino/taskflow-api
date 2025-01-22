<?php

declare(strict_types = 1);
require __DIR__ . '/vendor/autoload.php';

use Api\Services\ErrorHandler;

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

  echo 'register';

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