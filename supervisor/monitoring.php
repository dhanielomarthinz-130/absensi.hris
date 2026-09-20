<?php
// supervisor/monitoring.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['supervisor', 'admin']);

$db = Database::getConnection();
$currentUser = Auth::user();
$divId = (int)($currentUser['division_id'] ?: 1);
$activePage = 'spv_monitoring';

$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$shiftFilter = isset($_GET['shift_id']) && $_GET['shift_id'] !== '' ? (int)$_GET['shift_id'] : null;

$shifts = $db->query("SELECT * FROM shifts ORDER BY id ASC")->fetchAll();

$sql = "
    SELECT 
        u.id, u.full_name, u.position,
        s.shift_id, sh.name as shift_name, sh.start_time, sh.end_time, sh.color as shift_color,
        a.clock_in, a.clock_out, a.status as attendance_status,
        l.type as leave_type, l.reason as leave_reason, l.doctor_letter
    FROM users u
    LEFT JOIN schedules s ON s.user_id = u.id AND s.date = '{$selectedDate}'
    LEFT JOIN shifts sh ON s.shift_id = sh.id
    LEFT JOIN attendances a ON a.user_id = u.id AND a.date = '{$selectedDate}'
    LEFT JOIN leaves l ON l.user_id = u.id AND l.status = 'approved' AND '{$selectedDate}' BETWEEN l.start_date AND l.end_date
    WHERE u.division_id = {$divId} AND u.role = 'operator'
";

if ($shiftFilter) {
    $sql .= " AND s.shift_id = {$shiftFilter}";
}

$sql .= " ORDER BY u.full_name ASC";
$team = $db->query($sql)->fetchAll();

$pageTitle = 'Monitoring Presensi Tim (' . htmlspecialchars($currentUser['division_name'] ?? '') . ')';
$pageSubtitle = 'Pemantauan kehadiran anggota tim pada ' . Helpers::formatTanggalIndo($selectedDate);

