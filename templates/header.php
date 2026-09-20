<?php
// templates/header.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

$currentUser = Auth::user();
$flash = Helpers::getFlash();
$currentRole = $currentUser['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?><?= APP_NAME ?></title>
    <!-- Google Fonts Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <!-- Tabler Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <!-- Main Style -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <!-- App Engine & Toast Notification -->
    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</head>
<body class="page-<?= htmlspecialchars($activePage ?? 'default') ?>">

<div class="app-container">
    <!-- Sidebar Navigation (Desktop Fixed / Mobile Offcanvas Drawer) -->
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="app-main">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div style="display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1;">
                <div class="page-title" style="min-width: 0; flex: 1;">
                    <h1><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard' ?></h1>
                    <p><?= isset($pageSubtitle) ? htmlspecialchars($pageSubtitle) : Helpers::formatTanggalIndo(date('Y-m-d')) ?></p>
                </div>
            </div>
            <div class="nav-actions" style="display: flex; align-items: center; gap: 8px;">
                <div class="user-meta" style="text-align: right;">
                    <div style="font-weight: 600; font-size: 0.875rem; color: var(--text-heading);">
                        <?= htmlspecialchars($currentUser['full_name'] ?? 'Pengguna') ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                        <?= htmlspecialchars($currentUser['division_name'] ?? 'Kantor Pusat') ?>
                    </div>
                </div>
                <?php
                $homeUrl = BASE_URL . '/';
                if ($currentRole === 'admin') {
                    $homeUrl = BASE_URL . '/admin/index.php';
                } elseif ($currentRole === 'supervisor') {
                    $homeUrl = BASE_URL . '/supervisor/index.php';
                } elseif ($currentRole === 'operator') {
                    $homeUrl = BASE_URL . '/operator/index.php';
                }
                ?>
                <a href="<?= $homeUrl ?>" class="btn btn-secondary btn-nav-home" title="Kembali ke Beranda" style="height: 36px; border-radius: 10px; padding: 0 12px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; font-size: 0.8125rem; font-weight: 700; color: var(--text-heading);">
                    <i class="ti ti-smart-home" style="font-size: 1.2rem; color: var(--primary);"></i>
                    <span class="d-none-mobile">Beranda</span>
                </a>
                <button type="button" onclick="openSettingsDrawer('main')" class="btn btn-secondary" style="width: 36px; height: 36px; border-radius: 10px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Pengaturan Akun & About">
                    <i class="ti ti-settings" style="font-size: 1.15rem;"></i>
                </button>
            </div>
        </header>

        <div class="content-body">
            <?php if ($flash): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showToast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
                    });
                </script>
            <?php endif; ?>

