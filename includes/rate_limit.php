<?php
// includes/rate_limit.php — Brute-force protection helpers

/**
 * Check if an IP is currently rate-limited for an endpoint.
 * @param string $endpoint  Endpoint identifier (e.g., 'login', 'register')
 * @param string $ip        IP address (defaults to $_SERVER['REMOTE_ADDR'])
 * @return array|null       ['remaining' => N, 'reset_at' => timestamp] if locked, null if allowed
 */
function checkRateLimit(string $endpoint, string $ip = ''): array|null {
    if (!$ip) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    if (!$ip) return null; // Can't rate-limit without IP

    try {
        // Check if currently locked out
        $row = Database::fetchOne(
            'SELECT attempt_count, lockout_until FROM rate_limits 
             WHERE ip_address=? AND endpoint=? LIMIT 1',
            [$ip, $endpoint]
        );

        if ($row && $row['lockout_until'] && strtotime($row['lockout_until']) > time()) {
            // Still locked out
            return [
                'remaining' => 0,
                'reset_at' => strtotime($row['lockout_until']),
            ];
        }
    } catch (Throwable $e) {
        error_log("Rate limit check error: " . $e->getMessage());
    }

    return null; // Not locked out
}

/**
 * Record a failed attempt for an endpoint.
 * Locks out after RATE_LIMIT_MAX_ATTEMPTS within RATE_LIMIT_WINDOW seconds.
 * @param string $endpoint  Endpoint identifier (e.g., 'login', 'register')
 * @param string $ip        IP address (defaults to $_SERVER['REMOTE_ADDR'])
 */
function recordFailedAttempt(string $endpoint, string $ip = ''): void {
    if (!$ip) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    if (!$ip) return;

    $maxAttempts = defined('RATE_LIMIT_MAX_ATTEMPTS') ? RATE_LIMIT_MAX_ATTEMPTS : 5;
    $windowSeconds = defined('RATE_LIMIT_WINDOW') ? RATE_LIMIT_WINDOW : 900; // 15 min
    $lockoutSeconds = defined('RATE_LIMIT_LOCKOUT') ? RATE_LIMIT_LOCKOUT : 900;

    try {
        // Get or create rate limit record
        $row = Database::fetchOne(
            'SELECT id, attempt_count, first_attempt_at FROM rate_limits 
             WHERE ip_address=? AND endpoint=? LIMIT 1',
            [$ip, $endpoint]
        );

        if ($row) {
            // Check if outside the window (reset counter)
            $firstAttemptTime = strtotime($row['first_attempt_at']);
            $secondsSinceFirst = time() - $firstAttemptTime;

            if ($secondsSinceFirst > $windowSeconds) {
                // Outside window: reset counter
                Database::query(
                    'UPDATE rate_limits SET attempt_count=1, first_attempt_at=NOW(), lockout_until=NULL 
                     WHERE ip_address=? AND endpoint=?',
                    [$ip, $endpoint]
                );
            } else {
                // Within window: increment
                $newCount = $row['attempt_count'] + 1;
                $lockoutUntil = $newCount >= $maxAttempts 
                    ? date('Y-m-d H:i:s', time() + $lockoutSeconds)
                    : null;

                Database::query(
                    'UPDATE rate_limits SET attempt_count=?, lockout_until=? 
                     WHERE ip_address=? AND endpoint=?',
                    [$newCount, $lockoutUntil, $ip, $endpoint]
                );
            }
        } else {
            // First attempt for this IP/endpoint
            Database::insert(
                'INSERT INTO rate_limits (ip_address, endpoint, attempt_count) VALUES (?,?,?)',
                [$ip, $endpoint, 1]
            );
        }
    } catch (Throwable $e) {
        error_log("Failed attempt recording error: " . $e->getMessage());
    }
}

/**
 * Reset rate limit for an endpoint (call on successful login/registration).
 * @param string $endpoint
 * @param string $ip
 */
function resetRateLimit(string $endpoint, string $ip = ''): void {
    if (!$ip) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    if (!$ip) return;

    try {
        Database::query(
            'DELETE FROM rate_limits WHERE ip_address=? AND endpoint=?',
            [$ip, $endpoint]
        );
    } catch (Throwable $e) {
        error_log("Rate limit reset error: " . $e->getMessage());
    }
}

/**
 * Clean up old rate limit records (older than 1 day).
 * Call this periodically or on cron job.
 */
function cleanupOldRateLimits(): int {
    try {
        $stmt = Database::query(
            'DELETE FROM rate_limits WHERE last_attempt_at < DATE_SUB(NOW(), INTERVAL 1 DAY)'
        );
        return $stmt->rowCount();
    } catch (Throwable $e) {
        error_log("Rate limit cleanup error: " . $e->getMessage());
        return 0;
    }
}
