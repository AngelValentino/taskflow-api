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

if ($row_count > 0) {
    ErrorHandler::logAudit("Successfully deleted {$row_count} expired refresh tokens.");
    
    echo json_encode([
        'status' => 'success',
        'message' => "Successfully deleted {$row_count} expired refresh tokens."
    ]) . PHP_EOL;
} 
else {
    ErrorHandler::logAudit("No expired refresh tokens found to delete.");
    
    echo json_encode([
        'status' => 'info',
        'message' => 'No expired refresh tokens found to delete.'
    ]) . PHP_EOL;
}