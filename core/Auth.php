<?php
// core/Auth.php
require_once __DIR__ . '/Database.php';

class Auth {
    public static function check(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.*, d.name as division_name, d.code as division_code 
            FROM users u 
            LEFT JOIN divisions d ON u.division_id = d.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string {
        return $_SESSION['user_role'] ?? null;
    }

    public static function login(string $username, string $password): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([trim($username)]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            return true;
        }
        return false;
    }

    // Quick switch untuk testing / presentasi demo
    public static function quickLogin(int $userId): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            return true;
        }
        return false;
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function isSuperAdmin(): bool {
        return (self::role() === 'superadmin');
    }

    public static function requireRole(array $allowedRoles): void {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }

        $currentRole = self::role();

        // SUPERADMIN memiliki hak akses mutlak ke SELURUH halaman sistem
        if ($currentRole === 'superadmin') {
            return;
        }

        if (!in_array($currentRole, $allowedRoles)) {
            // Redirect ke halaman default masing-masing role
            if ($currentRole === 'admin') {
                header('Location: ' . BASE_URL . '/admin/index.php');
            } elseif ($currentRole === 'supervisor') {
                header('Location: ' . BASE_URL . '/supervisor/index.php');
            } else {
                header('Location: ' . BASE_URL . '/operator/index.php');
            }
            exit;
        }
    }
}
