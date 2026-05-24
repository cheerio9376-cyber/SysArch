<?php
// admin/reports.php — Generate CSV, PDF, or DOC reports
require 'includes/admin_auth.php';
$current_page = 'reports';

// ── Handle Export ────────────────────────────────────────
$type   = trim($_GET['type']   ?? '');
$format = trim($_GET['format'] ?? '');
$from   = trim($_GET['from']   ?? date('Y-m-01'));
$to     = trim($_GET['to']     ?? date('Y-m-d'));
$lab        = trim($_GET['lab']        ?? '');
$year_level = trim($_GET['year_level'] ?? '');
$course_filter = trim($_GET['course_filter'] ?? '');

if ($type && $format && in_array($format,['csv','pdf','doc'])) {

    // ── Fetch data ───────────────────────────────────────
    if ($type === 'sitins') {
        $lab_clean = $conn->real_escape_string($lab);
        $lab_filter = $lab_clean ? "AND si.lab = '$lab_clean'" : '';
        $lab_label  = $lab_clean ? " — Lab $lab_clean" : '';
        $rows = $conn->query("
            SELECT si.id, s.student_id, CONCAT(s.firstname,' ',s.lastname) as name,
                   s.course_level, s.course, si.purpose, si.lab,
                   si.sit_in_time, si.sit_out_time, si.duration_minutes, si.status
            FROM sitins si
            JOIN students s ON s.student_id = si.student_id
            WHERE DATE(si.sit_in_time) BETWEEN '$from' AND '$to'
            $lab_filter
            ORDER BY si.sit_in_time DESC
        ")->fetch_all(MYSQLI_ASSOC);
        $headers = ['ID','Student ID','Name','Level','Course','Purpose','Lab','Time In','Time Out','Duration (min)','Status'];
        $title   = 'Sit-In History Report' . $lab_label;

    } elseif ($type === 'students') {
        $stu_where = ["s.role = 'student'"];
        $stu_label_parts = [];

        if ($year_level) {
            $yl = $conn->real_escape_string($year_level);
            $stu_where[] = "s.course_level = '$yl'";
            $stu_label_parts[] = $yl;
        }
        if ($course_filter) {
            $cf = $conn->real_escape_string($course_filter);
            $stu_where[] = "s.course = '$cf'";
            $stu_label_parts[] = $cf;
        }
        $stu_where[] = "DATE(s.created_at) BETWEEN '$from' AND '$to'";
        $stu_where_sql = implode(' AND ', $stu_where);
        $title_suffix  = $stu_label_parts ? ' — ' . implode(', ', $stu_label_parts) : '';

        $rows = $conn->query("
            SELECT s.student_id, CONCAT(s.firstname,' ',s.lastname) as name,
                   s.course_level, s.course, s.email,
                   COALESCE(sits.total_sitins, 0) as total_sitins,
                   COALESCE(sits.completed, 0) as completed_sessions,
                   s.created_at as date_enrolled
            FROM students s
            LEFT JOIN (
                SELECT student_id,
                       COUNT(*) as total_sitins,
                       SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed
                FROM sitins
                GROUP BY student_id
            ) sits ON sits.student_id = s.student_id
            WHERE $stu_where_sql
            ORDER BY s.lastname ASC
        ")->fetch_all(MYSQLI_ASSOC);
        $headers = ['Student ID','Name','Year Level','Course','Email','Total Sit-Ins','Completed Sessions','Date Enrolled'];
        $title   = 'Student List Report' . $title_suffix;

    } else { $rows = []; $headers = []; $title = 'Report'; }

    // ── CSV Export ───────────────────────────────────────
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.strtolower(str_replace(' ','_',$title)).'_'.$from.'_'.$to.'.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM
        $lab_info = ($type === 'sitins' && $lab) ? "Lab: $lab" : '';
        $stu_info = ($type === 'students' && ($year_level || $course_filter))
            ? implode(', ', array_filter([$year_level, $course_filter])) : '';
        $filter_info = $lab_info ?: $stu_info;
        fputcsv($out, array_filter([$title, "Period: $from to $to", $filter_info, 'Generated: '.date('Y-m-d H:i')]));
        fputcsv($out, []);
        fputcsv($out, $headers);
        foreach ($rows as $r) fputcsv($out, array_values($r));
        fclose($out);
        exit;
    }

    // ── HTML → DOC Export ────────────────────────────────
    if ($format === 'doc') {
        header('Content-Type: application/vnd.ms-word; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.strtolower(str_replace(' ','_',$title)).'_'.$from.'_'.$to.'.doc"');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>
            body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
            h1   { color: #5a3d82; font-size: 16pt; border-bottom: 2px solid #5a3d82; padding-bottom: 6pt; }
            p    { margin: 4pt 0; color: #555; }
            table { border-collapse: collapse; width: 100%; margin-top: 12pt; }
            th   { background: #5a3d82; color: #fff; padding: 6pt 8pt; font-size: 10pt; border: 1pt solid #ccc; text-align: left; }
            td   { padding: 5pt 8pt; border: 1pt solid #ddd; font-size: 10pt; }
            tr:nth-child(even) td { background: #f3eeff; }
        </style></head><body>';
        echo "<h1>$title</h1>";
        $extra = '';
        if ($type === 'sitins' && $lab) $extra = " &nbsp;|&nbsp; Lab: <strong>$lab</strong>";
        if ($type === 'students') {
            $sf = implode(', ', array_filter([$year_level, $course_filter]));
            if ($sf) $extra = " &nbsp;|&nbsp; Filter: <strong>$sf</strong>";
        }
        echo "<p>Period: <strong>$from</strong> to <strong>$to</strong>$extra</p>";
        echo "<p>Generated: ".date('F d, Y H:i')." &nbsp;|&nbsp; Total Records: ".count($rows)."</p>";
        echo '<table><thead><tr>';
        foreach ($headers as $h) echo "<th>$h</th>";
        echo '</tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            foreach (array_values($r) as $v) echo '<td>'.htmlspecialchars((string)$v).'</td>';
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }

    // ── PDF Export (pure HTML → print as PDF via browser) ─
    if ($format === 'pdf') {
        header('Content-Type: text/html; charset=UTF-8');
        ?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        @page { size: A4 landscape; margin: 1.5cm; }
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #333; }
        .report-header { border-bottom: 3px solid #5a3d82; padding-bottom: 8px; margin-bottom: 16px; }
        .report-header h1 { color: #5a3d82; font-size: 16pt; margin: 0 0 4px; }
        .report-header p  { margin: 2px 0; color: #555; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead th { background: #5a3d82; color: #fff; padding: 6px 8px; font-size: 8pt; border: 1px solid #ccc; }
        tbody td { padding: 5px 8px; border: 1px solid #ddd; font-size: 8pt; }
        tbody tr:nth-child(even) td { background: #f3eeff; }
        .footer { margin-top: 16px; font-size: 8pt; color: #888; border-top: 1px solid #eee; padding-top: 8px; }
        .logo   { font-size: 10pt; font-weight: bold; color: #5a3d82; }
    </style>
</head>
<body onload="window.print()">
    <div class="report-header">
        <div class="logo">College of Computer Studies — CCS Sit-In Monitoring System</div>
        <h1><?= htmlspecialchars($title) ?></h1>
        <?php
        $pdf_extra = '';
        if ($type === 'sitins' && $lab) $pdf_extra = ' &nbsp;|&nbsp; Lab: <strong>'.htmlspecialchars($lab).'</strong>';
        if ($type === 'students') {
            $sf2 = implode(', ', array_filter([$year_level, $course_filter]));
            if ($sf2) $pdf_extra = ' &nbsp;|&nbsp; Filter: <strong>'.htmlspecialchars($sf2).'</strong>';
        }
        ?>
        <p>Period: <strong><?= $from ?></strong> to <strong><?= $to ?></strong><?= $pdf_extra ?> &nbsp;|&nbsp; Total Records: <strong><?= count($rows) ?></strong></p>
        <p>Generated: <?= date('F d, Y H:i') ?></p>
    </div>
    <table>
        <thead><tr><?php foreach ($headers as $h) echo "<th>".htmlspecialchars($h)."</th>"; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr><?php foreach (array_values($r) as $v) echo '<td>'.htmlspecialchars((string)$v).'</td>'; ?></tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
            <tr><td colspan="<?= count($headers) ?>" style="text-align:center;color:#999;padding:20px;">No records for this period.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <div class="footer">CCS Sit-In Monitoring System &copy; <?= date('Y') ?> College of Computer Studies</div>
</body>
</html><?php
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | CCS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'includes/admin_navbar.php'; ?>
<div class="admin-wrapper">
<div class="admin-content">

    <?php include 'includes/admin_alerts.php'; ?>

    <div class="page-header">
        <h2>Reports</h2>
        <small class="text-muted">Generate and export system data reports</small>
    </div>

    <!-- ── Report Generator ── -->
    <div class="row g-4">

        <?php
        $report_types = [
            ['id'=>'sitins',   'title'=>'Sit-In History', 'desc'=>'All sit-in sessions with student info, purpose, lab, and duration.', 'has_lab'=>true,  'has_student_filters'=>false],
            ['id'=>'students', 'title'=>'Student List',   'desc'=>'All enrolled students with total sit-in counts and enrollment info.',  'has_lab'=>false, 'has_student_filters'=>true],
        ];

        // Available labs from DB (fallback to static list)
        $labs_result = $conn->query("SELECT DISTINCT lab FROM sitins WHERE lab IS NOT NULL AND lab != '' ORDER BY lab ASC");
        $available_labs = [];
        while ($lr = $labs_result->fetch_row()) $available_labs[] = $lr[0];
        if (empty($available_labs)) $available_labs = ['Lab 524','Lab 526','Lab 528','Lab 530','Mac Lab'];

        foreach ($report_types as $rt):
        ?>
        <div class="col-lg-6">
            <div class="card-ccs p-4 h-100">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="stat-icon"></div>
                    <div>
                        <h5 class="fw-bold mb-0" style="color:#5a3d82;"><?= $rt['title'] ?></h5>
                        <small class="text-muted"><?= $rt['desc'] ?></small>
                    </div>
                </div>

                <form method="GET" class="mb-0">
                    <input type="hidden" name="type" value="<?= $rt['id'] ?>">

                    <div class="row g-2 mb-3">

                        <?php if (!$rt['has_student_filters']): ?>
                        <!-- Date range for Sit-In History -->
                        <div class="col-6">
                            <label class="form-label small fw-600">From</label>
                            <input type="date" name="from" class="form-control form-control-sm"
                                   value="<?= $from ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-600">To</label>
                            <input type="date" name="to" class="form-control form-control-sm"
                                   value="<?= $to ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <?php endif; ?>

                        <?php if ($rt['has_lab']): ?>
                        <div class="col-12">
                            <label class="form-label small fw-600">
                                Laboratory <span class="text-muted fw-normal">(optional — leave blank for all)</span>
                            </label>
                            <select name="lab" class="form-select form-select-sm">
                                <option value="">All Laboratories</option>
                                <?php foreach ($available_labs as $l): ?>
                                    <option value="<?= htmlspecialchars($l) ?>" <?= $lab===$l?'selected':'' ?>>
                                        <?= htmlspecialchars($l) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ($rt['has_student_filters']): ?>
                        <!-- Date range for Student List -->
                        <div class="col-6">
                            <label class="form-label small fw-600">From</label>
                            <input type="date" name="from" class="form-control form-control-sm"
                                   value="<?= $from ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-600">To</label>
                            <input type="date" name="to" class="form-control form-control-sm"
                                   value="<?= $to ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <!-- Year Level & Course filters -->
                        <div class="col-6">
                            <label class="form-label small fw-600">
                                Year Level <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <select name="year_level" class="form-select form-select-sm">
                                <option value="">All Year Levels</option>
                                <?php foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $yr): ?>
                                    <option value="<?= $yr ?>" <?= $year_level===$yr?'selected':'' ?>><?= $yr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-600">
                                Program <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <select name="course_filter" class="form-select form-select-sm">
                                <option value="">All Programs</option>
                                <?php foreach (['BSIT','BSCS','BSCpE','BSIM','BSEMC'] as $c): ?>
                                    <option value="<?= $c ?>" <?= $course_filter===$c?'selected':'' ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" name="format" value="csv" class="btn btn-outline-success btn-sm flex-grow-1">
                            <i class="bi bi-filetype-csv me-1"></i>CSV
                        </button>
                        <button type="submit" name="format" value="doc" class="btn btn-outline-primary btn-sm flex-grow-1">
                            <i class="bi bi-filetype-doc me-1"></i>Word
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-outline-danger btn-sm flex-grow-1">
                            <i class="bi bi-file-pdf me-1"></i>PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>

    </div>

    <!-- ── Quick Summary Preview ── -->
    <div class="row g-3 mt-2">
        <div class="col-12">
            <div class="card-ccs p-4">
                <h5 class="fw-bold mb-3" style="color:#5a3d82;">Quick Summary</h5>
                <div class="row g-3 text-center">
                    <?php
                    $sum = [
                        ['This Month Sit-Ins', $conn->query("SELECT COUNT(*) FROM sitins WHERE MONTH(sit_in_time)=MONTH(NOW()) AND YEAR(sit_in_time)=YEAR(NOW())")->fetch_row()[0]],
                        ['Active Today',       $conn->query("SELECT COUNT(*) FROM sitins WHERE DATE(sit_in_time)=CURDATE()")->fetch_row()[0]],
                        ['Students Enrolled',  $conn->query("SELECT COUNT(*) FROM students WHERE role='student'")->fetch_row()[0]],
                    ];
                    foreach ($sum as [$label, $val]):
                    ?>
                    <div class="col-6 col-md-4">
                        <div class="ccs-card py-3" style="background:#f8f5ff;">
                            <div style="font-size:1.5rem;font-weight:700;color:#5a3d82;"><?= number_format((int)$val) ?></div>
                            <div class="small text-muted"><?= $label ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</div></div>
<footer class="adm-footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies &bull; CCS Sit-In Monitoring System</small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>document.getElementById('sidebarToggle')?.addEventListener('click',()=>document.getElementById('adminSidebar').classList.toggle('show'));</script>
</body>
</html>