<?php
// supervisor/jadwal.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['supervisor', 'admin']);

$db = Database::getConnection();
$currentUser = Auth::user();
$divId = (int)($currentUser['division_id'] ?: 1);
$activePage = 'spv_jadwal';

// Parameter Navigasi Minggu & Tahun
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$week = isset($_GET['week']) ? (int)$_GET['week'] : (int)date('W');

// Hitung tanggal seminggu
$dto = new DateTime();
$dto->setISODate($year, $week, 1);
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

$shifts = $db->query("SELECT * FROM shifts ORDER BY id ASC")->fetchAll();

// POST Handler: Update Shift Karyawan Divisi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_single') {
        $userId = (int)$_POST['user_id'];
        $date = $_POST['date'];
        $shiftId = (int)$_POST['shift_id'];
        $weekNum = (int)date('W', strtotime($date));
        $yearNum = (int)date('Y', strtotime($date));

        // Pastikan user adalah anggota divisinya
        $chk = $db->prepare("SELECT id FROM users WHERE id = ? AND division_id = ?");
        $chk->execute([$userId, $divId]);
        if ($chk->fetch()) {
            $stmt = $db->prepare("
                INSERT INTO schedules (user_id, date, shift_id, week_number, year, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT(user_id, date) DO UPDATE SET shift_id = excluded.shift_id, created_by = excluded.created_by
            ");
            $stmt->execute([$userId, $date, $shiftId, $weekNum, $yearNum, Auth::id()]);
            Helpers::setFlash('success', 'Jadwal shift anggota tim berhasil disimpan.');
        }
        header("Location: " . BASE_URL . "/supervisor/jadwal.php?year={$year}&week={$week}" . (isset($_POST['date']) ? "&active_day=" . urlencode($_POST['date']) : ""));
        exit;
    }

    if ($_POST['action'] === 'batch_assign') {
        $targetUserId = !empty($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : null;
        $shiftId = (int)$_POST['shift_id'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $includeSunday = isset($_POST['include_sunday']) ? true : false;

        if ($targetUserId) {
            $targetUsers = [$targetUserId];
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE division_id = ? AND role = 'operator'");
            $stmt->execute([$divId]);
            $targetUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $stmtInsert = $db->prepare("
            INSERT INTO schedules (user_id, date, shift_id, week_number, year, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT(user_id, date) DO UPDATE SET shift_id = excluded.shift_id, created_by = excluded.created_by
        ");

        $begin = new DateTime($startDate);
        $end = new DateTime($endDate);
        $end->modify('+1 day');
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($begin, $interval, $end);

        $countSaved = 0;
        foreach ($period as $dt) {
            $dayOfWeek = (int)$dt->format('N');
            $curDateStr = $dt->format('Y-m-d');
            $wNum = (int)$dt->format('W');
            $yNum = (int)$dt->format('Y');
            $actualShiftId = ($dayOfWeek === 7 && !$includeSunday) ? 3 : $shiftId;

            foreach ($targetUsers as $uId) {
                $stmtInsert->execute([$uId, $curDateStr, $actualShiftId, $wNum, $yNum, Auth::id()]);
                $countSaved++;
            }
        }

        Helpers::setFlash('success', "Berhasil menyimpan jadwal tim ({$countSaved} slot jadwal).");
        header("Location: " . BASE_URL . "/supervisor/jadwal.php?year={$year}&week={$week}");
        exit;
    }
}

// Ambil anggota divisi
$stmtEmp = $db->prepare("SELECT * FROM users WHERE division_id = ? AND role = 'operator' ORDER BY full_name ASC");
$stmtEmp->execute([$divId]);
$employees = $stmtEmp->fetchAll();

// Ambil jadwal minggu ini
$empIds = array_column($employees, 'id');
$schedulesMap = [];
if (!empty($empIds)) {
    $inClause = implode(',', $empIds);
    $sqlSched = "
        SELECT s.user_id, s.date, s.shift_id, sh.name as shift_name, sh.color as shift_color
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

$prevDto = clone $dto; $prevDto->modify('-7 days');
$nextDto = clone $dto; $nextDto->modify('+7 days');

$pageTitle = 'Penjadwalan Shift Tim (' . htmlspecialchars($currentUser['division_name'] ?? '') . ')';
$pageSubtitle = "Atur shift Shift 1 & Shift 2 untuk minggu ke-{$week}, {$year}";

require_once __DIR__ . '/../templates/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="<?= BASE_URL ?>/supervisor/jadwal.php?year=<?= $prevDto->format('Y') ?>&week=<?= $prevDto->format('W') ?>" class="btn btn-secondary btn-sm">
                    <i class="ti ti-chevron-left"></i> Minggu Lalu
                </a>
                <div style="font-weight: 700; font-size: 1rem; color: var(--text-heading); padding: 4px 12px; background: #f1f5f9; border-radius: var(--radius-md);">
                    <i class="ti ti-calendar"></i> Week <?= $week ?> - <?= $year ?>
                </div>
                <a href="<?= BASE_URL ?>/supervisor/jadwal.php?year=<?= $nextDto->format('Y') ?>&week=<?= $nextDto->format('W') ?>" class="btn btn-secondary btn-sm">
                    Minggu Depan <i class="ti ti-chevron-right"></i>
                </a>
            </div>

            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('batchSpvModal')">
                <i class="ti ti-calendar-plus"></i> Atur Shift Anggota Sekaligus (Batch)
            </button>
        </div>
    </div>
</div>

<!-- Matrix Roster Grid Divisi & Mobile Daily View -->
<div class="card">
    <div class="card-header" style="flex-direction: column; align-items: stretch; gap: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 8px;">
            <div>
                <div class="card-title">
                    <i class="ti ti-calendar-week" style="color: var(--primary);"></i>
                    Roster Jadwal Shift Tim Divisi <?= htmlspecialchars($currentUser['division_name'] ?? '') ?>
                </div>
                <div style="font-size: 0.8125rem; color: var(--text-muted);">
                    Atur jadwal shift harian anggota tim atau periksa matriks mingguan.
                </div>
            </div>

            <!-- Mobile Toggle View: Harian vs Matriks -->
            <div class="mobile-only-view" style="width: 100%; margin-top: 4px;">
                <div class="ios-segmented-control" style="max-width: 100%;">
                    <button type="button" class="ios-segment-btn active" id="btnSpvViewDaily" onclick="switchSpvMobileView('daily')">
                        <i class="ti ti-calendar-event"></i> Harian (Mobile)
                    </button>
                    <button type="button" class="ios-segment-btn" id="btnSpvViewMatrix" onclick="switchSpvMobileView('matrix')">
                        <i class="ti ti-table"></i> Tabel Matriks
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAMPILAN KHUSUS MOBILE -->
    <div class="mobile-only-view">
        <!-- MODE 1: HARIAN DENGAN HORIZONTAL DATE PICKER STRIP -->
        <div id="spvMobileDailyView" style="padding: 14px 12px;">
            <!-- Day Selector Strip (Senin - Minggu) -->
            <div class="ios-day-strip">
                <?php foreach ($weekDates as $wd): ?>
                    <div class="ios-day-pill <?= $wd['date'] === $selectedActiveDay ? 'active' : '' ?>" onclick="switchSpvMobileDay('<?= $wd['date'] ?>', this)">
                        <?php if ($wd['is_today']): ?>
                            <span class="ios-day-today-tag">HARI INI</span>
                        <?php endif; ?>
                        <div class="ios-day-name"><?= substr($wd['day_name'], 0, 3) ?></div>
                        <div class="ios-day-date"><?= $wd['day_num'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- List Anggota Tim Per Hari -->
            <?php foreach ($weekDates as $wd): ?>
                <div class="spv-day-pane" id="spvDayPane_<?= $wd['date'] ?>" style="display: <?= $wd['date'] === $selectedActiveDay ? 'block' : 'none' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding: 0 4px;">
                        <div style="font-weight: 800; font-size: 0.95rem; color: #0f172a;">
                            <i class="ti ti-calendar-event" style="color: var(--primary);"></i> <?= $wd['day_name'] ?>, <?= Helpers::formatTanggalIndo($wd['date'], false) ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">
                            <?= count($employees) ?> Anggota Tim
                        </div>
                    </div>

                    <?php if (empty($employees)): ?>
                        <div style="text-align: center; padding: 32px 16px; background: #f8fafc; border-radius: 16px; border: 1px dashed #cbd5e1; color: #64748b;">
                            <i class="ti ti-users-minus" style="font-size: 2rem; display: block; margin-bottom: 8px; color: #94a3b8;"></i>
                            Belum ada anggota tim pada divisi ini.
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
                                            <?= htmlspecialchars($currentUser['division_name'] ?? 'Tim') ?>
                                        </span>
                                        <span style="font-size: 0.72rem; color: #64748b;"><?= htmlspecialchars($emp['position'] ?? '') ?></span>
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
                                                <?= $sh['id'] == 1 ? '☀️ Shift 1 (07:00 - 15:00)' : ($sh['id'] == 2 ? '🌙 Shift 2 (15:00 - 23:00)' : '☕ Libur (OFF)') ?>
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
        <div id="spvMobileMatrixView" style="display: none; padding: 0;">
            <div class="table-responsive">
                <table class="roster-table">
                    <thead>
                        <tr>
                            <th style="min-width: 150px; text-align: left;">Anggota</th>
                            <?php foreach ($weekDates as $wd): ?>
                                <th style="min-width: 110px; <?= $wd['is_today'] ? 'background: #eff6ff; border-top: 2px solid #3b82f6;' : '' ?>">
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
                            <tr><td colspan="8" style="text-align: center; padding: 24px;">Belum ada anggota tim.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td style="text-align: left; background: #fff;">
                                    <div style="font-weight: 700; font-size: 0.85rem;"><?= htmlspecialchars($emp['full_name']) ?></div>
                                    <div style="font-size: 0.7rem; color: var(--text-muted);"><?= htmlspecialchars($emp['position']) ?></div>
                                </td>
                                <?php foreach ($weekDates as $wd): 
                                    $curSched = $schedulesMap[$emp['id']][$wd['date']] ?? null;
                                    $curShiftId = $curSched ? $curSched['shift_id'] : 3;
                                ?>
                                    <td>
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

    <!-- TAMPILAN KHUSUS DESKTOP (FULL TABLE) -->
    <div class="card-body desktop-only-view" style="padding: 0;">
        <div class="table-responsive">
            <table class="roster-table">
                <thead>
                    <tr>
                        <th style="min-width: 200px; text-align: left;">Nama Anggota Tim</th>
                        <?php foreach ($weekDates as $wd): ?>
                            <th style="min-width: 130px; <?= $wd['is_today'] ? 'background: #eff6ff; border-top: 2px solid #3b82f6;' : '' ?>">
                                <div style="font-weight: 700; color: <?= $wd['is_today'] ? '#1d4ed8' : 'var(--text-heading)' ?>;">
                                    <?= $wd['day_name'] ?>
                                </div>
                                <div style="font-size: 0.75rem; color: <?= $wd['is_today'] ? '#3b82f6' : 'var(--text-muted)' ?>;">
                                    <?= $wd['day_num'] ?>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 30px;">Belum ada anggota tim pada divisi ini.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td style="text-align: left; background: #fff;">
                                <strong><?= htmlspecialchars($emp['full_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($emp['position']) ?></div>
                            </td>
                            <?php foreach ($weekDates as $wd): 
                                $curSched = $schedulesMap[$emp['id']][$wd['date']] ?? null;
                                $curShiftId = $curSched ? $curSched['shift_id'] : 3;
                            ?>
                                <td>
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
                                                    <?= $sh['id'] == 1 ? '☀️ Shift 1' : ($sh['id'] == 2 ? '🌙 Shift 2' : '☕ Libur') ?>
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

<!-- Modal Batch Assign Supervisor -->
<div class="modal-backdrop" id="batchSpvModal">
    <div class="modal-content">
        <form method="POST" action="">
            <input type="hidden" name="action" value="batch_assign">
            <div class="modal-header">
                <h3 class="modal-title">Atur Shift Sekaligus Tim Divisi</h3>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('batchSpvModal')"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Pilih Anggota Tim</label>
                    <select name="target_user_id" class="form-control">
                        <option value="">-- Seluruh Anggota Divisi (<?= count($employees) ?> Orang) --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Pilihan Shift</label>
                    <select name="shift_id" class="form-control" required>
                        <option value="1">☀️ Shift 1 (07:00 - 15:00)</option>
                        <option value="2">🌙 Shift 2 (15:00 - 23:00)</option>
                        <option value="3">☕ Libur (OFF)</option>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $startDateOfWeek ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $endDateOfWeek ?>" required>
                    </div>
                </div>
                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.8125rem;">
                    <input type="checkbox" name="include_sunday" value="1"> Termasuk Hari Minggu
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('batchSpvModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Jadwal</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchSpvMobileDay(dateStr, btnElement) {
    document.querySelectorAll('.spv-day-pane').forEach(function(pane) {
        pane.style.display = 'none';
    });
    document.querySelectorAll('.ios-day-pill').forEach(function(pill) {
        pill.classList.remove('active');
    });
    var target = document.getElementById('spvDayPane_' + dateStr);
    if (target) {
        target.style.display = 'block';
    }
    if (btnElement) {
        btnElement.classList.add('active');
    }
}

function switchSpvMobileView(viewType) {
    var dailyView = document.getElementById('spvMobileDailyView');
    var matrixView = document.getElementById('spvMobileMatrixView');
    var btnDaily = document.getElementById('btnSpvViewDaily');
    var btnMatrix = document.getElementById('btnSpvViewMatrix');
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
