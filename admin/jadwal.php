<?php
// admin/jadwal.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['admin']);

$db = Database::getConnection();
$activePage = 'admin_jadwal';

// Parameter Navigasi Minggu & Tahun
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$week = isset($_GET['week']) ? (int)$_GET['week'] : (int)date('W');
$divisionFilter = isset($_GET['division_id']) && $_GET['division_id'] !== '' ? (int)$_GET['division_id'] : null;

// Hitung rentang tanggal (Senin s/d Minggu) untuk $week & $year
$dto = new DateTime();
$dto->setISODate($year, $week, 1); // Hari Senin
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $cur = clone $dto;
    $cur->modify("+{$i} days");
    $weekDates[] = [
        'date' => $cur->format('Y-m-d'),
        'day_name' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'][$i],
        'day_num' => $cur->format('d/m'),
        'is_today' => ($cur->format('Y-m-d') === date('Y-m-d'))
    ];
}

$startDateOfWeek = $weekDates[0]['date'];
$endDateOfWeek = $weekDates[6]['date'];

// Ambil Semua Shifts
$shifts = $db->query("SELECT * FROM shifts ORDER BY id ASC")->fetchAll();

// Ambil Semua Divisi
$divisions = $db->query("SELECT * FROM divisions ORDER BY id ASC")->fetchAll();

