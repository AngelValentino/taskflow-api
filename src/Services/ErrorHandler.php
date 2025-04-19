<?php 

namespace Api\Services;

use ErrorException;
use Throwable;

class ErrorHandler {
    private static string $logFile = __DIR__ . '/../../logs/errors.log';
    
    public static function handleError(
        int $errno,
        string $errstr,
        string $errfile,
        int $errline
    ): void {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public static function handleException(Throwable $exception): void {
        // Log the error
        self::logError($exception);
        
        http_response_code(500);
        echo json_encode([
            'message' => 'An internal server error occurred. Please try again later.'
        ]);
        exit;
    }

    private static function logError(Throwable $exception): void {
        $errorDetails = sprintf(
            "[%s] ERROR: %s in %s on line %d\n\n", 
            date('Y-m-d H:i:s'), 
            $exception->getMessage(), 
            $exception->getFile(), 
            $exception->getLine()
        );

        // Check if log directory exists, create if not
        $logDir = __DIR__ . '/../../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);  // Create logs directory with proper permissions
        }

        // If log file doesn't exist, create it
        if (!file_exists(self::$logFile)) {
            touch(self::$logFile);  // Create the log file if it doesn't exist
        }

        // Write error details to the log file
        file_put_contents(self::$logFile, $errorDetails, FILE_APPEND);
    }
}