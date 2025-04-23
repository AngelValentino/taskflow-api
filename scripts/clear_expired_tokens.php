<?php

declare(strict_types = 1);
require __DIR__ . '/../vendor/autoload.php';

use Api\Gateways\RefreshTokenGateway;
use Api\Services\ErrorHandler;
use Api\Services\InitApiUtils;

set_error_handler([ErrorHandler::class, 'handleError']); // Convert all PHP warnings/notices into ErrorException
set_exception_handler([ErrorHandler::class, 'handleException']); // Handle uncaught exceptions

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$database = InitApiUtils::getDbInstance();
$refreshTokenGateway = new RefreshTokenGateway($database, $_ENV['SECRET_KEY']);
$row_count = $refreshTokenGateway->deleteExpired();
$timestamp = date('Y-m-d H:i:s');

if ($row_count > 0) {
    $logMessage = "Successfully deleted {$row_count} expired refresh tokens.";
    ErrorHandler::logAudit($logMessage);
    echo "[{$timestamp}] {$logMessage}" . PHP_EOL;
} 
else {
    $logMessage = "No expired refresh tokens found to delete.";
    ErrorHandler::logAudit($logMessage);
    echo "[{$timestamp}] {$logMessage}" . PHP_EOL;
}