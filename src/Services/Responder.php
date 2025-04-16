<?php

namespace Api\Services;

class Responder {
    public function respondUnprocessableEntity(array $errors):void {
        http_response_code(422);
        echo json_encode(['errors' => $errors]);
    }

    public function respondMethodNotAllowed(string $allowed_methods): void {
        http_response_code(405);
        header("Allow: $allowed_methods");
    }

    public function respondNotFound(string $message): void {
        http_response_code(404);
        echo json_encode(['message' => $message]);
    }

    public function respondUnauthorized($message): void {
        http_response_code(401);
        echo json_encode(['message' => $message]);
    }

    public function respondBadRequest(string $message): void {
        http_response_code(400);
        echo json_encode(['message' => $message]);
    }

    public function respondCreated($message): void {
        http_response_code(201);
        echo json_encode(['message' => $message]);
    }

    public function respondNoContent(): void {
        http_response_code(204);
    }
}