// PROSES POST: Update Single Shift dari Matrix Roster
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_single') {
        $userId = (int)$_POST['user_id'];
        $date = $_POST['date'];
        $shiftId = (int)$_POST['shift_id'];

        $weekNum = (int)date('W', strtotime($date));
        $yearNum = (int)date('Y', strtotime($date));

        $stmt = $db->prepare("
            INSERT INTO schedules (user_id, date, shift_id, week_number, year, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT(user_id, date) DO UPDATE SET shift_id = excluded.shift_id, created_by = excluded.created_by
        ");
        $stmt->execute([$userId, $date, $shiftId, $weekNum, $yearNum, Auth::id()]);
        Helpers::setFlash('success', 'Jadwal shift berhasil diperbarui.');
        header("Location: " . BASE_URL . "/admin/jadwal.php?year={$year}&week={$week}" . ($divisionFilter ? "&division_id={$divisionFilter}" : "") . (isset($_POST['date']) ? "&active_day=" . urlencode($_POST['date']) : ""));
        exit;
    }

    // PROSES POST: Batch Assign Jadwal Shift untuk Rentang Tanggal / Divisi
    if ($_POST['action'] === 'batch_assign') {
        $targetDivision = !empty($_POST['target_division_id']) ? (int)$_POST['target_division_id'] : null;
        $targetUserId = !empty($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : null;
        $shiftId = (int)$_POST['shift_id'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $includeSunday = isset($_POST['include_sunday']) ? true : false;

        // Ambil target users
        if ($targetUserId) {
            $targetUsers = [$targetUserId];
        } elseif ($targetDivision) {
            $stmt = $db->prepare("SELECT id FROM users WHERE division_id = ? AND role = 'operator'");
            $stmt->execute([$targetDivision]);
            $targetUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $targetUsers = $db->query("SELECT id FROM users WHERE role = 'operator'")->fetchAll(PDO::FETCH_COLUMN);
        }

        $stmtInsert = $db->prepare("
            INSERT INTO schedules (user_id, date, shift_id, week_number, year, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT(user_id, date) DO UPDATE SET shift_id = excluded.shift_id, created_by = excluded.created_by
        ");

        $begin = new DateTime($startDate);
        $end = new DateTime($endDate);
        $end->modify('+1 day'); // inclusive
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($begin, $interval, $end);

        $countSaved = 0;
        foreach ($period as $dt) {
            $dayOfWeek = (int)$dt->format('N'); // 1 = Senin, 7 = Minggu
            $curDateStr = $dt->format('Y-m-d');
            $wNum = (int)$dt->format('W');
            $yNum = (int)$dt->format('Y');

            // Jika hari Minggu dan tidak dicentang, jadikan shift OFF (id: 3)
            $actualShiftId = ($dayOfWeek === 7 && !$includeSunday) ? 3 : $shiftId;

            foreach ($targetUsers as $uId) {
                $stmtInsert->execute([$uId, $curDateStr, $actualShiftId, $wNum, $yNum, Auth::id()]);
                $countSaved++;
            }
        }

        Helpers::setFlash('success', "Berhasil menerapkan jadwal ({$countSaved} data slot shift telah disimpan).");
        header("Location: " . BASE_URL . "/admin/jadwal.php?year={$year}&week={$week}" . ($divisionFilter ? "&division_id={$divisionFilter}" : ""));
        exit;
    }
}

// Ambil Karyawan (Operator) sesuai filter
$sqlEmp = "
    SELECT u.id, u.full_name, u.position, d.id as division_id, d.name as division_name, d.code as division_code
    FROM users u
    LEFT JOIN divisions d ON u.division_id = d.id
    WHERE u.role = 'operator'
";
if ($divisionFilter) {
    $sqlEmp .= " AND u.division_id = {$divisionFilter}";
}
$sqlEmp .= " ORDER BY d.id ASC, u.full_name ASC";
$employees = $db->query($sqlEmp)->fetchAll();

// Ambil Seluruh Jadwal untuk Minggu Ini
$empIds = array_column($employees, 'id');
$schedulesMap = []; // [user_id][date] = shift_id
if (!empty($empIds)) {
    $inClause = implode(',', $empIds);
    $sqlSched = "
        SELECT s.user_id, s.date, s.shift_id, sh.name as shift_name, sh.color as shift_color, sh.code as shift_code
        FROM schedules s
        JOIN shifts sh ON s.shift_id = sh.id
        WHERE s.user_id IN ({$inClause}) AND s.date BETWEEN '{$startDateOfWeek}' AND '{$endDateOfWeek}'
    ";
    $schedRows = $db->query($sqlSched)->fetchAll();
    foreach ($schedRows as $sr) {
        $schedulesMap[$sr['user_id']][$sr['date']] = $sr;
    }
}

// Tentukan hari aktif untuk tampilan mobile
$validDates = array_column($weekDates, 'date');
$selectedActiveDay = $_GET['active_day'] ?? null;
if (!$selectedActiveDay || !in_array($selectedActiveDay, $validDates)) {
    $todayIso = date('Y-m-d');
    if (in_array($todayIso, $validDates)) {
        $selectedActiveDay = $todayIso;
    } else {
        $selectedActiveDay = $weekDates[0]['date'] ?? $startDateOfWeek;
    }
}

// Navigasi Minggu Sebelumnya dan Berikutnya
$prevDto = clone $dto;
$prevDto->modify('-7 days');
$prevWeek = $prevDto->format('W');
$prevYear = $prevDto->format('Y');

$nextDto = clone $dto;
$nextDto->modify('+7 days');
$nextWeek = $nextDto->format('W');
$nextYear = $nextDto->format('Y');

$pageTitle = 'Penjadwalan Shift Karyawan (Roster)';
$pageSubtitle = "Pengaturan Shift 1 & Shift 2 untuk Minggu ke-{$week}, {$year} (" . Helpers::formatTanggalIndo($startDateOfWeek, false) . " - " . Helpers::formatTanggalIndo($endDateOfWeek, false) . ")";

require_once __DIR__ . '/../templates/header.php';
?>

<!-- Action Header & Navigasi Week -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <div class="roster-nav-container">
            <!-- Pilihan Minggu & Navigasi -->
            <div class="roster-week-nav">
                <a href="<?= BASE_URL ?>/admin/jadwal.php?year=<?= $prevYear ?>&week=<?= $prevWeek ?><?= $divisionFilter ? "&division_id={$divisionFilter}" : "" ?>" class="btn btn-secondary btn-sm" title="Minggu Lalu">
                    <i class="ti ti-chevron-left"></i> <span class="d-none-mobile">Minggu Lalu</span>
                </a>
                <div class="roster-week-badge">
                    <i class="ti ti-calendar"></i> Week <?= $week ?> - <?= $year ?>
                </div>
                <a href="<?= BASE_URL ?>/admin/jadwal.php?year=<?= $nextYear ?>&week=<?= $nextWeek ?><?= $divisionFilter ? "&division_id={$divisionFilter}" : "" ?>" class="btn btn-secondary btn-sm" title="Minggu Depan">
                    <span class="d-none-mobile">Minggu Depan</span> <i class="ti ti-chevron-right"></i>
                </a>
                <a href="<?= BASE_URL ?>/admin/jadwal.php" class="btn btn-secondary btn-sm" title="Kembali ke Minggu Sekarang">
                    Minggu Ini
                </a>
            </div>

            <!-- Filter Divisi & Tombol Batch Assign -->
            <div class="roster-actions-nav">
                <form method="GET" action="" style="margin: 0;">
                    <input type="hidden" name="year" value="<?= $year ?>">
                    <input type="hidden" name="week" value="<?= $week ?>">
                    <select name="division_id" class="form-control" style="padding: 6px 12px; font-size: 0.8125rem;" onchange="this.form.submit()">
                        <option value="">Semua Divisi (<?= count($employees) ?> Karyawan)</option>
                        <?php foreach ($divisions as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $divisionFilter == $d['id'] ? 'selected' : '' ?>>
                                Divisi <?= htmlspecialchars($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('batchAssignModal')">
                    <i class="ti ti-calendar-plus"></i> Atur Shift Sekaligus (Batch)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Petunjuk Warna Shift -->
<div class="roster-legend-nav" style="margin-bottom: 16px; padding: 0 4px;">
    <span style="font-weight: 700; color: var(--text-muted);"><i class="ti ti-info-circle"></i> Keterangan Shift:</span>
    <span class="badge-shift" style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd;">
        <i class="ti ti-sun"></i> Shift 1 (07:00 - 15:00)
    </span>
    <span class="badge-shift" style="background: #f3e8ff; color: #7c3aed; border: 1px solid #e9d5ff;">
        <i class="ti ti-moon"></i> Shift 2 (15:00 - 23:00)
    </span>
    <span class="badge-shift" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;">
        <i class="ti ti-bed"></i> Off (Libur)
    </span>
</div>

<!-- MATRIX ROSTER SCHEDULE TABLE & MOBILE DAILY VIEW -->
<div class="card">
    <div class="card-header" style="flex-direction: column; align-items: stretch; gap: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 8px;">
            <div>
                <div class="card-title">
                    <i class="ti ti-layout-grid" style="color: var(--primary);"></i>
                    Roster Jadwal Shift Mingguan
                </div>
                <div style="font-size: 0.8125rem; color: var(--text-muted);">
                    Atur jadwal shift harian atau periksa matriks mingguan.
                </div>
            </div>
            
            <!-- Mobile Toggle View: Harian vs Matriks -->
            <div class="mobile-only-view" style="width: 100%; margin-top: 4px;">
                <div class="ios-segmented-control" style="max-width: 100%;">
                    <button type="button" class="ios-segment-btn active" id="btnViewDaily" onclick="switchMobileView('daily')">
                        <i class="ti ti-calendar-event"></i> Harian (Mobile)
                    </button>
                    <button type="button" class="ios-segment-btn" id="btnViewMatrix" onclick="switchMobileView('matrix')">
                        <i class="ti ti-table"></i> Tabel Matriks
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAMPILAN KHUSUS MOBILE -->
    <div class="mobile-only-view">
        <!-- MODE 1: HARIAN DENGAN HORIZONTAL DATE PICKER STRIP -->
        <div id="mobileDailyView" style="padding: 14px 12px;">
            <!-- Day Selector Strip (Senin - Minggu) -->
            <div class="ios-day-strip">
                <?php foreach ($weekDates as $wd): ?>
                    <div class="ios-day-pill <?= $wd['date'] === $selectedActiveDay ? 'active' : '' ?>" onclick="switchMobileDay('<?= $wd['date'] ?>', this)">
                        <?php if ($wd['is_today']): ?>
                            <span class="ios-day-today-tag">HARI INI</span>
                        <?php endif; ?>
                        <div class="ios-day-name"><?= substr($wd['day_name'], 0, 3) ?></div>
                        <div class="ios-day-date"><?= $wd['day_num'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- List Karyawan Per Hari -->
            <?php foreach ($weekDates as $wd): ?>
                <div class="ios-day-pane" id="dayPane_<?= $wd['date'] ?>" style="display: <?= $wd['date'] === $selectedActiveDay ? 'block' : 'none' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding: 0 4px;">
                        <div style="font-weight: 800; font-size: 0.95rem; color: #0f172a;">
                            <i class="ti ti-calendar-event" style="color: var(--primary);"></i> <?= $wd['day_name'] ?>, <?= Helpers::formatTanggalIndo($wd['date'], false) ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">
                            <?= count($employees) ?> Karyawan
                        </div>
                    </div>

                    <?php if (empty($employees)): ?>
                        <div style="text-align: center; padding: 32px 16px; background: #f8fafc; border-radius: 16px; border: 1px dashed #cbd5e1; color: #64748b;">
                            <i class="ti ti-users-minus" style="font-size: 2rem; display: block; margin-bottom: 8px; color: #94a3b8;"></i>
                            Tidak ada data karyawan untuk divisi yang dipilih.
                        </div>
                    <?php endif; ?>

                    <?php foreach ($employees as $emp): 
                        $curSched = $schedulesMap[$emp['id']][$wd['date']] ?? null;
                        $curShiftId = $curSched ? (int)$curSched['shift_id'] : 3;
                        $initials = strtoupper(substr($emp['full_name'], 0, 1));
                        $shiftClass = $curShiftId == 1 ? 'shift-1' : ($curShiftId == 2 ? 'shift-2' : 'shift-off');
                    ?>
                        <div class="ios-emp-shift-card">
                            <div class="ios-emp-shift-header">
                                <div class="ios-emp-avatar">
                                    <?= $initials ?>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div class="ios-emp-name" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars($emp['full_name']) ?>
                                    </div>
                                    <div class="ios-emp-sub" style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                        <span class="user-role-badge" style="background: #f1f5f9; color: #475569; padding: 1px 6px; font-size: 0.65rem; border-radius: 4px;">
                                            <?= htmlspecialchars($emp['division_name']) ?>
                                        </span>
                                        <span style="font-size: 0.72rem; color: #64748b;"><?= htmlspecialchars($emp['position']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <form method="POST" action="" style="margin: 0;">
                                    <input type="hidden" name="action" value="save_single">
                                    <input type="hidden" name="user_id" value="<?= $emp['id'] ?>">
                                    <input type="hidden" name="date" value="<?= $wd['date'] ?>">
                                    <label style="font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 4px; display: block;">
                                        PILIH SHIFT HARI INI:
                                    </label>
                                    <select name="shift_id" class="ios-shift-select <?= $shiftClass ?>" onchange="this.form.submit()">
                                        <?php foreach ($shifts as $sh): ?>
                                            <option value="<?= $sh['id'] ?>" <?= $curShiftId == $sh['id'] ? 'selected' : '' ?>>
                                                <?= $sh['id'] == 1 ? '☀️ Shift 1 (07:00 - 15:00)' : ($sh['id'] == 2 ? '🌙 Shift 2 (15:00 - 23:00)' : '☕ Off (Libur)') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- MODE 2: TABEL MATRIKS HORIZONTAL SCROLL -->
        <div id="mobileMatrixView" style="display: none; padding: 0;">
            <div class="table-responsive">
                <table class="roster-table table-roster-sticky">
                    <thead>
                        <tr>
                            <th style="min-width: 170px; text-align: left;">Karyawan</th>
                            <?php foreach ($weekDates as $wd): ?>
                                <th style="min-width: 120px; <?= $wd['is_today'] ? 'background: #eff6ff; border-top: 2px solid #3b82f6;' : '' ?>">
                                    <div style="font-weight: 700; color: <?= $wd['is_today'] ? '#1d4ed8' : 'var(--text-heading)' ?>;">
                                        <?= substr($wd['day_name'], 0, 3) ?>
                                    </div>
                                    <div style="font-size: 0.72rem; color: <?= $wd['is_today'] ? '#3b82f6' : 'var(--text-muted)' ?>;">
                                        <?= $wd['day_num'] ?>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                    Tidak ada data karyawan.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td style="text-align: left; background: #ffffff;">
                                    <div style="font-weight: 700; font-size: 0.85rem;"><?= htmlspecialchars($emp['full_name']) ?></div>
                                    <div style="font-size: 0.7rem; color: var(--text-muted);">
                                        <?= htmlspecialchars($emp['division_name']) ?>
                                    </div>
                                </td>
                                <?php foreach ($weekDates as $wd): 
                                    $curSched = $schedulesMap[$emp['id']][$wd['date']] ?? null;
                                    $curShiftId = $curSched ? $curSched['shift_id'] : 3;
                                ?>
                                    <td style="<?= $wd['is_today'] ? 'background: #f8faff;' : '' ?>">
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="save_single">
                                            <input type="hidden" name="user_id" value="<?= $emp['id'] ?>">
                                            <input type="hidden" name="date" value="<?= $wd['date'] ?>">

                                            <select name="shift_id" class="shift-cell-select" onchange="this.form.submit()" style="
                                                <?php 
                                                if ($curShiftId == 1) echo 'background-color: #e0f2fe; color: #0369a1; border-color: #7dd3fc; font-weight:700;';
                                                elseif ($curShiftId == 2) echo 'background-color: #f3e8ff; color: #6b21a8; border-color: #d8b4fe; font-weight:700;';
                                                else echo 'background-color: #f8fafc; color: #64748b; border-color: #e2e8f0;';
                                                ?>
                                            ">
                                                <?php foreach ($shifts as $sh): ?>
                                                    <option value="<?= $sh['id'] ?>" <?= $curShiftId == $sh['id'] ? 'selected' : '' ?>>
                                                        <?= $sh['id'] == 1 ? '☀️ S1' : ($sh['id'] == 2 ? '🌙 S2' : '☕ Off') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAMPILAN KHUSUS DESKTOP (FULL MATRIX TABLE) -->
    <div class="card-body desktop-only-view" style="padding: 0;">
        <div class="table-responsive">
            <table class="roster-table table-roster-sticky">
                <thead>
                    <tr>
                        <th style="min-width: 220px; text-align: left;">Nama Karyawan & Divisi</th>
                        <?php foreach ($weekDates as $wd): ?>
                            <th style="min-width: 140px; <?= $wd['is_today'] ? 'background: #eff6ff; border-top: 2px solid #3b82f6;' : '' ?>">
                                <div style="font-weight: 700; color: <?= $wd['is_today'] ? '#1d4ed8' : 'var(--text-heading)' ?>;">
                                    <?= $wd['day_name'] ?>
                                </div>
                                <div style="font-size: 0.75rem; color: <?= $wd['is_today'] ? '#3b82f6' : 'var(--text-muted)' ?>;">
                                    <?= $wd['day_num'] ?> <?= $wd['is_today'] ? '(Hari Ini)' : '' ?>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                Tidak ada data karyawan untuk divisi yang dipilih.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td style="text-align: left; background: #ffffff;">
                                <strong><?= htmlspecialchars($emp['full_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    <span class="user-role-badge" style="background: #f1f5f9; color: #475569; padding: 1px 6px; font-size: 0.65rem;">
                                        <?= htmlspecialchars($emp['division_name']) ?>
                                    </span>
                                    <span><?= htmlspecialchars($emp['position']) ?></span>
                                </div>
                            </td>

                            <?php foreach ($weekDates as $wd): 
                                $curSched = $schedulesMap[$emp['id']][$wd['date']] ?? null;
                                $curShiftId = $curSched ? $curSched['shift_id'] : 3; // default off
                            ?>
                                <td style="<?= $wd['is_today'] ? 'background: #f8faff;' : '' ?>">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="save_single">
                                        <input type="hidden" name="user_id" value="<?= $emp['id'] ?>">
                                        <input type="hidden" name="date" value="<?= $wd['date'] ?>">

                                        <select name="shift_id" class="shift-cell-select" onchange="this.form.submit()" style="
                                            <?php 
                                            if ($curShiftId == 1) echo 'background-color: #e0f2fe; color: #0369a1; border-color: #7dd3fc; font-weight:700;';
                                            elseif ($curShiftId == 2) echo 'background-color: #f3e8ff; color: #6b21a8; border-color: #d8b4fe; font-weight:700;';
                                            else echo 'background-color: #f8fafc; color: #64748b; border-color: #e2e8f0;';
                                            ?>
                                        ">
                                            <?php foreach ($shifts as $sh): ?>
                                                <option value="<?= $sh['id'] ?>" <?= $curShiftId == $sh['id'] ? 'selected' : '' ?>>
                                                    <?= $sh['id'] == 1 ? '☀️ Shift 1' : ($sh['id'] == 2 ? '🌙 Shift 2' : '☕ Libur / Off') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL BATCH ASSIGN JADWAL -->
<div class="modal-backdrop" id="batchAssignModal">
    <div class="modal-content">
        <form method="POST" action="">
            <input type="hidden" name="action" value="batch_assign">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="ti ti-calendar-plus" style="color: var(--primary);"></i>
                    Atur Shift Sekaligus (Batch Assign)
                </h3>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('batchAssignModal')">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Terapkan Untuk Divisi</label>
                    <select name="target_division_id" class="form-control" id="batchDivisionSelect">
                        <option value="">-- Semua Divisi --</option>
                        <?php foreach ($divisions as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $divisionFilter == $d['id'] ? 'selected' : '' ?>>
                                Divisi <?= htmlspecialchars($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Atau Pilih Karyawan Tertentu Saja (Opsional)</label>
                    <select name="target_user_id" class="form-control">
                        <option value="">-- Berdasarkan Divisi di Atas (Semua Anggota) --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>">
                                <?= htmlspecialchars($emp['full_name']) ?> (<?= htmlspecialchars($emp['division_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Pilihan Shift Kerja</label>
                    <select name="shift_id" class="form-control" required>
                        <option value="1">☀️ Shift 1 (07:00 - 15:00)</option>
                        <option value="2">🌙 Shift 2 (15:00 - 23:00)</option>
                        <option value="3">☕ Libur (OFF)</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $startDateOfWeek ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $endDateOfWeek ?>" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; cursor: pointer;">
                        <input type="checkbox" name="include_sunday" value="1">
                        <span>Termasuk Hari Minggu (Jika tidak dicentang, Hari Minggu otomatis OFF/Libur)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('batchAssignModal')">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-check"></i> Terapkan Jadwal Roster
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function switchMobileDay(dateStr, btnElement) {
    document.querySelectorAll('.ios-day-pane').forEach(function(pane) {
        pane.style.display = 'none';
    });
    document.querySelectorAll('.ios-day-pill').forEach(function(pill) {
        pill.classList.remove('active');
    });
    var target = document.getElementById('dayPane_' + dateStr);
    if (target) {
        target.style.display = 'block';
    }
    if (btnElement) {
        btnElement.classList.add('active');
    }
}

function switchMobileView(viewType) {
    var dailyView = document.getElementById('mobileDailyView');
    var matrixView = document.getElementById('mobileMatrixView');
    var btnDaily = document.getElementById('btnViewDaily');
    var btnMatrix = document.getElementById('btnViewMatrix');
    if (viewType === 'daily') {
        if (dailyView) dailyView.style.display = 'block';
        if (matrixView) matrixView.style.display = 'none';
        if (btnDaily) btnDaily.classList.add('active');
        if (btnMatrix) btnMatrix.classList.remove('active');
    } else {
        if (dailyView) dailyView.style.display = 'none';
        if (matrixView) matrixView.style.display = 'block';
        if (btnDaily) btnDaily.classList.remove('active');
        if (btnMatrix) btnMatrix.classList.add('active');
    }
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
