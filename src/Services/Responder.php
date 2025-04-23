<?php

namespace Api\Services;

class Responder {
    public static function respondTooManyRequests(string $message, int $window): void {
        header('Retry-After: ' . $window);
        http_response_code(429);
        echo json_encode(['message' => $message]);
    }

    public static function respondUnprocessableEntity(array $errors):void {
        http_response_code(422);
        echo json_encode(['errors' => $errors]);
    }

    public static function respondMethodNotAllowed(string $allowed_methods): void {
        http_response_code(405);
        header("Allow: $allowed_methods");
    }

    public static function respondNotFound(string $message): void {
        http_response_code(404);
        echo json_encode(['message' => $message]);
    }

    public static function respondUnauthorized($message): void {
        http_response_code(401);
        echo json_encode(['message' => $message]);
    }

    public static function respondBadRequest(string $message): void {
        http_response_code(400);
        echo json_encode(['message' => $message]);
    }

    public static function respondNoContent(): void {
        http_response_code(204);
    }

    public static function respondCreated($message): void {
        http_response_code(201);
        echo json_encode(['message' => $message]);
    }
}