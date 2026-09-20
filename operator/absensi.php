<?php
// operator/absensi.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['operator', 'supervisor', 'admin']);

$db = Database::getConnection();
$currentUser = Auth::user();
$userId = $currentUser['id'];
$todayStr = date('Y-m-d');
$activePage = 'op_absensi';

// Ambil Jadwal Shift Hari Ini
$sqlSched = "
    SELECT s.*, sh.name as shift_name, sh.start_time, sh.end_time, sh.color as shift_color
    FROM schedules s
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.user_id = ? AND s.date = ?
";
$stmt = $db->prepare($sqlSched);
$stmt->execute([$userId, $todayStr]);
$todaySched = $stmt->fetch();

// Ambil data absensi hari ini jika sudah ada
$stmtAtt = $db->prepare("SELECT * FROM attendances WHERE user_id = ? AND date = ?");
$stmtAtt->execute([$userId, $todayStr]);
$att = $stmtAtt->fetch();

// Ambil data izin aktif jika ada
$stmtLeave = $db->prepare("SELECT * FROM leaves WHERE user_id = ? AND ? BETWEEN start_date AND end_date AND status = 'approved'");
$stmtLeave->execute([$userId, $todayStr]);
$activeLeave = $stmtLeave->fetch();

// PROSES POST: Konfirmasi Hadir (Clock In) / Pulang (Clock Out)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $currentTime = date('H:i:s');
    $notes = trim($_POST['notes'] ?? '');

    if ($_POST['action'] === 'clock_in') {
        if ($att && !empty($att['clock_in'])) {
            Helpers::setFlash('warning', 'Anda sudah melakukan konfirmasi hadir hari ini.');
        } else {
            // Tentukan status hadir atau terlambat berdasarkan jam shift
            $status = 'hadir';
            if ($todaySched && $todaySched['shift_id'] != 3) {
                $shiftStartTime = $todaySched['start_time'] . ':00';
                // Toleransi keterlambatan 15 menit
                $lateThreshold = date('H:i:s', strtotime($shiftStartTime . ' +15 minutes'));
                if ($currentTime > $lateThreshold) {
                    $status = 'terlambat';
                }
            }

            $stmtIn = $db->prepare("
                INSERT INTO attendances (user_id, schedule_id, date, clock_in, status, notes)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT(user_id, date) DO UPDATE SET clock_in = excluded.clock_in, status = excluded.status, notes = excluded.notes
            ");
            $stmtIn->execute([
                $userId,
                $todaySched['id'] ?? null,
                $todayStr,
                $currentTime,
                $status,
                $notes ?: ($status === 'terlambat' ? 'Terlambat check-in' : 'Hadir tepat waktu')
            ]);

            Helpers::setFlash('success', "Konfirmasi Kehadiran Masuk berhasil dicatat pukul {$currentTime} WIB (" . strtoupper($status) . ").");
        }
        header('Location: ' . BASE_URL . '/operator/absensi.php');
        exit;
    }

    if ($_POST['action'] === 'clock_out') {
        if (!$att || empty($att['clock_in'])) {
            Helpers::setFlash('error', 'Anda harus melakukan Clock-In terlebih dahulu sebelum Clock-Out.');
        } else {
            $stmtOut = $db->prepare("
                UPDATE attendances 
                SET clock_out = ?, notes = CASE WHEN notes IS NULL OR notes = '' THEN ? ELSE notes || ' | ' || ? END
                WHERE user_id = ? AND date = ?
            ");
            $outNote = "Clock-out pada " . $currentTime;
            $stmtOut->execute([$currentTime, $outNote, $outNote, $userId, $todayStr]);

            Helpers::setFlash('success', "Konfirmasi Pulang berhasil dicatat pukul {$currentTime} WIB.");
        }
        header('Location: ' . BASE_URL . '/operator/absensi.php');
        exit;
    }
}

$pageTitle = 'Absensi Mandiri';
$pageSubtitle = Helpers::formatTanggalIndo($todayStr);

require_once __DIR__ . '/../templates/header.php';
?>

<div style="max-width: 680px; margin: 0 auto; margin-bottom: 24px;">
    <!-- 0. Top Back / Title Navigation -->
    <div class="premium-page-nav" style="margin-bottom: 14px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="<?= BASE_URL ?>/operator/index.php" class="premium-back-btn" title="Kembali ke Beranda">
                <i class="ti ti-arrow-left" style="font-size: 1.15rem;"></i>
            </a>
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.3px;">Live Attendance</h2>
                    <span style="font-size: 0.675rem; color: #059669; background: #ecfdf5; padding: 2px 8px; border-radius: 999px; border: 1px solid #a7f3d0; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                        <span class="talenta-live-radar" style="width: 6px; height: 6px;"></span> Aktif
                    </span>
                </div>
                <div style="font-size: 0.725rem; color: #64748b; margin-top: 1px;">
                    <i class="ti ti-fingerprint"></i> Presensi Kehadiran Real-Time Mandiri
                </div>
            </div>
        </div>
    </div>

    <?php if ($activeLeave): ?>
        <!-- Card Jika Ada Cuti/Izin Aktif -->
        <div style="background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%); border-radius: 20px; padding: 24px 18px; border: 1.5px solid #fecdd3; text-align: center; box-shadow: 0 6px 20px rgba(225, 29, 72, 0.1); margin-bottom: 16px;">
            <div style="width: 56px; height: 56px; border-radius: 18px; background: #e11d48; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 12px; box-shadow: 0 4px 12px rgba(225, 29, 72, 0.3);">
                <i class="ti ti-file-medical"></i>
            </div>
            <h3 style="font-weight: 800; color: #9f1239; font-size: 1.15rem; margin-bottom: 4px;">
                Masa <?= strtoupper($activeLeave['type']) ?> Aktif
            </h3>
            <p style="color: #be123c; font-size: 0.85rem; margin-bottom: 12px; font-weight: 500;">
                "<?= htmlspecialchars($activeLeave['reason']) ?>"
            </p>
            <div style="font-size: 0.75rem; color: #881337; background: rgba(255,255,255,0.7); display: inline-block; padding: 5px 14px; border-radius: 999px; border: 1px solid #fecdd3;">
                Periode: <strong><?= date('d/m/Y', strtotime($activeLeave['start_date'])) ?></strong> s/d <strong><?= date('d/m/Y', strtotime($activeLeave['end_date'])) ?></strong>
            </div>
        </div>
    <?php else: ?>
        <!-- 1. HERO DIGITAL CLOCK CARD (Gradient Mewah & Modern) -->
        <div style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #312e81 100%); border-radius: 20px; padding: 22px 18px; text-align: center; color: #ffffff; margin-bottom: 14px; box-shadow: 0 10px 25px -4px rgba(30, 27, 75, 0.4); border: 1px solid rgba(255, 255, 255, 0.15); position: relative; overflow: hidden;">
            <!-- Ambient Glow Spheres -->
            <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(99, 102, 241, 0.3); border-radius: 50%; filter: blur(28px); pointer-events: none;"></div>
            <div style="position: absolute; bottom: -20px; left: -20px; width: 100px; height: 100px; background: rgba(16, 185, 129, 0.25); border-radius: 50%; filter: blur(28px); pointer-events: none;"></div>

            <!-- Shift Badge Pill -->
            <div style="margin-bottom: 10px;">
                <?php if ($todaySched && $todaySched['shift_id'] != 3): ?>
                    <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(8px); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.25); font-size: 0.775rem; font-weight: 700; padding: 4px 14px; border-radius: 999px;">
                        <i class="ti ti-<?= $todaySched['shift_id'] == 1 ? 'sun' : 'moon' ?>" style="color: <?= $todaySched['shift_id'] == 1 ? '#fde047' : '#c084fc' ?>;"></i>
                        <?= htmlspecialchars($todaySched['shift_name']) ?> &bull; <?= htmlspecialchars($todaySched['start_time']) ?> - <?= htmlspecialchars($todaySched['end_time']) ?> WIB
                    </span>
                <?php elseif ($todaySched && $todaySched['shift_id'] == 3): ?>
                    <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 255, 255, 0.12); color: #cbd5e1; font-size: 0.775rem; font-weight: 700; padding: 4px 14px; border-radius: 999px;">
                        <i class="ti ti-bed"></i> Hari Ini: Bebas Tugas (Off Day)
                    </span>
                <?php else: ?>
                    <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 255, 255, 0.15); color: #ffffff; font-size: 0.775rem; font-weight: 700; padding: 4px 14px; border-radius: 999px;">
                        <i class="ti ti-clock"></i> Shift Reguler Operasional
                    </span>
                <?php endif; ?>
            </div>

            <!-- Live Digital Clock Display -->
            <div class="talenta-punch-clock" id="liveClockTime" style="color: #ffffff; font-size: 2.5rem; letter-spacing: -0.5px; text-shadow: 0 2px 12px rgba(0,0,0,0.4); margin: 2px 0;">
                --:--:-- <span class="talenta-clock-tz" style="color: #a5b4fc;">WIB</span>
            </div>

            <div style="font-size: 0.775rem; color: #cbd5e1; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; margin-top: 4px;">
                <i class="ti ti-calendar" style="color: #818cf8;"></i> <?= Helpers::formatTanggalIndo($todayStr) ?>
            </div>
        </div>

        <!-- 2. ACTION CARD (Clock In / Clock Out Form) -->
        <div style="background: #ffffff; border-radius: 18px; padding: 18px 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(0,0,0,0.03); margin-bottom: 14px;">
            <?php if (empty($att['clock_in'])): ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="clock_in">
                    <div style="margin-bottom: 12px; text-align: left;">
                        <label style="font-size: 0.725rem; font-weight: 700; color: #475569; display: block; margin-bottom: 5px;">
                            <i class="ti ti-notes" style="color: #6366f1;"></i> Catatan Masuk (Opsional)
                        </label>
                        <input type="text" name="notes" class="form-control" placeholder="Kondisi siap & sehat bertugas..." style="font-size: 0.825rem; border-radius: 12px; height: 42px; border: 1.5px solid #e2e8f0; background: #f8fafc;">
                    </div>
                    <button type="submit" class="talenta-punch-button clock-in" style="height: 58px; border-radius: 14px; box-shadow: 0 8px 22px -3px rgba(16, 185, 129, 0.42);">
                        <span class="talenta-punch-button-content" style="font-size: 0.95rem;">
                            <i class="ti ti-fingerprint" style="font-size: 1.5rem;"></i>
                            <span>KONFIRMASI CLOCK IN</span>
                        </span>
                        <span class="talenta-punch-button-sub">Sentuh untuk mencatat jam kehadiran masuk</span>
                    </button>
                </form>
            <?php elseif (empty($att['clock_out'])): ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="clock_out">
                    <div style="margin-bottom: 12px; text-align: left;">
                        <label style="font-size: 0.725rem; font-weight: 700; color: #475569; display: block; margin-bottom: 5px;">
                            <i class="ti ti-notes" style="color: #ef4444;"></i> Catatan Serah Terima / Pulang (Opsional)
                        </label>
                        <input type="text" name="notes" class="form-control" placeholder="Pekerjaan shift selesai dengan baik..." style="font-size: 0.825rem; border-radius: 12px; height: 42px; border: 1.5px solid #e2e8f0; background: #f8fafc;">
                    </div>
                    <button type="submit" class="talenta-punch-button clock-out" style="height: 58px; border-radius: 14px; box-shadow: 0 8px 22px -3px rgba(225, 29, 72, 0.42);">
                        <span class="talenta-punch-button-content" style="font-size: 0.95rem;">
                            <i class="ti ti-logout" style="font-size: 1.5rem;"></i>
                            <span>KONFIRMASI CLOCK OUT</span>
                        </span>
                        <span class="talenta-punch-button-sub">Sentuh saat jam kerja shift Anda selesai</span>
                    </button>
                </form>
            <?php else: ?>
                <!-- Sudah Clock In & Clock Out Lengkap -->
                <div style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 1.5px solid #a7f3d0; border-radius: 16px; padding: 20px 16px; text-align: center; color: #065f46; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.12);">
                    <div style="width: 50px; height: 50px; border-radius: 50%; background: #10b981; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.7rem; margin: 0 auto 10px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);">
                        <i class="ti ti-check"></i>
                    </div>
                    <strong style="font-size: 1.05rem; display: block; color: #065f46;">Presensi Hari Ini Lengkap!</strong>
                    <p style="font-size: 0.775rem; margin: 4px 0 0 0; color: #047857;">Seluruh catatan kehadiran (Masuk & Pulang) telah berhasil disimpan dalam sistem.</p>
                </div>
            <?php endif; ?>

            <!-- 3. DUAL SUMMARY CARDS (Jam Masuk & Jam Pulang Berwarna Cantik) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px;">
                <!-- Card Jam Masuk -->
                <div style="border-radius: 14px; padding: 12px 14px; text-align: center; <?= !empty($att['clock_in']) ? 'background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%); border: 1.5px solid #a7f3d0;' : 'background: #f8fafc; border: 1.5px solid #e2e8f0;' ?>">
                    <div style="font-size: 0.65rem; font-weight: 700; color: <?= !empty($att['clock_in']) ? '#047857' : '#64748b' ?>; text-transform: uppercase; letter-spacing: 0.4px; display: flex; align-items: center; justify-content: center; gap: 5px;">
                        <i class="ti ti-login" style="font-size: 0.9rem; color: #10b981;"></i> Jam Masuk
                    </div>
                    <div style="font-size: 1.15rem; font-weight: 800; font-family: monospace; color: <?= !empty($att['clock_in']) ? '#065f46' : '#94a3b8' ?>; margin-top: 3px;">
                        <?= !empty($att['clock_in']) ? htmlspecialchars($att['clock_in']) : '--:--:--' ?>
                    </div>
                    <?php if (!empty($att['status'])): ?>
                        <div style="margin-top: 4px;">
                            <?= Helpers::statusBadge($att['status']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Card Jam Pulang -->
                <div style="border-radius: 14px; padding: 12px 14px; text-align: center; <?= !empty($att['clock_out']) ? 'background: linear-gradient(135deg, #eff6ff 0%, #f8faff 100%); border: 1.5px solid #bfdbfe;' : 'background: #f8fafc; border: 1.5px solid #e2e8f0;' ?>">
                    <div style="font-size: 0.65rem; font-weight: 700; color: <?= !empty($att['clock_out']) ? '#1e40af' : '#64748b' ?>; text-transform: uppercase; letter-spacing: 0.4px; display: flex; align-items: center; justify-content: center; gap: 5px;">
                        <i class="ti ti-logout" style="font-size: 0.9rem; color: #3b82f6;"></i> Jam Pulang
                    </div>
                    <div style="font-size: 1.15rem; font-weight: 800; font-family: monospace; color: <?= !empty($att['clock_out']) ? '#1d4ed8' : '#94a3b8' ?>; margin-top: 3px;">
                        <?= !empty($att['clock_out']) ? htmlspecialchars($att['clock_out']) : '--:--:--' ?>
                    </div>
                    <?php if (!empty($att['clock_out'])): ?>
                        <div style="margin-top: 4px;">
                            <span class="status-badge status-pulang" style="font-size: 0.65rem;">Selesai Bertugas</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4. NAVIGASI CEPAT KE FITUR LAIN (Color Tiles) -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <a href="<?= BASE_URL ?>/operator/izin.php" class="talenta-grid-tile" style="padding: 13px 12px; border-radius: 16px; border: 1px solid #e2e8f0; text-decoration: none;">
            <div class="talenta-tile-icon tile-pastel-rose" style="width: 44px; height: 44px; font-size: 1.35rem; border-radius: 14px;">
                <i class="ti ti-file-text"></i>
            </div>
            <div class="talenta-tile-title" style="font-size: 0.8rem; font-weight: 800; color: #0f172a; margin-top: 6px;">Pengajuan<br>Izin</div>
        </a>
        <a href="<?= BASE_URL ?>/operator/riwayat.php" class="talenta-grid-tile" style="padding: 13px 12px; border-radius: 16px; border: 1px solid #e2e8f0; text-decoration: none;">
            <div class="talenta-tile-icon tile-pastel-amber" style="width: 44px; height: 44px; font-size: 1.35rem; border-radius: 14px;">
                <i class="ti ti-history"></i>
            </div>
            <div class="talenta-tile-title" style="font-size: 0.8rem; font-weight: 800; color: #0f172a; margin-top: 6px;">Riwayat &<br>Rekap Jam</div>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

