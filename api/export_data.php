<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();

$type   = $_GET['type']    ?? 'all';
$range  = (int)($_GET['range']   ?? 7);
$hiveId = (int)($_GET['hive_id'] ?? 0);
$format = $_GET['format']  ?? 'csv';

$allowedTypes   = ['all','sensor','audio','alerts','activity','inspection'];
$allowedFormats = ['csv','word','pdf'];
if (!in_array($type,   $allowedTypes))   $type   = 'all';
if (!in_array($format, $allowedFormats)) $format = 'csv';

// ── Fetch helpers (each uses correct column names per table) ──────────
function buildDateWhere(string $col, int $range): string {
    return $range > 0 ? "AND {$col} >= NOW() - INTERVAL {$range} DAY" : '';
}
function buildHiveWhere(string $alias, int $hiveId): string {
    return $hiveId > 0 ? "AND {$alias}.hive_id = {$hiveId}" : '';
}

function fetchSensor($conn, int $range, int $hiveId): array {
    $dc = buildDateWhere('sd.timestamp', $range);
    $hc = buildHiveWhere('sd', $hiveId);
    $sql = "SELECT sd.id,
                   h.name  AS hive_name,
                   h.hive_id AS hive_code,
                   sd.temperature,
                   sd.humidity,
                   sd.co2,
                   sd.sound_level,
                   sd.timestamp AS recorded_at
            FROM sensor_data sd
            LEFT JOIN hives h ON h.id = sd.hive_id
            WHERE 1=1 {$hc} {$dc}
            ORDER BY sd.timestamp DESC";
    $res = $conn->query($sql);
    if (!$res) return [];
    return $res->fetch_all(MYSQLI_ASSOC);
}

function fetchAudio($conn, int $range, int $hiveId): array {
    $dc = buildDateWhere('aa.timestamp', $range);
    $hc = buildHiveWhere('aa', $hiveId);
    $sql = "SELECT aa.id,
                   h.name  AS hive_name,
                   h.hive_id AS hive_code,
                   aa.colony_status,
                   aa.confidence,
                   aa.dominant_freq,
                   aa.sound_level,
                   aa.timestamp AS analyzed_at
            FROM audio_analysis aa
            LEFT JOIN hives h ON h.id = aa.hive_id
            WHERE 1=1 {$hc} {$dc}
            ORDER BY aa.timestamp DESC";
    $res = $conn->query($sql);
    if (!$res) return [];
    return $res->fetch_all(MYSQLI_ASSOC);
}

function fetchAlerts($conn, int $range, int $hiveId): array {
    $dc = buildDateWhere('al.created_at', $range);
    $hc = buildHiveWhere('al', $hiveId);
    $sql = "SELECT al.id,
                   h.name  AS hive_name,
                   h.hive_id AS hive_code,
                   al.alert_type,
                   al.severity,
                   al.message,
                   al.recommended_action,
                   CASE al.is_read WHEN 1 THEN 'Dismissed' ELSE 'Active' END AS status,
                   al.created_at
            FROM alerts al
            LEFT JOIN hives h ON h.id = al.hive_id
            WHERE 1=1 {$hc} {$dc}
            ORDER BY al.created_at DESC";
    $res = $conn->query($sql);
    if (!$res) return [];
    return $res->fetch_all(MYSQLI_ASSOC);
}

function fetchActivity($conn, int $range, int $hiveId): array {
    $dc = buildDateWhere('al.timestamp', $range);
    $hc = $hiveId > 0 ? "AND al.hive_id = {$hiveId}" : '';
    $sql = "SELECT al.id,
                   COALESCE(h.name, '—')     AS hive_name,
                   COALESCE(h.hive_id, '—')  AS hive_code,
                   COALESCE(u.username, 'system') AS performed_by,
                   al.event_type,
                   al.details,
                   al.severity,
                   al.timestamp AS recorded_at
            FROM activity_log al
            LEFT JOIN hives h ON h.id = al.hive_id
            LEFT JOIN users u ON u.id = al.user_id
            WHERE 1=1 {$hc} {$dc}
            ORDER BY al.timestamp DESC";
    $res = $conn->query($sql);
    if (!$res) return [];
    return $res->fetch_all(MYSQLI_ASSOC);
}

