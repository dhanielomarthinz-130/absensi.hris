<?php
// operator/inbox.php - Talenta Style Inbox & Notifications
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['operator', 'supervisor', 'admin']);

$db = Database::getConnection();
$currentUser = Auth::user();
$userId = (int)$currentUser['id'];

$pageTitle = 'Inbox';
$pageSubtitle = 'Notifikasi & Riwayat Persetujuan';
$activePage = 'op_inbox';

// Ambil riwayat presensi terbaru user
$stAtt = $db->prepare("
    SELECT date, clock_in, clock_out, status
    FROM attendances
    WHERE user_id = ?
    ORDER BY date DESC
    LIMIT 6
");
$stAtt->execute([$userId]);
$recentAtt = $stAtt->fetchAll();

// Ambil riwayat cuti / izin user
$stLeaves = $db->prepare("
    SELECT l.*, u.full_name as approver_name, u.avatar as approver_avatar
    FROM leaves l
    LEFT JOIN users u ON l.approved_by = u.id
    WHERE l.user_id = ?
    ORDER BY l.created_at DESC
    LIMIT 4
");
$stLeaves->execute([$userId]);
$recentLeaves = $stLeaves->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div style="max-width: 540px; margin: 0 auto; padding-bottom: 24px;">
    <!-- 1. TOP HEADER -->
    <div class="talenta-inbox-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1 class="talenta-inbox-title" style="margin: 0;">Inbox</h1>
            <a href="<?= BASE_URL ?>/operator/index.php" style="color: #64748b; font-size: 0.85rem; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                <i class="ti ti-arrow-left"></i> Beranda
            </a>
        </div>
    </div>

    <!-- 2. TABS: Notifikasi | Butuh persetujuan -->
    <div class="talenta-inbox-tabs">
        <button type="button" class="talenta-inbox-tab active" id="tabNotifBtn" onclick="switchInboxTab('notif')">
            Notifikasi
        </button>
        <button type="button" class="talenta-inbox-tab" id="tabApprovalBtn" onclick="switchInboxTab('approval')">
            Butuh persetujuan
        </button>
    </div>

    <!-- 3. TAB CONTENT 1: NOTIFIKASI FEED -->
    <div id="tabContentNotif" class="talenta-inbox-list">
        <?php 
        $hasItems = false;

        // Tampilkan notifikasi presensi & cuti nyata dari database
        if (!empty($recentAtt)): 
            foreach ($recentAtt as $att):
                $hasItems = true;
                $formattedDate = Helpers::formatTanggalIndo($att['date'], false);
                
                // Clock Out Notif jika ada
                if (!empty($att['clock_out'])): ?>
                    <a href="<?= BASE_URL ?>/operator/riwayat.php" class="talenta-inbox-card">
                        <div class="talenta-inbox-icon talenta-red">
                            <i class="ti ti-chevron-right" style="transform: scaleX(-1); font-size: 1.25rem;"></i>
                        </div>
                        <div class="talenta-inbox-details">
                            <div class="talenta-inbox-sender">Schedule IEG</div>
                            <div class="talenta-inbox-message">Presensi keluar (Clock Out) berhasil disubmit (<?= substr($att['clock_out'], 0, 5) ?> WIB)</div>
                            <div class="talenta-inbox-sublabel">Status presensi diperbarui &bull; <?= $formattedDate ?></div>
                        </div>
                        <i class="ti ti-chevron-right" style="color: #94a3b8; font-size: 1.15rem;"></i>
                    </a>
                <?php endif;

                // Clock In Notif jika ada
                if (!empty($att['clock_in'])): ?>
                    <a href="<?= BASE_URL ?>/operator/riwayat.php" class="talenta-inbox-card">
                        <div class="talenta-inbox-icon talenta-red">
                            <i class="ti ti-chevron-right" style="transform: scaleX(-1); font-size: 1.25rem;"></i>
                        </div>
                        <div class="talenta-inbox-details">
                            <div class="talenta-inbox-sender">Schedule IEG</div>
                            <div class="talenta-inbox-message">Presensi masuk (Clock In) berhasil disubmit (<?= substr($att['clock_in'], 0, 5) ?> WIB)</div>
                            <div class="talenta-inbox-sublabel">Status presensi diperbarui &bull; <?= $formattedDate ?></div>
                        </div>
                        <i class="ti ti-chevron-right" style="color: #94a3b8; font-size: 1.15rem;"></i>
                    </a>
                <?php endif;
            endforeach;
        endif;

        // Tampilkan notifikasi Cuti / Time Off dari DB
        if (!empty($recentLeaves)):
            foreach ($recentLeaves as $lv):
                $hasItems = true;
                $approver = !empty($lv['approver_name']) ? strtoupper($lv['approver_name']) : 'SUPERVISOR';
                $statusText = ($lv['status'] === 'approved') ? 'disetujui' : (($lv['status'] === 'rejected') ? 'ditolak' : 'sedang ditinjau');
                ?>
                <a href="<?= BASE_URL ?>/operator/izin.php" class="talenta-inbox-card">
                    <div class="talenta-inbox-icon" style="background: #e2e8f0;">
                        <?php if (!empty($lv['approver_avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $lv['approver_avatar'])): ?>
                            <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($lv['approver_avatar']) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; background: #dc2626; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem;">
                                <?= strtoupper(substr($approver, 0, 2)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="talenta-inbox-details">
                        <div class="talenta-inbox-sender"><?= htmlspecialchars($approver) ?></div>
                        <div class="talenta-inbox-message">Pengajuan cuti/izin Anda tanggal <?= date('d M Y', strtotime($lv['start_date'])) ?> telah <?= $statusText ?></div>
                        <div class="talenta-inbox-sublabel">Pengajuan Izin/Cuti &bull; <?= ucfirst($statusText) ?></div>
                    </div>
                    <i class="ti ti-chevron-right" style="color: #94a3b8; font-size: 1.15rem;"></i>
                </a>
            <?php endforeach;
        endif;

        // Jika belum ada data sama sekali, tampilkan default items Schedule IEG
        if (!$hasItems): ?>
            <a href="javascript:void(0)" class="talenta-inbox-card">
                <div class="talenta-inbox-icon talenta-red">
                    <i class="ti ti-chevron-right" style="transform: scaleX(-1); font-size: 1.25rem;"></i>
                </div>
                <div class="talenta-inbox-details">
                    <div class="talenta-inbox-sender">Schedule IEG</div>
                    <div class="talenta-inbox-message">Presensi masuk (Clock In) berhasil disubmit</div>
                    <div class="talenta-inbox-sublabel">Status presensi diperbarui &bull; Hari ini</div>
                </div>
                <i class="ti ti-chevron-right" style="color: #94a3b8; font-size: 1.15rem;"></i>
            </a>

            <a href="javascript:void(0)" class="talenta-inbox-card">
                <div class="talenta-inbox-icon" style="background: #e2e8f0;">
                    <div style="width: 100%; height: 100%; background: #dc2626; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem;">
                        SP
                    </div>
                </div>
                <div class="talenta-inbox-details">
                    <div class="talenta-inbox-sender">SUPERVISOR HR</div>
                    <div class="talenta-inbox-message">Pengajuan permohonan izin/cuti kerja Anda telah disetujui</div>
                    <div class="talenta-inbox-sublabel">Pengajuan Izin Disetujui &bull; Schedule IEG</div>
                </div>
                <i class="ti ti-chevron-right" style="color: #94a3b8; font-size: 1.15rem;"></i>
            </a>

            <a href="javascript:void(0)" class="talenta-inbox-card">
                <div class="talenta-inbox-icon talenta-red">
                    <i class="ti ti-chevron-right" style="transform: scaleX(-1); font-size: 1.25rem;"></i>
                </div>
                <div class="talenta-inbox-details">
                    <div class="talenta-inbox-sender">Schedule IEG</div>
                    <div class="talenta-inbox-message">Presensi keluar (Clock Out) berhasil disubmit</div>
                    <div class="talenta-inbox-sublabel">Status presensi diperbarui &bull; Schedule IEG</div>
                </div>
                <i class="ti ti-chevron-right" style="color: #94a3b8; font-size: 1.15rem;"></i>
            </a>

            <a href="javascript:void(0)" class="talenta-inbox-card">
                <div class="talenta-inbox-icon talenta-red">
                    <i class="ti ti-chevron-right" style="transform: scaleX(-1); font-size: 1.25rem;"></i>
                </div>
                <div class="talenta-inbox-details">
                    <div class="talenta-inbox-sender">Schedule IEG</div>
                    <div class="talenta-inbox-message">Roster jadwal shift minggu ini telah diperbarui oleh atasan</div>
                    <div class="talenta-inbox-sublabel">Jadwal Shift &bull; Schedule IEG</div>
                </div>
                <i class="ti ti-chevron-right" style="color: #94a3b8; font-size: 1.15rem;"></i>
            </a>
        <?php endif; ?>
    </div>

    <!-- 4. TAB CONTENT 2: BUTUH PERSETUJUAN -->
    <div id="tabContentApproval" class="talenta-inbox-list" style="display: none;">
        <div style="background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; padding: 40px 20px; text-align: center;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 12px;">
                <i class="ti ti-checkbox"></i>
            </div>
            <h4 style="margin: 0 0 6px; font-weight: 800; font-size: 1.05rem; color: #0f172a;">Tidak Ada Pengajuan Tertunda</h4>
            <p style="margin: 0; font-size: 0.825rem; color: #64748b;">Semua pengajuan tim yang membutuhkan persetujuan Anda telah diproses.</p>
        </div>
    </div>
</div>

<!-- Floating Scroll-To-Top Button (Matching Dark Blue Circle from screenshot) -->
<button type="button" class="talenta-scroll-top-btn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="Kembali ke Atas">
    <i class="ti ti-chevron-up" style="font-size: 1.3rem;"></i>
</button>

<script>
function switchInboxTab(tab) {
    const notifBtn = document.getElementById('tabNotifBtn');
    const appBtn = document.getElementById('tabApprovalBtn');
    const notifContent = document.getElementById('tabContentNotif');
    const appContent = document.getElementById('tabContentApproval');

    if (tab === 'notif') {
        notifBtn.classList.add('active');
        appBtn.classList.remove('active');
        notifContent.style.display = 'flex';
        appContent.style.display = 'none';
    } else {
        appBtn.classList.add('active');
        notifBtn.classList.remove('active');
        appContent.style.display = 'block';
        notifContent.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
