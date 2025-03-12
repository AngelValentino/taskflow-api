<?php

namespace Api\Controllers;

use Api\Gateways\QuoteGateway;

class QuoteController {
    public function __construct(
        private QuoteGateway $gateway
    )
    {
        
    }

    public function processRequest(string $method): void {
        if ($method === 'GET') {
            $quotes = $this->gateway->getAllQuotes();
            echo json_encode($quotes);
        } else {
            $this->respondMethodNotAllowed('GET');
        }
    }

    private function respondMethodNotAllowed(string $allowed_methods): void {
        http_response_code(405);
        header("Allow: $allowed_methods");
    }
}