function fetchInspections($conn, int $range, int $hiveId): array {
    $dc = buildDateWhere('n.inspection_date', $range);
    $hc = $hiveId > 0 ? "AND n.hive_id = {$hiveId}" : '';
    $sql = "SELECT n.id,
                   h.name  AS hive_name,
                   h.hive_id AS hive_code,
                   u.username AS inspector,
                   u.role AS inspector_role,
                   n.inspection_date,
                   n.colony_strength,
                   n.queen_present,
                   n.brood_pattern,
                   n.honey_stores,
                   n.pest_disease_signs,
                   n.actions_taken,
                   n.notes,
                   n.next_inspection_date
            FROM inspection_notes n
            LEFT JOIN hives h ON h.id = n.hive_id
            LEFT JOIN users u ON u.id = n.user_id
            WHERE 1=1 {$hc} {$dc}
            ORDER BY n.inspection_date DESC";
    $res = $conn->query($sql);
    if (!$res) return [];
    return $res->fetch_all(MYSQLI_ASSOC);
}

// ── Collect data ──────────────────────────────────────────────────────
$data = [];
if ($type === 'sensor'     || $type === 'all') $data['Sensor Data']      = fetchSensor($conn, $range, $hiveId);
if ($type === 'audio'      || $type === 'all') $data['Audio Analysis']   = fetchAudio($conn, $range, $hiveId);
if ($type === 'alerts'     || $type === 'all') $data['Alerts']           = fetchAlerts($conn, $range, $hiveId);
if ($type === 'activity'   || $type === 'all') $data['Activity Log']     = fetchActivity($conn, $range, $hiveId);
if ($type === 'inspection' || $type === 'all') $data['Inspection Notes'] = fetchInspections($conn, $range, $hiveId);

$rangeLabel = match($range) {
    7 => '7days', 30 => '30days', 90 => '3months', 180 => '6months', 365 => '1year',
    default => 'alltime'
};
$timestamp = date('Ymd_His');
$filename  = "beeguard_{$type}_{$rangeLabel}_{$timestamp}";

$uname = $_SESSION['username'] ?? 'user';
$totalRecordsExported = array_sum(array_map('count', $data));
logActivity($conn, null, 'Data Export',
    "User '$uname' exported $type data ($format format, $rangeLabel, $hiveText) — $totalRecordsExported records", 'info');

$rangeText = match($range) {
    7 => 'Last 7 Days', 30 => 'Last 30 Days', 90 => 'Last 3 Months',
    180 => 'Last 6 Months', 365 => 'Last Year', default => 'All Time'
};
$hiveText   = $hiveId > 0 ? "Hive #{$hiveId}" : 'All Hives';
$exportedAt = date('F j, Y  H:i:s');

