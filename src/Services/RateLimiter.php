<?php

namespace Api\Services;

use Api\Database\Redis;
use Predis\Client;

class RateLimiter {
    private Client $redis;
    private int $maxRequests;
    private int $timeWindow; // in seconds (e.g., 60 for 1 minute)

    public function __construct(Redis $redis, int $maxRequests = 100, int $timeWindow = 60) {
        $this->redis = $redis->getConnection();
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
    }

    public function isRateLimited(string $userKey): bool {
        // Get the number of requests made by the user within the current time window
        $requests = $this->redis->get($userKey);

        if ($requests === null) {
            // If no requests, set it with an expiry (this is the first request)
            $this->redis->setex($userKey, $this->timeWindow, 1);
            return false;
        }

        // If the user has exceeded the limit
        if ((int)$requests >= $this->maxRequests) {
            return true; // Rate limit exceeded
        }

        // Otherwise, increment the request counter
        $this->redis->incr($userKey);
        return false;
    }
}
