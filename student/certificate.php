<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/CertificateRepository.php';

$certRepo = new CertificateRepository();
$certId   = trim($_GET['id'] ?? '');
$cert     = $certId ? $certRepo->get($certId) : [];

if (empty($cert)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:3rem">
        <h2>Certificate Not Found</h2>
        <p>The certificate ID is invalid or no longer exists.</p>
        <a href="/E_Learning/">← Home</a>
    </body></html>';
    exit;
}

$lang          = load_lang();
$platformTitle = $lang['platform_title'] ?? 'E-Learning Platform';

$studentName  = htmlspecialchars($cert['student_name']  ?? '—');
$courseName   = htmlspecialchars($cert['course_title']  ?? '—');
$batchName    = htmlspecialchars($cert['batch_name']    ?? '—');
$trainerName  = htmlspecialchars($cert['trainer_name']  ?? '—');
$startDate    = !empty($cert['start_date']) ? date('d M Y', strtotime($cert['start_date'])) : '—';
$endDate      = !empty($cert['end_date'])   ? date('d M Y', strtotime($cert['end_date']))   : '—';
$issuedDate   = !empty($cert['issued_at'])  ? date('d F Y', strtotime($cert['issued_at']))  : '—';
$certIdShort  = $cert['id'] ?? '—';
$verifyUrl    = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/E_Learning/verify.php?id=' . urlencode($certId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate — <?= $studentName ?></title>
    <style>
        :root {
            --gold:    #b8860b;
            --gold-lt: #f5e6a3;
            --navy:    #1e3a5f;
            --text:    #1a1a1a;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background: #f0f0f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem;
        }

        /* Print toolbar */
        .no-print {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            font-family: sans-serif;
        }
        .no-print button, .no-print a {
            padding: .6rem 1.4rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: .9rem;
            text-decoration: none;
            display: inline-block;
        }
        .btn-print   { background: #2563eb; color: #fff; }
        .btn-close   { background: #e5e7eb; color: #374151; }

        /* Certificate card */
        .certificate {
            width: 100%;
            max-width: 860px;
            background: #fff;
            border: 12px double var(--gold);
            outline: 3px solid var(--gold);
            outline-offset: -18px;
            padding: 3.5rem 4rem;
            position: relative;
            box-shadow: 0 8px 40px rgba(0,0,0,.18);
        }

        /* Corner decorations */
        .certificate::before,
        .certificate::after {
            content: '✦';
            position: absolute;
            font-size: 2rem;
            color: var(--gold);
            top: 10px;
            left: 14px;
        }
        .certificate::after { left: auto; right: 14px; }

        .cert-header {
            text-align: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--gold-lt);
            padding-bottom: 1.5rem;
        }
        .cert-header__brand {
            font-size: 1rem;
            letter-spacing: .25em;
            text-transform: uppercase;
            color: var(--navy);
            font-family: sans-serif;
            font-weight: 700;
        }
        .cert-header__icon { font-size: 2.5rem; margin: .5rem 0; }
        .cert-header__title {
            font-size: 2.4rem;
            font-weight: 700;
            color: var(--navy);
            letter-spacing: .05em;
            margin-top: .25rem;
        }
        .cert-header__subtitle {
            font-size: .95rem;
            color: #666;
            font-family: sans-serif;
            margin-top: .35rem;
            font-style: italic;
        }

        .cert-body { text-align: center; padding: 1.5rem 0; }
        .cert-body__presented {
            font-size: 1rem;
            color: #555;
            margin-bottom: .5rem;
            font-style: italic;
        }
        .cert-body__name {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--navy);
            border-bottom: 2px solid var(--gold-lt);
            display: inline-block;
            padding: 0 2rem .3rem;
            margin-bottom: 1.25rem;
        }
        .cert-body__completed {
            font-size: 1rem;
            color: #555;
            margin-bottom: .5rem;
            font-style: italic;
        }
        .cert-body__course {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: .35rem;
        }
        .cert-body__batch {
            font-size: 1rem;
            color: #666;
            font-family: sans-serif;
        }
        .cert-body__dates {
            margin-top: .75rem;
            font-size: .9rem;
            color: #777;
            font-family: sans-serif;
        }

        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--gold-lt);
        }
        .cert-signature {
            text-align: center;
            min-width: 180px;
        }
        .cert-signature__line {
            width: 160px;
            border-bottom: 1.5px solid #333;
            margin: 0 auto .4rem;
            height: 36px;
        }
        .cert-signature__name {
            font-size: .88rem;
            font-weight: 700;
            color: var(--text);
        }
        .cert-signature__role {
            font-size: .78rem;
            color: #777;
            font-family: sans-serif;
        }

        .cert-id-block {
            text-align: center;
            font-family: sans-serif;
        }
        .cert-id-label { font-size: .72rem; color: #aaa; text-transform: uppercase; letter-spacing: .05em; }
        .cert-id-value { font-size: .9rem; font-weight: 700; color: var(--navy); letter-spacing: .08em; }
        .cert-id-verify { font-size: .7rem; color: #999; margin-top: .2rem; word-break: break-all; max-width: 200px; }

        /* Corner ornaments bottom */
        .cert-corner-bl, .cert-corner-br {
            position: absolute;
            bottom: 10px;
            font-size: 2rem;
            color: var(--gold);
        }
        .cert-corner-bl { left: 14px; }
        .cert-corner-br { right: 14px; }

        /* ── Print ── */
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .certificate {
                box-shadow: none;
                max-width: 100%;
                width: 100%;
                border-width: 8px;
            }
        }
    </style>
</head>
<body>

    <!-- Toolbar -->
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨 Print / Save as PDF</button>
        <a href="/E_Learning/student/dashboard.php" class="btn-close">← Back to Dashboard</a>
    </div>

    <!-- Certificate -->
    <div class="certificate">

        <span class="cert-corner-bl">✦</span>
        <span class="cert-corner-br">✦</span>

        <!-- Header -->
        <div class="cert-header">
            <div class="cert-header__brand"><?= htmlspecialchars($platformTitle) ?></div>
            <div class="cert-header__icon">🎓</div>
            <div class="cert-header__title">Certificate of Completion</div>
            <div class="cert-header__subtitle">This is to certify that the following individual has successfully completed the course</div>
        </div>

        <!-- Body -->
        <div class="cert-body">
            <div class="cert-body__presented">This certificate is proudly presented to</div>
            <div class="cert-body__name"><?= $studentName ?></div>
            <div class="cert-body__completed">for successfully completing</div>
            <div class="cert-body__course"><?= $courseName ?></div>
            <div class="cert-body__batch"><?= $batchName ?></div>
            <div class="cert-body__dates">
                Duration: <?= $startDate ?> &ndash; <?= $endDate ?>
            </div>
        </div>

        <!-- Footer: signatures + cert ID -->
        <div class="cert-footer">
            <div class="cert-signature">
                <div class="cert-signature__line"></div>
                <div class="cert-signature__name"><?= $trainerName ?></div>
                <div class="cert-signature__role">Trainer</div>
            </div>

            <div class="cert-id-block">
                <div class="cert-id-label">Certificate ID</div>
                <div class="cert-id-value"><?= htmlspecialchars($certIdShort) ?></div>
                <div class="cert-id-label" style="margin-top:.4rem">Issued on</div>
                <div style="font-size:.82rem;color:var(--navy)"><?= $issuedDate ?></div>
                <div class="cert-id-verify">Verify: <?= htmlspecialchars($verifyUrl) ?></div>
            </div>

            <div class="cert-signature">
                <div class="cert-signature__line"></div>
                <div class="cert-signature__name"><?= htmlspecialchars($platformTitle) ?></div>
                <div class="cert-signature__role">Issuing Authority</div>
            </div>
        </div>
    </div>

</body>
</html>
