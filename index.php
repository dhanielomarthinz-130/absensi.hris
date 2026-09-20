<?php
// index.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Helpers.php';

// Pastikan DB terinisialisasi
Database::getConnection();

// Jika sudah login, alihkan ke dashboard role masing-masing
if (Auth::check()) {
    $role = Auth::role();
    if ($role === 'admin') {
        header('Location: ' . BASE_URL . '/admin/index.php');
    } elseif ($role === 'supervisor') {
        header('Location: ' . BASE_URL . '/supervisor/index.php');
    } else {
        header('Location: ' . BASE_URL . '/operator/index.php');
    }
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (Auth::login($username, $password)) {
        $role = Auth::role();
        if ($role === 'admin') {
            header('Location: ' . BASE_URL . '/admin/index.php');
        } elseif ($role === 'supervisor') {
            header('Location: ' . BASE_URL . '/supervisor/index.php');
        } else {
            header('Location: ' . BASE_URL . '/operator/index.php');
        }
        exit;
    } else {
        $error = 'Username atau kata sandi tidak cocok. Silakan coba lagi.';
    }
}

$flash = Helpers::getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .login-header {
            padding: 32px 32px 24px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }
        .login-logo {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.8rem;
            margin-bottom: 16px;
            box-shadow: 0 8px 16px rgba(79, 70, 229, 0.3);
        }
        .login-body {
            padding: 28px 32px 32px;
        }
        .quick-accounts {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px dashed var(--border-color);
        }
        .quick-accounts-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .quick-btn-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="login-logo">
            <i class="ti ti-calendar-time"></i>
        </div>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--text-heading); margin-bottom: 4px;"><?= APP_NAME ?></h2>
        <p style="font-size: 0.85rem; color: var(--text-muted);"><?= APP_TAGLINE ?></p>
    </div>

    <div class="login-body">
        <?php if ($error): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showToast(<?= json_encode($error) ?>, 'error', 'Gagal Masuk');
                });
            </script>
        <?php endif; ?>

        <?php if ($flash): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showToast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
                });
            </script>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="username">Username / ID Pegawai</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Contoh: admin / spv_produksi / operator1" required autofocus>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password Anda" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 0.95rem;">
                <i class="ti ti-login"></i> Masuk ke Portal
            </button>
        </form>

        <!-- Akun Demo Cepat -->
        <div class="quick-accounts">
            <div class="quick-accounts-title">
                <i class="ti ti-click"></i> Akses Instan Akun Pengguna
            </div>
            <div class="quick-btn-grid">
                <a href="<?= BASE_URL ?>/quick_switch.php?username=Daniel" class="btn btn-sm" style="font-size: 0.75rem; justify-content: flex-start; grid-column: span 2; background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; font-weight: 700;">
                    <i class="ti ti-crown" style="color: #b45309; font-size: 1rem;"></i> Daniel (Superadmin) - User: Daniel / Pass: Dh@niel0
                </a>
                <a href="<?= BASE_URL ?>/quick_switch.php?username=admin" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; justify-content: flex-start;">
                    <i class="ti ti-shield-check" style="color: #ef4444;"></i> Admin HR
                </a>
                <a href="<?= BASE_URL ?>/quick_switch.php?username=spv_produksi" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; justify-content: flex-start;">
                    <i class="ti ti-user-check" style="color: #f59e0b;"></i> SPV Produksi
                </a>
                <a href="<?= BASE_URL ?>/quick_switch.php?username=spv_it" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; justify-content: flex-start;">
                    <i class="ti ti-user-check" style="color: #f59e0b;"></i> SPV IT
                </a>
                <a href="<?= BASE_URL ?>/quick_switch.php?username=operator1" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; justify-content: flex-start;">
                    <i class="ti ti-user" style="color: #3b82f6;"></i> Operator 1 (Rian)
                </a>
                <a href="<?= BASE_URL ?>/quick_switch.php?username=operator2" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; justify-content: flex-start; grid-column: span 2;">
                    <i class="ti ti-file-medical" style="color: #dc2626;"></i> Operator 2 (Siti - Sakit + Surat Dokter)
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
