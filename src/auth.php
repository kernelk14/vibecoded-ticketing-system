<?php

declare(strict_types=1);

function startSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function currentUser(): ?array
{
    startSession();
    return $_SESSION['user'] ?? null;
}

function isAdmin(array $user): bool
{
    return ($user['role'] ?? '') === 'ADMIN';
}

function loginUser(string $email, string $password): bool
{
    $stmt = db()->prepare(
        'SELECT id, name, email, password, role
         FROM User
         WHERE email = :email
         LIMIT 1'
    );
    $stmt->execute(['email' => strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password'])) {
        return false;
    }

    startSession();
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];

    return true;
}

function logoutUser(): void
{
    startSession();
    $_SESSION = [];
    session_destroy();
}
