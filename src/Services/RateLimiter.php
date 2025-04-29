<?php

namespace Api\Services;

use Api\Database\Redis;
use Predis\Client;

class RateLimiter {
    private Client $redis_conn;

    public function __construct(Redis $redis) {
        $this->redis_conn = $redis->getConnection();
    }

    public function authDeviceId(?string $device_id) {
        if (!$device_id) {
            Responder::respondBadRequest('No device id found.');
            exit;
        }
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $device_id)) {
            Responder::respondUnauthorized('Incorrect device id format found.');
            exit;
        }
        
        // We rely on the rotation window cache for expiry checks
        // No need for additional expiry checks here since we're handling it with the window logic
    }

    // Generate the key for rate-limiting based on the route, device ID, and IP address
    public function getRateLimitKey(string $ip, string $device_id, string $route): string {
        return "ip:{$ip}:deviceId:{$device_id}:route:{$route}";
    }

    public function detectRateLimit(string $ip, string $device_id, string $route, int $window = 60, int $max_requests = 5, int $block_window = 60): void {
        $base_key = $this->getRateLimitKey($ip, $device_id, $route);
        $counter_key = "{$base_key}:requests";
        $block_key = "{$base_key}:blocked";
    
        if ($this->redis_conn->exists($block_key)) {
            Responder::respondTooManyRequests('Too many requests. Please try again later.', $block_window);            
            exit;
        }

        $request_count = $this->redis_conn->incr($counter_key);
        
        if ($request_count === 1) {
            $this->redis_conn->expire($counter_key, $window);
        }
    
        if ((int) $request_count > $max_requests) {
            $minutes = ceil($block_window / 60);
            $label = (int) $minutes === 1 ? 'minute' : 'minutes';

            $this->redis_conn->setex($block_key, $block_window, 1);  // Block for 60 seconds
            
            Responder::respondTooManyRequests("Too many requests. You have been blocked for {$minutes} {$label}.", $block_window);
            ErrorHandler::logAudit("RATE_LIMIT -> IP {$ip} blocked on route '{$route}' due to {$request_count} requests (max allowed: {$max_requests})");

            exit;
        }
    }

    private function camelCaseToSpaced($input): string {
        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $input);
    }

    private function detectRotation(
        string $set_key_owner,
        string $value_to_track,
        string $ip, 
        string $device_id, 
        int $window = 300, 
        int $max_count = 3, 
        int $block_window = 300
    ): void {
        $owner = strtolower($set_key_owner);
        $tracked_value = $owner === 'ip' ? $device_id : $ip;
        $set_key_value = $owner === 'ip' ? $ip : $device_id;
        $set_key = "{$set_key_owner}:{$set_key_value}:{$value_to_track}s";
        $block_key = "{$set_key_owner}:{$set_key_value}:blocked";
        $rotationType = $owner === 'ip' ? 'DEVICE_ID_ROTATION' : 'IP_ROTATION';
    
        if ($this->redis_conn->exists($block_key)) {
            Responder::respondTooManyRequests("Too many {$this->camelCaseToSpaced($value_to_track)} switches. Try again later.", $block_window);
            exit;
        }
    
        // Add the device ID to the set
        $this->redis_conn->sAdd($set_key, $tracked_value);
        // Set expiry on the set, expire once window ends
        $this->redis_conn->expire($set_key, $window);
        // Count how many unique device IDs were seen
        $count = $this->redis_conn->sCard($set_key);
        
        // If more device IDs changes than allowed, block IP for the time needed
        if ((int) $count > $max_count) {
            $minutes = ceil($block_window / 60);
            $label = (int) $minutes === 1 ? 'minute' : 'minutes';

            $this->redis_conn->setex($block_key, $block_window, 1);
            $this->redis_conn->del($set_key);

            $actor = $owner === 'ip' ? "IP {$ip}" : "Device ID {$device_id}";
            Responder::respondTooManyRequests("Too many {$this->camelCaseToSpaced($value_to_track)}s detected. You have been blocked for {$minutes} {$label}.", $block_window);
            ErrorHandler::logAudit("{$rotationType} -> {$actor} blocked for {$value_to_track} rotation. Seen {$set_key_owner}s: {$count} (limit: {$max_count})");

            exit;
        }
    }

    public function detectDeviceIdRotation(string $set_key_owner, string $value_to_track, string $ip, string $device_id, int $window = 300, int $max_ids = 5, int $block_window = 300): void {
        $this->detectRotation($set_key_owner, $value_to_track, $ip, $device_id, $window, $max_ids, $block_window);
    }

    public function detectIpRotation(string $set_key_owner, string $value_to_track, string $ip, string $device_id, int $window = 900, int $max_ips = 3, int $block_window = 900): void {
        $this->detectRotation($set_key_owner, $value_to_track, $ip, $device_id, $window, $max_ips, $block_window);
    }
}
