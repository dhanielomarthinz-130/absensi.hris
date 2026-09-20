<?php
// operator/riwayat.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['operator', 'supervisor', 'admin']);

$db = Database::getConnection();
$currentUser = Auth::user();
$userId = $currentUser['id'];
$activePage = 'op_riwayat';

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

$sql = "
    SELECT 
        s.date, s.shift_id, sh.name as shift_name, sh.start_time, sh.end_time, sh.color as shift_color,
        a.clock_in, a.clock_out, a.status as attendance_status, a.notes as attendance_notes,
        l.type as leave_type, l.reason as leave_reason, l.doctor_letter
    FROM schedules s
    JOIN shifts sh ON s.shift_id = sh.id
    LEFT JOIN attendances a ON a.user_id = s.user_id AND a.date = s.date
    LEFT JOIN leaves l ON l.user_id = s.user_id AND l.status = 'approved' AND s.date BETWEEN l.start_date AND l.end_date
    WHERE s.user_id = ? AND strftime('%Y-%m', s.date) = ?
    ORDER BY s.date DESC
";
$stmt = $db->prepare($sql);
$stmt->execute([$userId, $month]);
$history = $stmt->fetchAll();

// Hitung rekap
$totalHadir = count(array_filter($history, fn($h) => in_array($h['attendance_status'], ['hadir', 'terlambat'])));
$totalTerlambat = count(array_filter($history, fn($h) => $h['attendance_status'] === 'terlambat'));
$totalSakit = count(array_filter($history, fn($h) => $h['leave_type'] === 'sakit'));

$pageTitle = 'Riwayat Presensi';
$pageSubtitle = 'Histori jam kerja & kehadiran';

require_once __DIR__ . '/../templates/header.php';
?>