// ── Word (.doc) ───────────────────────────────────────────────────────
if ($format === 'word') {
    header('Content-Type: application/vnd.ms-word');
    header("Content-Disposition: attachment; filename=\"{$filename}.doc\"");
    header('Cache-Control: no-cache, no-store');

    $totalRecords = array_sum(array_map('count', $data));
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body  { font-family:Calibri,Arial,sans-serif; font-size:11pt; color:#1f2937; margin:40px; }
  h1    { font-size:20pt; color:#d97706; margin-bottom:4px; }
  h2    { font-size:11pt; color:#6b7280; font-weight:normal; margin-bottom:18px; }
  .meta { font-size:10pt; color:#6b7280; margin-bottom:28px; border-bottom:2px solid #f59e0b; padding-bottom:10px; }
  .meta strong { color:#111827; }
  h3    { font-size:13pt; color:#92400e; background:#fef3c7; padding:5px 10px;
          border-left:4px solid #f59e0b; margin:28px 0 10px; }
  table { width:100%; border-collapse:collapse; font-size:9.5pt; margin-bottom:8px; }
  th    { background:#f3f4f6; padding:6px 8px; text-align:left; font-weight:bold;
          border:1px solid #d1d5db; color:#374151; }
  td    { padding:5px 8px; border:1px solid #e5e7eb; vertical-align:top; }
  tr:nth-child(even) td { background:#f9fafb; }
  .empty { color:#9ca3af; font-style:italic; padding:10px; }
  .footer { font-size:9pt; color:#9ca3af; margin-top:36px; border-top:1px solid #e5e7eb; padding-top:10px; text-align:center; }
</style></head><body>';

    echo '<h1>&#128029; BeeGuard IoT Monitor</h1>';
    echo '<h2>Data Export Report</h2>';
    echo '<div class="meta">';
    echo '<strong>Data Type:</strong> ' . htmlspecialchars(ucwords(str_replace('_', ' ', $type === 'all' ? 'All Data' : $type))) . ' &nbsp;|&nbsp; ';
    echo '<strong>Date Range:</strong> ' . $rangeText . ' &nbsp;|&nbsp; ';
    echo '<strong>Hive:</strong> ' . $hiveText . ' &nbsp;|&nbsp; ';
    echo '<strong>Generated:</strong> ' . $exportedAt . ' &nbsp;|&nbsp; ';
    echo '<strong>Total Records:</strong> ' . number_format($totalRecords);
    echo '</div>';

    foreach ($data as $sectionName => $rows) {
        echo '<h3>' . strtoupper($sectionName) . ' &mdash; ' . count($rows) . ' record' . (count($rows) !== 1 ? 's' : '') . '</h3>';
        if (empty($rows)) {
            echo '<p class="empty">No records found for the selected filters.</p>';
            continue;
        }
        echo '<table><thead><tr>';
        foreach (array_keys($rows[0]) as $col) {
            echo '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $col))) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $val) {
                echo '<td>' . htmlspecialchars((string)($val ?? '—')) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '<div class="footer">BeeGuard IoT Monitor &bull; Report generated ' . $exportedAt . '</div>';
    echo '</body></html>';
    exit;
}

// ── CSV ───────────────────────────────────────────────────────────────
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
    header('Cache-Control: no-cache, no-store');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel

    $out = fopen('php://output', 'w');
    foreach ($data as $sectionName => $rows) {
        if ($type === 'all') {
            fputcsv($out, ["=== {$sectionName} ==="]);
        }
        if (empty($rows)) {
            fputcsv($out, ['No records found.']);
        } else {
            fputcsv($out, array_map(fn($k) => ucwords(str_replace('_',' ',$k)), array_keys($rows[0])));
            foreach ($rows as $row) fputcsv($out, $row);
        }
        if ($type === 'all') fputcsv($out, []);
    }
    fclose($out);
    exit;
}

// ── PDF (print-ready HTML) ────────────────────────────────────────────
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>BeeGuard Export Report</title>
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:Arial,sans-serif; font-size:12px; color:#1f2937; padding:30px; }
  .cover { text-align:center; padding:40px 0 28px; border-bottom:3px solid #f59e0b; margin-bottom:30px; }
  .cover h1 { font-size:26px; color:#f59e0b; letter-spacing:1px; }
  .cover h2 { font-size:15px; color:#374151; margin-top:6px; }
  .meta { display:flex; flex-wrap:wrap; justify-content:center; gap:24px 40px; margin-top:16px; font-size:12px; color:#6b7280; }
  .meta span strong { color:#111827; }
  .section { margin-bottom:36px; }
  .section-title { font-size:13px; font-weight:bold; background:#fef3c7; color:#92400e;
                   padding:6px 12px; border-left:4px solid #f59e0b; margin-bottom:10px;
                   display:flex; align-items:center; justify-content:space-between; }
  .section-title small { font-weight:normal; color:#b45309; }
  table { width:100%; border-collapse:collapse; font-size:11px; }
  thead { background:#f3f4f6; }
  th { padding:7px 8px; text-align:left; font-weight:bold; color:#374151;
       border:1px solid #d1d5db; white-space:nowrap; }
  td { padding:6px 8px; border:1px solid #e5e7eb; vertical-align:top; word-break:break-word; max-width:220px; }
  tr:nth-child(even) td { background:#f9fafb; }
  .badge { display:inline-block; padding:2px 7px; border-radius:4px; font-size:10px; font-weight:bold; }
  .badge-critical,.badge-critical td { background:#fee2e2; color:#991b1b; }
  .badge-warning  { background:#fef3c7; color:#92400e; }
  .badge-info     { background:#dbeafe; color:#1e40af; }
  .badge-normal   { background:#d1fae5; color:#065f46; }
  .badge-Dismissed{ background:#d1fae5; color:#065f46; }
  .badge-Active   { background:#fef3c7; color:#92400e; }
  .empty { color:#9ca3af; font-style:italic; padding:16px; text-align:center; }
  .footer { text-align:center; font-size:10px; color:#9ca3af; margin-top:40px;
            border-top:1px solid #e5e7eb; padding-top:12px; }
  .print-btn { background:#f59e0b; color:#fff; border:none; padding:8px 20px;
               border-radius:6px; font-weight:bold; cursor:pointer; font-size:13px; }
  @media print {
    body { padding:10px; }
    .no-print { display:none !important; }
    .section { page-break-inside:avoid; }
  }
</style>
</head>
<body>

<div class="no-print" style="background:#fef3c7;border:1px solid #f59e0b;padding:10px 16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
  <strong>&#128438; Save as PDF:</strong>
  Press <kbd style="background:#fff;border:1px solid #d1d5db;padding:2px 8px;border-radius:4px;font-size:12px;">Ctrl + P</kbd>
  → choose <em>"Save as PDF"</em>
  <button class="print-btn" onclick="window.print()" style="margin-left:auto;">&#128438; Print / Save PDF</button>
</div>

<div class="cover">
  <h1>&#128029; BeeGuard IoT Monitor</h1>
  <h2>Data Export Report</h2>
  <div class="meta">
    <span><strong>Data Type:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_',' ',$type === 'all' ? 'All Data' : $type))); ?></span>
    <span><strong>Date Range:</strong> <?php echo $rangeText; ?></span>
    <span><strong>Hive Filter:</strong> <?php echo $hiveText; ?></span>
    <span><strong>Generated:</strong> <?php echo $exportedAt; ?></span>
  </div>
</div>

<?php foreach ($data as $sectionName => $rows): ?>
<div class="section">
  <div class="section-title">
    <?php echo strtoupper($sectionName); ?>
    <small><?php echo count($rows); ?> record<?php echo count($rows) !== 1 ? 's' : ''; ?></small>
  </div>
  <?php if (empty($rows)): ?>
    <p class="empty">No records found for the selected filters.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr><?php foreach (array_keys($rows[0]) as $col): ?>
        <th><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$col))); ?></th>
      <?php endforeach; ?></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr><?php foreach ($row as $key => $val):
          $cell = htmlspecialchars((string)($val ?? '—'));
          if ($key === 'severity') {
              $cell = "<span class=\"badge badge-{$val}\">" . strtoupper((string)$val) . "</span>";
          } elseif ($key === 'status') {
              $cell = "<span class=\"badge badge-{$val}\">" . htmlspecialchars((string)$val) . "</span>";
          } elseif ($key === 'colony_status') {
              $label = ucwords(str_replace('_', ' ', (string)$val));
              $cell = "<span class=\"badge badge-normal\">{$label}</span>";
          }
      ?><td><?php echo $cell; ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<div class="footer">BeeGuard IoT Monitor &bull; Report generated <?php echo $exportedAt; ?> &bull; <?php echo array_sum(array_map('count', $data)); ?> total records</div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 500));</script>
</body>
</html>
<?php exit;
