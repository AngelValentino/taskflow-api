<?php

namespace Api\Gateways;

use PDO;
use Api\Database\Database;

class QuoteGateway {
    private PDO $conn;

    public function __construct(Database $database) {
        $this->conn = $database->getConnection();
    }

    public function getAllQuotes(): array {
        $sql = "SELECT * FROM quotes";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}