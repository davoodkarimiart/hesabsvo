<?php

declare(strict_types=1);

final class Auth {
    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function check(): bool { return !empty($_SESSION['user']); }
    public static function requireLogin(): void { if (!self::check()) redirect('index.php?page=login'); }
    public static function requireDeveloper(): void {
        self::requireLogin();
        if (($_SESSION['user']['role'] ?? '') !== 'developer') {
            http_response_code(403);
            exit('دسترسی Developer لازم است.');
        }
    }

    public static function attempt(string $username, string $password): bool {
        $pdo = Database::connection();
        if (self::tooManyAttempts($username)) {
            Logger::audit('auth.rate_limited', ['username'=>$username]);
            throw new RuntimeException('تلاش ورود بیش از حد مجاز است. چند دقیقه بعد دوباره امتحان کنید.');
        }
        $stmt = $pdo->prepare('SELECT id, username, password_hash, role, active FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        $ok = $user && (int)$user['active'] === 1 && password_verify($password, $user['password_hash']);
        self::recordAttempt($username, $ok);
        if (!$ok) {
            Logger::audit('auth.failed', ['username'=>$username]);
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>(int)$user['id'],'username'=>$user['username'],'role'=>$user['role']];
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        Logger::audit('auth.login', ['username'=>$username]);
        return true;
    }

    public static function logout(): void {
        if (self::check()) Logger::audit('auth.logout', ['username'=>$_SESSION['user']['username'] ?? null]);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    private static function tooManyAttempts(string $username): bool {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 30 MINUTE)')->execute();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND ip_hash = ? AND success = 0 AND attempted_at >= (NOW() - INTERVAL 10 MINUTE)');
        $stmt->execute([$username, ip_hash()]);
        return (int)$stmt->fetchColumn() >= 5;
    }

    private static function recordAttempt(string $username, bool $success): void {
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO login_attempts(username, ip_hash, success, attempted_at) VALUES(?,?,?,NOW())')->execute([$username, ip_hash(), $success ? 1 : 0]);
    }
}
