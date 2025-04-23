<?php

namespace Api\Services;

use Api\Database\Redis;
use Predis\Client;

class RateLimiter {
    private Client $redis;

    public function __construct(Redis $redis) {
        $this->redis = $redis->getConnection();
    }

    public function authDeviceId(string $device_id) {
        if (!$device_id) {
            Responder::respondBadRequest('No device id found.');
            exit;
        }
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $device_id)) {
            Responder::respondUnauthorized('Incorrect device id format found.');
            exit;
        }
    }

    // Generate the key for rate-limiting based on the route, device ID, and IP address
    public function getRateLimitKey(string $ip, string $device_id, string $route): string {
        return "ip:{$ip}:deviceId:{$device_id}:route:{$route}";
    }

    public function detectRateLimit(string $ip, string $device_id, string $route, int $window = 60, int $max_requests = 5, int $block_window = 60): void {
        $base_key = $this->getRateLimitKey($ip, $device_id, $route);
        $counter_key = "{$base_key}:requests";
        $block_key = "{$base_key}:blocked";
    
        if ($this->redis->exists($block_key)) {
            Responder::respondTooManyRequests('Too many requests. Please try again later.', $block_window);            
            exit;
        }

        $request_count = $this->redis->incr($counter_key);
        
        if ($request_count === 1) {
            $this->redis->expire($counter_key, $window);
        }
    
        if ((int) $request_count > $max_requests) {
            $minutes = ceil($block_window / 60);
            $label = (int) $minutes === 1 ? 'minute' : 'minutes';

            $this->redis->setex($block_key, $block_window, 1);  // Block for 60 seconds
            
            Responder::respondTooManyRequests("Too many requests. You have been blocked for {$minutes} {$label}.", $block_window);
            ErrorHandler::logAudit("IP {$ip} blocked on route '{$route}' due to {$request_count} requests (max allowed: {$max_requests})");

            exit;
        }
    }

    // Detect too many Device ID rotations from the same IP within the time window
    public function detectDeviceIdRotation(string $ip, string $device_id, int $window = 300, int $max_ids = 2, int $block_window = 300): void {
        $set_key = "ip:{$ip}:deviceIds";
        $block_key = "ip:{$ip}:blocked";
    
        if ($this->redis->exists($block_key)) {
            Responder::respondTooManyRequests('Too many device switches. Try again later.', $block_window);
            exit;
        }
    
        // Add the device ID to the set
        $this->redis->sAdd($set_key, $device_id);
        // Set expiry on the set, expire once window ends
        $this->redis->expire($set_key, $window);
        // Count how many unique device IDs were seen
        $count = $this->redis->sCard($set_key);
    
        // If more device IDs changes than allowed, block IP for the time needed
        if ((int) $count > $max_ids) {
            $minutes = ceil($block_window / 60);
            $label = (int) $minutes === 1 ? 'minute' : 'minutes';
            
            $this->redis->setex($block_key, $block_window, 1);
            $this->redis->del($set_key);

            Responder::respondTooManyRequests("Too many device IDs detected. You have been blocked for {$minutes} {$label}.", $block_window);
            ErrorHandler::logAudit("IP {$ip} blocked for device ID rotation. Seen IDs: {$count} (limit: {$max_ids})");

            exit;
        }
    }

    public function detectIpRotation() {

    }
}
