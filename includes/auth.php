<?php
// includes/auth.php — Session-based authentication helpers

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function currentUser(): array|null {
    startSession();
    if (empty($_SESSION['user_id'])) return null;

    // Re-fetch from DB on each request (ensures bans apply immediately)
    static $cache = null;
    if ($cache !== null) return $cache;

    $user = Database::fetchOne(
        'SELECT id, full_name, email, role, is_banned, trust_score, avatar
         FROM users WHERE id = ?',
        [$_SESSION['user_id']]
    );

    if (!$user || $user['is_banned']) {
        sessionLogout();
        return null;
    }

    $cache = $user;
    return $user;
}

function isLoggedIn(): bool {
    return currentUser() !== null;
}

function isAdmin(): bool {
    $user = currentUser();
    return $user && $user['role'] === 'admin';
}

function requireLogin(string $redirect = '/pages/login.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . $redirect);
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function sessionLogin(int $userId): void {
    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function sessionLogout(): void {
    startSession();
    $_SESSION = [];
    session_destroy();
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

function isValidUMUEmail(string $email): bool {
    return str_ends_with(strtolower(trim($email)), '@' . UNIVERSITY_DOMAIN);
}

// CSRF token helpers
function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return hash_equals(csrfToken(), $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}
