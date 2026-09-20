<?php
// templates/operator_bottom_dock.php
$activePage = $activePage ?? 'op_dashboard';
$dockUser = Auth::user();
$dockRole = $dockUser['role'] ?? 'operator';

// Count pending approvals for supervisor/admin
$dockPending = 0;
if (in_array($dockRole, ['supervisor', 'admin', 'superadmin'])) {
    try {
        $dbDock = Database::getConnection();
        if (in_array($dockRole, ['admin', 'superadmin'])) {
            $dockPending = (int)$dbDock->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'")->fetchColumn();
        } else {
            $stDock = $dbDock->prepare("SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id = u.id WHERE u.division_id = ? AND l.status = 'pending'");
            $stDock->execute([(int)($dockUser['division_id'] ?? 1)]);
            $dockPending = (int)$stDock->fetchColumn();
        }
    } catch (\Throwable $e) {}
}
$isSpvOrAdmin = in_array($dockRole, ['supervisor', 'admin', 'superadmin']);
?>
<!-- TALENTA MOBILE BOTTOM NAVIGATION DOCK -->
<nav class="talenta-mobile-dock">
    <a href="<?= BASE_URL ?>/operator/index.php" class="talenta-dock-tab <?= in_array($activePage, ['op_dashboard', 'admin_dashboard', 'spv_dashboard']) ? 'active' : '' ?>">
        <i class="ti ti-smart-home"></i>
        <span>Beranda</span>
    </a>

    <?php if ($isSpvOrAdmin): ?>
        <!-- Approval tab for supervisor/admin -->
        <a href="<?= BASE_URL ?>/supervisor/izin.php" class="talenta-dock-tab <?= $activePage === 'spv_izin' ? 'active' : '' ?>" style="position: relative;">
            <i class="ti ti-clipboard-check"></i>
            <?php if ($dockPending > 0): ?>
                <span class="dock-approval-badge"><?= $dockPending > 9 ? '9+' : $dockPending ?></span>
            <?php endif; ?>
            <span>Approval</span>
        </a>
    <?php else: ?>
        <a href="<?= BASE_URL ?>/operator/karyawan.php" class="talenta-dock-tab <?= in_array($activePage, ['op_karyawan', 'admin_karyawan']) ? 'active' : '' ?>">
            <i class="ti ti-users"></i>
            <span>Karyawan</span>
        </a>
    <?php endif; ?>

    <button type="button" onclick="openAjukanSheet(event)" class="talenta-dock-tab talenta-dock-absen <?= in_array($activePage, ['op_izin', 'op_absensi']) ? 'active' : '' ?>" title="Ajukan untuk...">
        <div class="talenta-dock-absen-btn" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
            <i class="ti ti-plus" style="font-size: 1.4rem;"></i>
        </div>
        <span>Pengajuan</span>
    </button>
    <a href="<?= BASE_URL ?>/operator/inbox.php" class="talenta-dock-tab <?= $activePage === 'op_inbox' ? 'active' : '' ?>">
        <i class="ti ti-bell"></i>
        <span>Inbox</span>
    </a>
    <a href="<?= BASE_URL ?>/operator/profile.php" class="talenta-dock-tab <?= $activePage === 'op_profile' ? 'active' : '' ?>">
        <?php if (!empty($dockUser['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $dockUser['avatar'])): ?>
            <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($dockUser['avatar']) ?>" alt="Akun" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover; margin-bottom: 2px;">
        <?php else: ?>
            <i class="ti ti-user"></i>
        <?php endif; ?>
        <span>Akun</span>
    </a>
</nav>


<!-- TALENTA "AJUKAN UNTUK" BOTTOM SHEET MODAL -->
<div id="talentaAjukanSheet" class="talenta-sheet-backdrop" onclick="closeAjukanSheet(event)">
    <div class="talenta-sheet-content" onclick="event.stopPropagation()">
        <div class="talenta-sheet-handle"></div>
        <div class="talenta-sheet-header">
            <h3>Ajukan untuk</h3>
            <button type="button" class="talenta-sheet-close" onclick="closeAjukanSheet(event)" aria-label="Tutup">
                <i class="ti ti-x"></i>
            </button>
        </div>
        <div class="talenta-sheet-body">
            <a href="<?= BASE_URL ?>/operator/izin.php?type=cuti" class="talenta-sheet-item">
                <i class="ti ti-clock"></i>
                <span>Cuti & Izin Kerja</span>
                <i class="ti ti-chevron-right talenta-sheet-arrow"></i>
            </a>
            <a href="<?= BASE_URL ?>/operator/absensi.php" class="talenta-sheet-item">
                <i class="ti ti-map-pin"></i>
                <span>Absensi</span>
                <i class="ti ti-chevron-right talenta-sheet-arrow"></i>
            </a>
            <a href="<?= BASE_URL ?>/supervisor/izin.php" class="talenta-sheet-item">
                <i class="ti ti-clipboard-check"></i>
                <span>Approval</span>
                <i class="ti ti-chevron-right talenta-sheet-arrow"></i>
            </a>
            <a href="<?= BASE_URL ?>/operator/profile.php" class="talenta-sheet-item">
                <i class="ti ti-user-circle"></i>
                <span>Perubahan Data</span>
                <i class="ti ti-chevron-right talenta-sheet-arrow"></i>
            </a>
        </div>
    </div>
</div>

<script>
function openAjukanSheet(e) {
    if (e) e.preventDefault();
    const sheet = document.getElementById('talentaAjukanSheet');
    if (sheet) {
        sheet.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}
function closeAjukanSheet(e) {
    if (e) e.stopPropagation();
    const sheet = document.getElementById('talentaAjukanSheet');
    if (sheet) {
        sheet.classList.remove('active');
        document.body.style.overflow = '';
    }
}
</script>
