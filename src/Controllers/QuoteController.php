<?php

namespace Api\Controllers;

use Api\Gateways\QuoteGateway;
use Api\Services\Responder;

class QuoteController {
    public function __construct(
        private QuoteGateway $quote_gateway
    ) {
        
    }

    public function processRequest(string $method): void {
        if ($method === 'GET') {
            $quotes = $this->quote_gateway->getAllQuotes();
            
            $sanitizedQuotes = array_map(function($quote) {
                return array_map(function($value) {
                    return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
                }, $quote);
            }, $quotes);

            echo json_encode($sanitizedQuotes);
        }
        else {
            Responder::respondMethodNotAllowed('GET');
        }
    }
}