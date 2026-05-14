<?php
/**
 * print_id.php — Student QR ID Card
 * Size: 74mm × 105mm (A7 — standard PH school ID)
 * Usage: print_id.php?id=STUDENT_ID
 */

session_start();
require_once __DIR__ . '/db.php';
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access denied.');
}

$student_id = (int)($_GET['id'] ?? 0);
if (!$student_id) exit('Invalid student ID.');

$conn = db_connect();

$stmt = $conn->prepare("
    SELECT s.*, COALESCE(e.id_photo_path, '') AS _id_photo_path
    FROM students s
    LEFT JOIN encodings e ON e.student_id = s.id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) exit('Student not found.');

$full_name  = strtoupper(trim(
    ($row['last_name'] ?? '') . ', ' .
    ($row['first_name'] ?? '') .
    (!empty($row['middle_name']) ? ' ' . strtoupper(substr($row['middle_name'], 0, 1)) . '.' : '') .
    (!empty($row['extension_name']) ? ' ' . $row['extension_name'] : '')
));
$lrn        = $row['lrn'] ?: 'N/A';
$photo_path = $row['_id_photo_path'] ?? '';

$qr_data = 'LRN:' . $lrn . '|NAME:' . $full_name;
$qr_url  = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&ecc=M&data=' . urlencode($qr_data);

$school_name  = 'Daniel R. Aguinaldo National High School';
$school_short = 'DRANHS';
$logo_path    = '../uploads/school_logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ID — <?php echo htmlspecialchars($full_name); ?></title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: #e2e8f0;
    font-family: Arial, sans-serif;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 24px;
    gap: 14px;
  }

  /* ── Controls ── */
  .controls { display: flex; gap: 10px; }
  .btn {
    padding: 10px 22px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    border: none;
  }
  .btn-print { background: #009b5a; color: #fff; }
  .btn-back  { background: #cbd5e1; color: #334155; }

  /* ── Print hint ── */
  .print-hint {
    background: #fefce8;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 11.5px;
    color: #92400e;
    text-align: center;
    max-width: 340px;
    line-height: 1.5;
  }

  /* ══════════════════════════════
     ID CARD — 74mm × 105mm (A7)
  ══════════════════════════════ */
  .id-card {
    width: 74mm;
    height: 105mm;
    background: #fff;
    border-radius: 3mm;
    overflow: hidden;
    box-shadow: 0 6px 28px rgba(0,0,0,0.22);
    display: flex;
    flex-direction: column;
  }

  /* ── Header ── */
  .id-header {
    background: #009b5a;
    padding: 3mm 3mm 2.5mm;
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 2.5mm;
    flex-shrink: 0;
  }

  .logo-wrap {
    width: 12mm;
    height: 12mm;
    border-radius: 50%;
    background: #fff;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 0.5mm solid rgba(255,255,255,0.5);
  }
  .logo-wrap img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }
  .logo-wrap .logo-text {
    font-size: 4pt;
    font-weight: 900;
    color: #009b5a;
    text-align: center;
    line-height: 1.2;
  }

  .school-text {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.8mm;
  }
  .school-name {
    font-size: 6pt;
    font-weight: 900;
    color: #fff;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.01em;
    line-height: 1.4;
    white-space: normal;
    word-break: break-word;
  }
  .school-sub {
    font-size: 4.5pt;
    color: rgba(255,255,255,0.85);
    text-align: center;
    margin-top: 0.5mm;
  }

  /* ── Body ── */
  .id-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 3.5mm 3mm 2mm;
    gap: 2.5mm;
  }

  /* 1×1 inch = 25.4mm */
  .id-photo {
    width: 25mm;
    height: 25mm;
    object-fit: cover;
    border-radius: 1.5mm;
    border: 0.5mm solid #cbd5e1;
    flex-shrink: 0;
  }
  .id-photo-placeholder {
    width: 25mm;
    height: 25mm;
    border-radius: 1.5mm;
    border: 0.5mm dashed #cbd5e1;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 6pt;
    flex-shrink: 0;
  }

  .id-name {
    font-size: 7.5pt;
    font-weight: 900;
    color: #0f172a;
    text-align: center;
    line-height: 1.3;
    word-break: break-word;
    padding: 0 2mm;
  }

  .id-lrn {
    font-size: 7pt;
    font-weight: 700;
    color: #009b5a;
    text-align: center;
    letter-spacing: 0.12em;
  }

  /* QR — reduced to 38mm so footer is always visible */
  .id-qr-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
  }
  .id-qr {
    width: 38mm;
    height: 38mm;
    object-fit: contain;
  }

  /* ── Footer ── */
  .id-footer {
    background: #009b5a;
    padding: 2.5mm 3mm;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .id-footer span {
    font-size: 7pt;
    font-weight: 900;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 0.2em;
  }

  /* ── Print ── */
  @media print {
    body { background: #fff; padding: 0; margin: 0; display: block; }
    .controls, .print-hint { display: none !important; }
    .id-card { box-shadow: none; border-radius: 0; }
    @page { size: 74mm 105mm portrait; margin: 0; }
  }
</style>
</head>
<body>

<div class="controls">
  <button class="btn btn-back" onclick="window.close()">← Back</button>
  <button class="btn btn-print" onclick="window.print()">🖨 Print ID</button>
</div>

<div class="print-hint">
  ⚠ Print dialog → <strong>Scale: 100%</strong> → Paper: <strong>Custom 74×105mm</strong>
</div>

<div class="id-card">

  <!-- Header -->
  <div class="id-header">
    <div class="logo-wrap">
      <?php if (file_exists($logo_path)): ?>
        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo">
      <?php else: ?>
        <span class="logo-text"><?php echo htmlspecialchars($school_short); ?></span>
      <?php endif; ?>
    </div>
    <div class="school-text">
      <div class="school-name">Daniel R. Aguinaldo<br>National High School</div>
      <div class="school-sub">Senior High School Department</div>
    </div>
  </div>

  <!-- Body -->
  <div class="id-body">

    <?php if ($photo_path && file_exists(__DIR__ . '/' . $photo_path)): ?>
      <img class="id-photo" src="<?php echo htmlspecialchars($photo_path); ?>" alt="Photo">
    <?php else: ?>
      <div class="id-photo-placeholder">No Photo</div>
    <?php endif; ?>

    <div class="id-name"><?php echo htmlspecialchars($full_name); ?></div>
    <div class="id-lrn"><?php echo htmlspecialchars($lrn); ?></div>

    <div class="id-qr-wrap">
      <img class="id-qr" src="<?php echo htmlspecialchars($qr_url); ?>" alt="QR Code">
    </div>

  </div>

  <!-- Footer -->
  <div class="id-footer">
    <span>Temporary ID</span>
  </div>

</div>

<script>
  const qrImg = document.querySelector('.id-qr');
  if (qrImg && qrImg.complete) {
    window.print();
  } else if (qrImg) {
    qrImg.addEventListener('load', () => window.print());
    qrImg.addEventListener('error', () => window.print());
  } else {
    window.print();
  }
</script>
</body>
</html>
