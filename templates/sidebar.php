<?php
// templates/sidebar.php
$activePage = $activePage ?? '';
$role = $currentUser['role'] ?? 'operator';

// Ambil jumlah pending approval jika peran supervisor / admin / superadmin
$sidebarPending = 0;
if ($role === 'supervisor' || $role === 'admin' || $role === 'superadmin') {
    try {
        $dbSidebar = Database::getConnection();
        if ($role === 'admin' || $role === 'superadmin') {
            $sidebarPending = (int)$dbSidebar->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'")->fetchColumn();
        } else {
            $stSp = $dbSidebar->prepare("SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id = u.id WHERE u.division_id = ? AND l.status = 'pending'");
            $stSp->execute([(int)($currentUser['division_id'] ?? 1)]);
            $sidebarPending = (int)$stSp->fetchColumn();
        }
    } catch (\Throwable $e) {}
}
?>
<!-- Mobile Sidebar Backdrop Overlay -->
<div class="mobile-sidebar-backdrop" id="mobileSidebarBackdrop" onclick="closeMobileSidebar()"></div>

<aside class="app-sidebar">
    <!-- Header Sidebar dengan Brand & Tombol Tutup Mobile -->
    <div class="sidebar-header" style="justify-content: space-between; position: relative;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="brand-logo-icon">
                <i class="ti ti-calendar-time"></i>
            </div>
            <div>
                <div class="brand-name">Schedule IEG</div>
                <div class="brand-sub">Shift & Kehadiran</div>
            </div>
        </div>
        <!-- Tombol Tutup Khusus Layar Mobile -->
        <button type="button" class="sidebar-mobile-close-btn" onclick="closeMobileSidebar()" title="Tutup Menu">
            <i class="ti ti-x"></i>
        </button>
    </div>

    <!-- Active User Card dengan Ring Gradient & Status Online -->
    <div class="sidebar-user">
        <div class="user-avatar">
            <div class="user-avatar-inner" style="<?= (!empty($currentUser['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $currentUser['avatar'])) ? 'background: transparent; padding: 0;' : '' ?>">
                <?php if (!empty($currentUser['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $currentUser['avatar'])): ?>
                    <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($currentUser['avatar']) ?>" alt="Foto" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                <?php else: ?>
                    <?= strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)) ?>
                <?php endif; ?>
            </div>
            <span class="status-dot" title="Online"></span>
        </div>
        <div class="user-info">
            <div class="user-name" title="<?= htmlspecialchars($currentUser['full_name'] ?? '') ?>">
                <?= htmlspecialchars($currentUser['full_name'] ?? 'User') ?>
            </div>
            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <span class="user-role-badge <?= htmlspecialchars($role) ?>">
                    <?= strtoupper(htmlspecialchars($role)) ?>
                </span>
                <?php if (!empty($currentUser['division_name'])): ?>
                    <span style="font-size: 0.65rem; color: #64748b; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90px;" title="<?= htmlspecialchars($currentUser['division_name']) ?>">
                        <?= htmlspecialchars($currentUser['division_name']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Navigasi Menu Berwarna & Rapi -->
    <nav class="sidebar-nav">
        <?php if ($role === 'superadmin' || $role === 'admin'): ?>
            <!-- ================= MENU SUPERADMIN & ADMIN ================= -->
            <div class="nav-category">
                <?= $role === 'superadmin' ? '👑 Superadmin Panel' : 'Administrasi Utama' ?>
            </div>

            <a href="<?= BASE_URL ?>/admin/index.php" class="nav-item <?= $activePage === 'admin_dashboard' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-indigo">
                    <i class="ti ti-layout-dashboard"></i>
                </div>
                <span class="nav-item-text">Dashboard Utama</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/admin/jadwal.php" class="nav-item <?= $activePage === 'admin_jadwal' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-violet">
                    <i class="ti ti-calendar-month"></i>
                </div>
                <span class="nav-item-text">Atur Jadwal Shift</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/admin/monitoring.php" class="nav-item <?= $activePage === 'admin_monitoring' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-cyan">
                    <i class="ti ti-users-group"></i>
                </div>
                <span class="nav-item-text">Monitoring Presensi</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/admin/izin.php" class="nav-item <?= $activePage === 'admin_izin' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-rose">
                    <i class="ti ti-checklist"></i>
                </div>
                <span class="nav-item-text">Approval Izin</span>
                <?php if ($sidebarPending > 0): ?>
                    <span class="nav-item-badge"><?= $sidebarPending ?></span>
                <?php else: ?>
                    <i class="ti ti-chevron-right nav-item-arrow"></i>
                <?php endif; ?>
            </a>

            <div class="nav-category">Master Data</div>

            <a href="<?= BASE_URL ?>/admin/karyawan.php" class="nav-item <?= $activePage === 'admin_karyawan' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-blue">
                    <i class="ti ti-id-badge-2"></i>
                </div>
                <span class="nav-item-text">Karyawan & Divisi</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/admin/rekap.php" class="nav-item <?= $activePage === 'admin_rekap' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-emerald">
                    <i class="ti ti-file-analytics"></i>
                </div>
                <span class="nav-item-text">Rekap Kehadiran</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/admin/menu_operator.php" class="nav-item <?= $activePage === 'admin_menu_operator' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-amber">
                    <i class="ti ti-toggle-right"></i>
                </div>
                <span class="nav-item-text">Pengaturan Menu</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <?php if ($role === 'superadmin'): ?>
                <div class="nav-category" style="color: #b45309;">Akses Cepat</div>
                <a href="<?= BASE_URL ?>/supervisor/index.php" class="nav-item">
                    <div class="nav-item-icon icon-amber">
                        <i class="ti ti-user-check"></i>
                    </div>
                    <span class="nav-item-text">Mode Supervisor</span>
                    <i class="ti ti-chevron-right nav-item-arrow"></i>
                </a>
                <a href="<?= BASE_URL ?>/operator/index.php" class="nav-item">
                    <div class="nav-item-icon icon-blue">
                        <i class="ti ti-user"></i>
                    </div>
                    <span class="nav-item-text">Mode Operator</span>
                    <i class="ti ti-chevron-right nav-item-arrow"></i>
                </a>
            <?php endif; ?>

        <?php elseif ($role === 'supervisor'): ?>
            <!-- ================= MENU SUPERVISOR ================= -->
            <div class="nav-category">Divisi <?= htmlspecialchars($currentUser['division_name'] ?? 'Tim') ?></div>

            <a href="<?= BASE_URL ?>/supervisor/index.php" class="nav-item <?= $activePage === 'spv_dashboard' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-indigo">
                    <i class="ti ti-layout-dashboard"></i>
                </div>
                <span class="nav-item-text">Dashboard Divisi</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/supervisor/izin.php" class="nav-item <?= $activePage === 'spv_izin' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-rose">
                    <i class="ti ti-clipboard-check"></i>
                </div>
                <span class="nav-item-text">Approval Izin Tim</span>
                <?php if ($sidebarPending > 0): ?>
                    <span class="nav-item-badge"><?= $sidebarPending ?></span>
                <?php else: ?>
                    <i class="ti ti-chevron-right nav-item-arrow"></i>
                <?php endif; ?>
            </a>

            <a href="<?= BASE_URL ?>/supervisor/jadwal.php" class="nav-item <?= $activePage === 'spv_jadwal' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-violet">
                    <i class="ti ti-calendar-event"></i>
                </div>
                <span class="nav-item-text">Jadwal Shift Tim</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/supervisor/monitoring.php" class="nav-item <?= $activePage === 'spv_monitoring' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-cyan">
                    <i class="ti ti-eye-check"></i>
                </div>
                <span class="nav-item-text">Monitoring Presensi</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <div class="nav-category">Layanan Mandiri</div>

            <a href="<?= BASE_URL ?>/operator/index.php" class="nav-item <?= $activePage === 'op_dashboard' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-blue">
                    <i class="ti ti-smart-home"></i>
                </div>
                <span class="nav-item-text">Beranda Saya</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/operator/absensi.php" class="nav-item <?= $activePage === 'op_absensi' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-emerald">
                    <i class="ti ti-fingerprint"></i>
                </div>
                <span class="nav-item-text">Presensi (Clock In/Out)</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/operator/izin.php" class="nav-item <?= $activePage === 'op_izin' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-amber">
                    <i class="ti ti-file-text"></i>
                </div>
                <span class="nav-item-text">Pengajuan Izin</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/operator/profile.php" class="nav-item <?= $activePage === 'op_profile' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-teal">
                    <i class="ti ti-user-circle"></i>
                </div>
                <span class="nav-item-text">Profil & Akun</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

        <?php else: ?>
            <!-- ================= MENU OPERATOR ================= -->
            <div class="nav-category">Portal Karyawan</div>

            <a href="<?= BASE_URL ?>/operator/index.php" class="nav-item <?= $activePage === 'op_dashboard' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-indigo">
                    <i class="ti ti-smart-home"></i>
                </div>
                <span class="nav-item-text">Beranda Saya</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/operator/absensi.php" class="nav-item <?= $activePage === 'op_absensi' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-emerald">
                    <i class="ti ti-fingerprint"></i>
                </div>
                <span class="nav-item-text">Presensi (Clock In/Out)</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/operator/jadwal.php" class="nav-item <?= $activePage === 'op_jadwal' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-violet">
                    <i class="ti ti-calendar-event"></i>
                </div>
                <span class="nav-item-text">Jadwal Shift</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/operator/izin.php" class="nav-item <?= $activePage === 'op_izin' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-rose">
                    <i class="ti ti-file-text"></i>
                </div>
                <span class="nav-item-text">Pengajuan Izin</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/operator/riwayat.php" class="nav-item <?= $activePage === 'op_riwayat' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-cyan">
                    <i class="ti ti-history"></i>
                </div>
                <span class="nav-item-text">Riwayat Kehadiran</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <a href="<?= BASE_URL ?>/operator/karyawan.php" class="nav-item <?= $activePage === 'op_karyawan' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-blue">
                    <i class="ti ti-users"></i>
                </div>
                <span class="nav-item-text">Direktori Karyawan</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>

            <div class="nav-category">Akun & Preferensi</div>

            <a href="<?= BASE_URL ?>/operator/profile.php" class="nav-item <?= $activePage === 'op_profile' ? 'active' : '' ?>">
                <div class="nav-item-icon icon-teal">
                    <i class="ti ti-user-circle"></i>
                </div>
                <span class="nav-item-text">Profil Saya</span>
                <i class="ti ti-chevron-right nav-item-arrow"></i>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Footer Sidebar dengan Tombol Keluar & Versi Aplikasi -->
    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/logout.php" class="sidebar-logout-btn" onclick="return confirm('Apakah Anda yakin ingin keluar?');">
            <i class="ti ti-logout" style="font-size: 1.15rem;"></i>
            <span>Keluar Akun</span>
        </a>
    </div>
</aside>

<script>
function openMobileSidebar() {
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.getElementById('mobileSidebarBackdrop');
    if (sidebar) sidebar.classList.add('mobile-open');
    if (backdrop) backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMobileSidebar() {
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.getElementById('mobileSidebarBackdrop');
    if (sidebar) sidebar.classList.remove('mobile-open');
    if (backdrop) backdrop.classList.remove('active');
    document.body.style.overflow = '';
}

// Tutup sidebar saat klik item navigasi di layar mobile
document.addEventListener('DOMContentLoaded', function() {
    const sidebarItems = document.querySelectorAll('.app-sidebar .nav-item');
    sidebarItems.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeMobileSidebar();
            }
        });
    });
});
</script>
