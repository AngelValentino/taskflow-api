<?php

namespace Api\Controllers;

use Api\Gateways\QuoteGateway;
use Api\Services\Responder;

class QuoteController {
    public function __construct(
        private QuoteGateway $gateway
    ) {
        
    }

    public function processRequest(string $method): void {
        if ($method === 'GET') {
            $quotes = $this->gateway->getAllQuotes();
            echo json_encode($quotes);
        } 
        else {
            Responder::respondMethodNotAllowed('GET');
        }
    }
}