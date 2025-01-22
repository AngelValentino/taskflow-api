<?php 

namespace Api\Services;

use ErrorException;
use Throwable;

# does not handle fatal errors
class ErrorHandler {
    public static function handleError(
        int $errno,
        string $errstr,
        string $errfile,
        int $errline
    ): void {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public static function handleException(Throwable $exception): void {
        http_response_code(500);
        echo json_encode([
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]);
        exit;
    }
}