<div style="max-width: 860px; margin: 0 auto; margin-bottom: 24px;">
    <!-- Top Back / Title Navigation & Month Selector -->
    <div class="premium-page-nav">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="<?= BASE_URL ?>/operator/index.php" class="premium-back-btn" title="Kembali ke Beranda">
                <i class="ti ti-arrow-left" style="font-size: 1.15rem;"></i>
            </a>
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.3px;">Riwayat Presensi</h2>
                <div style="font-size: 0.725rem; color: #64748b; margin-top: 1px;">
                    <i class="ti ti-history"></i> Histori Jam Kerja & Rekapitulasi
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 6px;">
            <form method="GET" action="" style="display: flex; gap: 6px; align-items: center; margin: 0;">
                <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>" onchange="this.form.submit()" style="font-size: 0.775rem; padding: 6px 10px; border-radius: 10px; height: 36px; border: 1px solid #cbd5e1;">
                <button type="button" class="btn btn-secondary" onclick="window.print()" style="border-radius: 10px; height: 36px; padding: 0 10px; font-size: 0.8rem;" title="Cetak Rekap">
                    <i class="ti ti-printer"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- 3 Rekap Stats Chips (Full-Color Gradient Cards) -->
    <div class="premium-stat-grid-3">
        <div class="stat-card-gradient-emerald">
            <div class="stat-card-icon-glass">
                <i class="ti ti-circle-check"></i>
            </div>
            <div style="font-size: 0.65rem; color: #a7f3d0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;">Hadir</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: #ffffff; line-height: 1.15; margin-top: 2px;">
                <?= $totalHadir ?> <span style="font-size: 0.725rem; font-weight: 600; opacity: 0.9;">Hari</span>
            </div>
        </div>

        <div class="stat-card-gradient-amber">
            <div class="stat-card-icon-glass">
                <i class="ti ti-clock-alert"></i>
            </div>
            <div style="font-size: 0.65rem; color: #ffedd5; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;">Terlambat</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: #ffffff; line-height: 1.15; margin-top: 2px;">
                <?= $totalTerlambat ?> <span style="font-size: 0.725rem; font-weight: 600; opacity: 0.9;">x</span>
            </div>
        </div>

        <div class="stat-card-gradient-rose">
            <div class="stat-card-icon-glass">
                <i class="ti ti-file-medical"></i>
            </div>
            <div style="font-size: 0.65rem; color: #fecdd3; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;">Sakit / Izin</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: #ffffff; line-height: 1.15; margin-top: 2px;">
                <?= $totalSakit ?> <span style="font-size: 0.725rem; font-weight: 600; opacity: 0.9;">Hari</span>
            </div>
        </div>
    </div>

    <!-- Timeline Card List Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <div style="font-size: 0.75rem; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.4px;">
            Daftar Presensi Harian
        </div>
        <span style="font-size: 0.7rem; color: #64748b; font-weight: 600;"><?= count($history) ?> Catatan</span>
    </div>

    <?php if (empty($history)): ?>
        <div style="background: #ffffff; border-radius: 16px; padding: 32px 20px; text-align: center; color: #94a3b8; border: 1px solid #f1f5f9; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
            <i class="ti ti-calendar-off" style="font-size: 2rem; display: block; margin-bottom: 6px;"></i>
            <div style="font-size: 0.8rem;">Tidak ada riwayat kehadiran pada bulan ini.</div>
        </div>
    <?php endif; ?>

    <div style="display: flex; flex-direction: column; gap: 8px;">
        <?php foreach ($history as $h): 
            $hasIn = !empty($h['clock_in']);
            $hasOut = !empty($h['clock_out']);
            
            // Tentukan status border color
            $statusColor = '#94a3b8'; // Default / Belum Hadir
            if ($h['attendance_status'] === 'hadir') {
                $statusColor = '#10b981';
            } elseif ($h['attendance_status'] === 'terlambat') {
                $statusColor = '#f59e0b';
            } elseif (in_array($h['attendance_status'], ['sakit', 'izin']) || !empty($h['leave_type'])) {
                $statusColor = '#f43f5e';
            }
        ?>
            <div style="background: #ffffff; border-radius: 14px; padding: 11px 14px; border: 1px solid #e2e8f0; border-left: 4.5px solid <?= $statusColor ?>; box-shadow: 0 2px 6px rgba(0,0,0,0.02); transition: transform 0.15s;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <div>
                        <strong style="font-size: 0.85rem; color: #1e293b;"><?= Helpers::formatTanggalIndo($h['date']) ?></strong>
                    </div>
                    <div>
                        <?php 
                        if (!empty($h['attendance_status'])) {
                            echo Helpers::statusBadge($h['attendance_status']);
                        } elseif (!empty($h['leave_type'])) {
                            echo Helpers::statusBadge($h['leave_type']);
                        } else {
                            echo '<span class="status-badge status-alpha" style="font-size: 0.65rem; padding: 2px 6px;">Belum Hadir</span>';
                        }
                        ?>
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 7px;">
                    <span class="badge-shift" style="background: <?= htmlspecialchars($h['shift_color']) ?>15; color: <?= htmlspecialchars($h['shift_color']) ?>; font-size: 0.7rem; padding: 2px 8px; border-radius: 6px;">
                        <?= htmlspecialchars($h['shift_name']) ?>
                    </span>
                    <span style="font-size: 0.7rem; color: #64748b;">
                        <?= htmlspecialchars($h['start_time']) ?> - <?= htmlspecialchars($h['end_time']) ?> WIB
                    </span>
                </div>

                <!-- Clock In / Clock Out Strip -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: #f8fafc; border-radius: 8px; padding: 6px 10px; border: 1px solid #f1f5f9;">
                    <div>
                        <span style="font-size: 0.625rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Masuk:</span>
                        <span style="font-family: monospace; font-size: 0.85rem; font-weight: 700; color: <?= $hasIn ? '#059669' : '#94a3b8' ?>; margin-left: 4px;">
                            <?= $hasIn ? htmlspecialchars($h['clock_in']) : '--:--' ?>
                        </span>
                    </div>
                    <div>
                        <span style="font-size: 0.625rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Pulang:</span>
                        <span style="font-family: monospace; font-size: 0.85rem; font-weight: 700; color: <?= $hasOut ? '#2563eb' : '#94a3b8' ?>; margin-left: 4px;">
                            <?= $hasOut ? htmlspecialchars($h['clock_out']) : '--:--' ?>
                        </span>
                    </div>
                </div>

                <?php if ($h['leave_reason'] || $h['doctor_letter'] || $h['attendance_notes']): ?>
                    <div style="margin-top: 7px; border-top: 1px dashed #e2e8f0; padding-top: 6px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.7rem; color: #64748b;">
                            <?= htmlspecialchars($h['leave_reason'] ?: $h['attendance_notes']) ?>
                        </span>
                        <?php if ($h['doctor_letter']): ?>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="viewDoctorLetter('<?= UPLOAD_URL . htmlspecialchars($h['doctor_letter']) ?>', '<?= htmlspecialchars(addslashes($currentUser['full_name'])) ?>', '<?= $h['date'] ?>')" style="font-size: 0.65rem; padding: 2px 7px; color: #dc2626; border-color: #fecaca; background: #fff5f5; border-radius: 6px;">
                                <i class="ti ti-file-certificate"></i> Surat Dokter
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

