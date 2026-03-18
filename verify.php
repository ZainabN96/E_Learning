<?php
declare(strict_types=1);
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/CertificateRepository.php';

$certRepo = new CertificateRepository();
$certId   = trim($_GET['id'] ?? '');
$cert     = $certId ? $certRepo->get($certId) : [];

$lang          = load_lang();
$platformTitle = $lang['platform_title'] ?? 'E-Learning Platform';
$valid         = !empty($cert);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate Verification — <?= htmlspecialchars($platformTitle) ?></title>
    <style>
        :root { --green:#16a34a;--red:#dc2626;--blue:#2563eb; }
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f9fafb;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem}
        .card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:2.5rem;max-width:520px;width:100%;text-align:center}
        .icon{font-size:4rem;margin-bottom:1rem}
        h1{font-size:1.4rem;margin-bottom:.5rem}
        .badge-valid{display:inline-block;background:#dcfce7;color:var(--green);font-size:.85rem;font-weight:700;padding:.35rem 1rem;border-radius:99px;margin-bottom:1.5rem}
        .badge-invalid{display:inline-block;background:#fee2e2;color:var(--red);font-size:.85rem;font-weight:700;padding:.35rem 1rem;border-radius:99px;margin-bottom:1.5rem}
        table{width:100%;text-align:left;border-collapse:collapse;margin:1.25rem 0;font-size:.9rem}
        td{padding:.5rem .75rem;border-bottom:1px solid #f3f4f6}
        td:first-child{color:#6b7280;width:130px;font-weight:600}
        .btn{display:inline-block;margin-top:1rem;padding:.6rem 1.5rem;background:var(--blue);color:#fff;border-radius:6px;text-decoration:none;font-size:.9rem}
        .cert-id{font-family:monospace;font-size:.85rem;color:#374151;background:#f3f4f6;padding:.2rem .5rem;border-radius:4px}
    </style>
</head>
<body>
    <div class="card">
        <?php if ($valid): ?>
            <div class="icon">✅</div>
            <h1>Certificate Verified</h1>
            <span class="badge-valid">VALID &amp; AUTHENTIC</span>

            <table>
                <tr><td>Student</td><td><strong><?= htmlspecialchars($cert['student_name'] ?? '—') ?></strong></td></tr>
                <tr><td>Course</td><td><?= htmlspecialchars($cert['course_title'] ?? '—') ?></td></tr>
                <tr><td>Batch</td><td><?= htmlspecialchars($cert['batch_name'] ?? '—') ?></td></tr>
                <tr><td>Trainer</td><td><?= htmlspecialchars($cert['trainer_name'] ?? '—') ?></td></tr>
                <tr><td>Duration</td><td>
                    <?= !empty($cert['start_date']) ? date('d M Y', strtotime($cert['start_date'])) : '—' ?>
                    &ndash;
                    <?= !empty($cert['end_date'])   ? date('d M Y', strtotime($cert['end_date']))   : '—' ?>
                </td></tr>
                <tr><td>Issued On</td><td><?= !empty($cert['issued_at']) ? date('d M Y', strtotime($cert['issued_at'])) : '—' ?></td></tr>
                <tr><td>Issued By</td><td><?= htmlspecialchars($platformTitle) ?></td></tr>
                <tr><td>Certificate ID</td><td><span class="cert-id"><?= htmlspecialchars($cert['id'] ?? '') ?></span></td></tr>
            </table>

            <a href="/E_Learning/student/certificate.php?id=<?= urlencode($certId) ?>" class="btn">
                📜 View Certificate
            </a>

        <?php else: ?>
            <div class="icon">❌</div>
            <h1>Certificate Not Found</h1>
            <span class="badge-invalid">INVALID</span>
            <p style="color:#6b7280;font-size:.9rem">
                No certificate with ID <span class="cert-id"><?= htmlspecialchars($certId ?: 'none') ?></span> exists in our system.
                It may be fake, expired, or the ID is incorrect.
            </p>
        <?php endif; ?>

        <div style="margin-top:1.5rem">
            <form method="get" style="display:flex;gap:.5rem;justify-content:center">
                <input type="text" name="id" value="<?= htmlspecialchars($certId) ?>"
                       placeholder="Enter Certificate ID…"
                       style="padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:6px;font-size:.9rem;flex:1;max-width:280px">
                <button type="submit"
                        style="padding:.5rem 1rem;background:#374151;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.9rem">
                    Verify
                </button>
            </form>
        </div>
    </div>
</body>
</html>