require_once __DIR__ . '/../templates/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Tanggal</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Shift</label>
                <select name="shift_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua Shift</option>
                    <?php foreach ($shifts as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $shiftFilter == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-secondary" onclick="window.print()">
                <i class="ti ti-printer"></i> Cetak Daftar
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="ti ti-users-group" style="color: var(--primary);"></i>
            Daftar Kehadiran Anggota Tim
        </div>
    </div>
    <!-- TAMPILAN KHUSUS MOBILE (IOS / ANDROID CARD LIST) -->
    <div class="card-body mobile-only-view" style="padding: 12px;">
        <?php if (empty($team)): ?>
            <div style="text-align: center; padding: 32px 16px; background: #f8fafc; border-radius: 16px; border: 1px dashed #cbd5e1; color: #64748b;">
                <i class="ti ti-users-minus" style="font-size: 2rem; display: block; margin-bottom: 8px; color: #94a3b8;"></i>
                Tidak ada data anggota tim.
            </div>
        <?php endif; ?>

        <?php foreach ($team as $tm): ?>
            <div class="ios-data-card">
                <div class="ios-card-header">
                    <div class="ios-card-user">
                        <div class="ios-card-avatar">
                            <?= strtoupper(substr($tm['full_name'], 0, 1)) ?>
                        </div>
                        <div style="min-width: 0;">
                            <div class="ios-card-name" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($tm['full_name']) ?>
                            </div>
                            <div class="ios-card-sub">
                                <?= htmlspecialchars($tm['position']) ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <?php 
                        if (!empty($tm['attendance_status'])) {
                            echo Helpers::statusBadge($tm['attendance_status']);
                        } elseif (!empty($tm['leave_type'])) {
                            echo Helpers::statusBadge($tm['leave_type']);
                        } else {
                            echo Helpers::statusBadge('tidak_hadir');
                        }
                        ?>
                    </div>
                </div>

                <!-- Shift info -->
                <div style="background: #f8fafc; padding: 8px 12px; border-radius: 10px; font-size: 0.775rem;">
                    <span style="color: #64748b; font-size: 0.7rem; font-weight: 700;">JADWAL SHIFT: </span>
                    <?php if ($tm['shift_name']): ?>
                        <span style="font-weight: 700; color: <?= htmlspecialchars($tm['shift_color']) ?>;">
                            <?= htmlspecialchars($tm['shift_name']) ?> (<?= htmlspecialchars($tm['start_time']) ?> - <?= htmlspecialchars($tm['end_time']) ?>)
                        </span>
                    <?php else: ?>
                        <span style="color: #94a3b8;">Belum diatur</span>
                    <?php endif; ?>
                </div>

                <!-- Clock In & Clock Out Pills -->
                <div class="ios-grid-2">
                    <div class="ios-pill-box">
                        <span class="ios-pill-label"><i class="ti ti-login" style="color: #10b981;"></i> Clock In</span>
                        <span class="ios-pill-val" style="font-family: monospace;">
                            <?= $tm['clock_in'] ? htmlspecialchars($tm['clock_in']) : '<span style="color:#94a3b8; font-weight:normal;">--:--:--</span>' ?>
                        </span>
                    </div>
                    <div class="ios-pill-box">
                        <span class="ios-pill-label"><i class="ti ti-logout" style="color: #ef4444;"></i> Clock Out</span>
                        <span class="ios-pill-val" style="font-family: monospace;">
                            <?= $tm['clock_out'] ? htmlspecialchars($tm['clock_out']) : '<span style="color:#94a3b8; font-weight:normal;">--:--:--</span>' ?>
                        </span>
                    </div>
                </div>

                <!-- Keterangan / Dokumen -->
                <?php if (!empty($tm['leave_type']) || $tm['doctor_letter']): ?>
                    <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 10px; padding: 8px 10px; font-size: 0.775rem; color: #92400e;">
                        <?php if (!empty($tm['leave_type'])): ?>
                            <strong>Izin:</strong> <?= htmlspecialchars($tm['leave_reason'] ?? '') ?>
                        <?php endif; ?>
                        <?php if ($tm['doctor_letter']): ?>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="viewDoctorLetter('<?= UPLOAD_URL . htmlspecialchars($tm['doctor_letter']) ?>', '<?= htmlspecialchars(addslashes($tm['full_name'])) ?>', '<?= $selectedDate ?>')" style="padding: 3px 8px; font-size: 0.7rem; margin-top: 4px; display: block; color: #dc2626;">
                                <i class="ti ti-file-certificate"></i> Lihat Surat Dokter
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- TAMPILAN KHUSUS DESKTOP (FULL DATA TABLE) -->
    <div class="card-body desktop-only-view">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Anggota Tim</th>
                        <th>Jadwal Shift</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Status</th>
                        <th>Keterangan / Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($team)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 30px;">Tidak ada data anggota.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($team as $tm): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($tm['full_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($tm['position']) ?></div>
                            </td>
                            <td>
                                <?php if ($tm['shift_name']): ?>
                                    <span class="badge-shift" style="background: <?= htmlspecialchars($tm['shift_color']) ?>15; color: <?= htmlspecialchars($tm['shift_color']) ?>;">
                                        <?= htmlspecialchars($tm['shift_name']) ?>
                                    </span>
                                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">
                                        (<?= htmlspecialchars($tm['start_time']) ?> - <?= htmlspecialchars($tm['end_time']) ?>)
                                    </div>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">Belum diatur</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $tm['clock_in'] ? '<strong style="font-family: monospace;">' . htmlspecialchars($tm['clock_in']) . '</strong>' : '<span style="color:#94a3b8;">-</span>' ?>
                            </td>
                            <td>
                                <?= $tm['clock_out'] ? '<strong style="font-family: monospace;">' . htmlspecialchars($tm['clock_out']) . '</strong>' : '<span style="color:#94a3b8;">-</span>' ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($tm['attendance_status'])) {
                                    echo Helpers::statusBadge($tm['attendance_status']);
                                } elseif (!empty($tm['leave_type'])) {
                                    echo Helpers::statusBadge($tm['leave_type']);
                                } else {
                                    echo Helpers::statusBadge('tidak_hadir');
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($tm['doctor_letter']): ?>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="viewDoctorLetter('<?= UPLOAD_URL . htmlspecialchars($tm['doctor_letter']) ?>', '<?= htmlspecialchars(addslashes($tm['full_name'])) ?>', '<?= $selectedDate ?>')" style="padding: 2px 6px; font-size: 0.7rem; color: #dc2626;">
                                        <i class="ti ti-file-certificate"></i> Lihat Surat Dokter
                                    </button>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.75rem;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
