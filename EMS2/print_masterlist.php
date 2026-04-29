<?php
/**
 * print_masterlist.php — Section Masterlist (A4 Portrait)
 * Usage: print_masterlist.php?section=SECTION_NAME&classroom_id=ID
 */

session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access denied.');
}

$section      = trim($_GET['section']      ?? '');
$classroom_id = (int)($_GET['classroom_id'] ?? 0);
if (!$section) exit('No section specified.');

$conn = new mysqli('localhost', 'root', '', 'dranhswin');
if ($conn->connect_error) exit('Database error.');

// Fetch classroom info
$cr = null;
if ($classroom_id) {
    $s = $conn->prepare("SELECT c.*, a.name AS adviser_name_full FROM classrooms c LEFT JOIN advisers_accounts a ON a.id = c.adviser_id WHERE c.id = ? LIMIT 1");
    $s->bind_param("i", $classroom_id);
    $s->execute();
    $cr = $s->get_result()->fetch_assoc();
    $s->close();
}

// Fetch school year from settings
$sy = '';
$sr = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='academic_year' LIMIT 1");
if ($sr && $row = $sr->fetch_assoc()) $sy = $row['setting_value'];

// Fetch enrolled students in this section
$students = [];
$stmt = $conn->prepare("
    SELECT s.last_name, s.first_name, s.middle_name, s.extension_name,
           s.lrn, s.sex, s.birthdate, s.grade_level, s.track, s.pathway_strand,
           s.enrollment_status
    FROM students s
    WHERE s.assigned_section = ? AND s.enrollment_status = 'enrolled'
    ORDER BY s.last_name ASC, s.first_name ASC
");
$stmt->bind_param("s", $section);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $students[] = $row;
$stmt->close();
$conn->close();

require_once __DIR__ . '/pathway_strand_catalog.php';

function ml_name($r) {
    $n = strtoupper(($r['last_name'] ?? '') . ', ' . ($r['first_name'] ?? ''));
    if (!empty($r['middle_name'])) $n .= ' ' . strtoupper(substr($r['middle_name'], 0, 1)) . '.';
    if (!empty($r['extension_name'])) $n .= ' ' . $r['extension_name'];
    return $n;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Masterlist — <?php echo htmlspecialchars($section); ?></title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: Arial, sans-serif;
    font-size: 10pt;
    color: #1e293b;
    background: #f1f5f9;
    padding: 20px;
  }

  .controls {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    justify-content: flex-end;
  }
  .btn {
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    border: none;
  }
  .btn-print { background: #009b5a; color: #fff; }
  .btn-back  { background: #e2e8f0; color: #334155; }

  .page {
    width: 210mm;
    min-height: 297mm;
    background: #fff;
    margin: 0 auto;
    padding: 15mm 15mm 15mm 15mm;
    box-shadow: 0 4px 20px rgba(0,0,0,0.12);
  }

  /* Header */
  .header {
    text-align: center;
    margin-bottom: 8mm;
    border-bottom: 2px solid #009b5a;
    padding-bottom: 4mm;
  }
  .header .school { font-size: 13pt; font-weight: 900; text-transform: uppercase; color: #009b5a; }
  .header .doc-title { font-size: 11pt; font-weight: 700; margin-top: 2mm; }
  .header .meta { font-size: 9pt; color: #64748b; margin-top: 1mm; }

  /* Info row */
  .info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5mm;
    font-size: 9.5pt;
  }
  .info-row .info-item { display: flex; flex-direction: column; gap: 1mm; }
  .info-row .info-label { font-size: 7.5pt; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; }
  .info-row .info-value { font-weight: 700; color: #0f172a; }

  /* Table */
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9pt;
  }
  thead th {
    background: #009b5a;
    color: #fff;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 7.5pt;
    letter-spacing: 0.05em;
    padding: 3mm 2mm;
    text-align: left;
    border: 1px solid #007a47;
  }
  thead th.center { text-align: center; }
  tbody tr:nth-child(even) { background: #f8fafc; }
  tbody tr:hover { background: #ecfdf5; }
  tbody td {
    padding: 2.5mm 2mm;
    border: 1px solid #e2e8f0;
    vertical-align: middle;
  }
  tbody td.center { text-align: center; }
  .no-data {
    text-align: center;
    padding: 10mm;
    color: #94a3b8;
    font-style: italic;
  }

  /* Footer */
  .footer {
    margin-top: 8mm;
    display: flex;
    justify-content: space-between;
    font-size: 8.5pt;
  }
  .sig-block { text-align: center; width: 45%; }
  .sig-line { border-top: 1px solid #334155; margin-top: 8mm; padding-top: 1mm; font-weight: 700; font-size: 9pt; }
  .sig-label { font-size: 7.5pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }

  .count-row {
    margin-top: 3mm;
    font-size: 8.5pt;
    color: #64748b;
    text-align: right;
  }

  @media print {
    body { background: #fff; padding: 0; }
    .controls { display: none !important; }
    .page { box-shadow: none; margin: 0; padding: 12mm; width: 100%; }
    @page { size: A4 portrait; margin: 0; }
  }
</style>
</head>
<body>

<div class="controls">
  <button class="btn btn-back" onclick="window.close()">← Back</button>
  <button class="btn btn-print" onclick="window.print()">🖨 Print Masterlist</button>
</div>

<div class="page">

  <!-- Header -->
  <div class="header">
    <div class="school">Daniel R. Aguinaldo National High School</div>
    <div class="doc-title">Section Masterlist — Enrolled Students</div>
    <div class="meta">Senior High School Department &nbsp;·&nbsp; S.Y. <?php echo htmlspecialchars($sy); ?></div>
  </div>

  <!-- Info row -->
  <div class="info-row">
    <div class="info-item">
      <span class="info-label">Section</span>
      <span class="info-value"><?php echo htmlspecialchars($section); ?></span>
    </div>
    <div class="info-item">
      <span class="info-label">Grade Level</span>
      <span class="info-value"><?php echo htmlspecialchars($cr['grade_level'] ?? '--'); ?></span>
    </div>
    <div class="info-item">
      <span class="info-label">Track</span>
      <span class="info-value"><?php echo htmlspecialchars($cr['track'] ?? '--'); ?></span>
    </div>
    <div class="info-item">
      <span class="info-label">Pathway / Strand</span>
      <span class="info-value"><?php echo htmlspecialchars(get_pathway_strand_label($cr['grade_level'] ?? '', $cr['pathway_strand'] ?? '')); ?></span>
    </div>
    <div class="info-item">
      <span class="info-label">Adviser</span>
      <span class="info-value"><?php echo htmlspecialchars($cr['adviser_name_full'] ?? $cr['adviser_name'] ?? '--'); ?></span>
    </div>
    <div class="info-item">
      <span class="info-label">Date Printed</span>
      <span class="info-value"><?php echo date('F d, Y'); ?></span>
    </div>
  </div>

  <!-- Table -->
  <table>
    <thead>
      <tr>
        <th style="width:5%">#</th>
        <th style="width:35%">Name</th>
        <th style="width:15%">LRN</th>
        <th class="center" style="width:6%">Sex</th>
        <th style="width:12%">Birthdate</th>
        <th style="width:27%">Pathway / Strand</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($students)): ?>
        <tr><td colspan="6" class="no-data">No enrolled students in this section.</td></tr>
      <?php else: foreach ($students as $i => $s): ?>
        <tr>
          <td class="center"><?php echo $i + 1; ?></td>
          <td><?php echo htmlspecialchars(ml_name($s)); ?></td>
          <td><?php echo htmlspecialchars($s['lrn'] ?: '--'); ?></td>
          <td class="center"><?php echo htmlspecialchars(strtoupper(substr($s['sex'] ?? '', 0, 1))); ?></td>
          <td><?php echo htmlspecialchars($s['birthdate'] ?: '--'); ?></td>
          <td><?php echo htmlspecialchars(get_pathway_strand_label($s['grade_level'] ?? '', $s['pathway_strand'] ?? '')); ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <div class="count-row">
    Total Enrolled: <strong><?php echo count($students); ?></strong>
  </div>

  <!-- Signature blocks -->
  <div class="footer">
    <div class="sig-block">
      <div class="sig-line"><?php echo htmlspecialchars($cr['adviser_name_full'] ?? $cr['adviser_name'] ?? ''); ?></div>
      <div class="sig-label">Class Adviser</div>
    </div>
    <div class="sig-block">
      <div class="sig-line">&nbsp;</div>
      <div class="sig-label">School Principal / Registrar</div>
    </div>
  </div>

</div>

<script>
  window.addEventListener('load', function () {
    // Auto-print after a short delay to let the page render
    // Uncomment below to auto-print:
    // setTimeout(() => window.print(), 500);
  });
</script>
</body>
</html>
