<?php
// Helpers for scam awareness and suspicious-activity reports.
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

// Kinds of scam a person can report (key => label).
function scam_report_types(): array
{
    return [
        'fake_pop'        => 'Fake proof of payment / EFT',
        'bank_impersonation' => 'Fake bank call, SMS or email (OTP / PIN request)',
        'sim_swap'        => 'SIM swap or account takeover',
        'phishing_link'   => 'Phishing link or fake website',
        'sars_government' => 'Fake SARS, SASSA or government message',
        'loan_fee'        => 'Loan or "advance fee" scam',
        'investment'      => 'Investment, forex or crypto scheme',
        'job_offer'       => 'Fake job or online "task" offer',
        'marketplace'     => 'Online marketplace, courier or delivery scam',
        'romance_family'  => 'Romance scam or "Hi Mum" / family impersonation',
        'police_extortion'=> 'Fake police, Hawks or extortion call',
        'prize_lottery'   => 'Prize, lottery or inheritance scam',
        'business_fraud'  => 'Business scam (fake invoice, supplier or tender)',
        'other'           => 'Something else that looks suspicious',
    ];
}

// How the scammer made contact (key => label).
function scam_report_channels(): array
{
    return [
        'sms'        => 'SMS',
        'whatsapp'   => 'WhatsApp or Telegram',
        'phone'      => 'Phone call',
        'email'      => 'Email',
        'social'     => 'Facebook, Instagram, TikTok or other social media',
        'website'    => 'Website or app',
        'marketplace'=> 'Online marketplace (e.g. Gumtree, OLX)',
        'in_person'  => 'In person',
        'other'      => 'Other',
    ];
}

// How money (if any) was sent (key => label).
function scam_report_loss_methods(): array
{
    return [
        ''        => 'No money was sent',
        'eft'     => 'Bank transfer / EFT',
        'card'    => 'Card payment',
        'cash'    => 'Cash or cash deposit',
        'voucher' => 'Vouchers (e.g. 1Voucher, OTT)',
        'crypto'  => 'Cryptocurrency',
        'mobile'  => 'Mobile money or airtime',
        'other'   => 'Other',
    ];
}

// What happens to a report (key => label).
function scam_report_statuses(): array
{
    return [
        'received'  => 'Received',
        'reviewing' => 'Being reviewed',
        'resolved'  => 'Reviewed',
        'dismissed' => 'Closed',
    ];
}

// Creates the report table if it is missing, so existing installs need no manual import.
function ensure_scam_reports_table(): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    if (!database_ready()) return $ready = false;

    try {
        $pdo = db();
        if (db_driver() === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS scam_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(24) NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                scam_type VARCHAR(40) NOT NULL,
                channel VARCHAR(40) NOT NULL,
                incident_date DATE NULL,
                suspect_name VARCHAR(140) NULL,
                suspect_contact VARCHAR(255) NULL,
                amount_lost DECIMAL(14,2) NULL,
                loss_method VARCHAR(20) NULL,
                description TEXT NOT NULL,
                reported_to_police TINYINT(1) NOT NULL DEFAULT 0,
                police_case_number VARCHAR(60) NULL,
                reporter_email VARCHAR(190) NULL,
                is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'received',
                admin_note VARCHAR(500) NULL,
                source_hash CHAR(64) NULL,
                created_ts BIGINT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_scam_reports_reference (reference),
                KEY idx_scam_reports_user (user_id),
                KEY idx_scam_reports_status (status, created_ts),
                KEY idx_scam_reports_source (source_hash, created_ts)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS scam_reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reference TEXT NOT NULL UNIQUE,
                user_id INTEGER NULL,
                scam_type TEXT NOT NULL,
                channel TEXT NOT NULL,
                incident_date TEXT NULL,
                suspect_name TEXT NULL,
                suspect_contact TEXT NULL,
                amount_lost REAL NULL,
                loss_method TEXT NULL,
                description TEXT NOT NULL,
                reported_to_police INTEGER NOT NULL DEFAULT 0,
                police_case_number TEXT NULL,
                reporter_email TEXT NULL,
                is_anonymous INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'received',
                admin_note TEXT NULL,
                source_hash TEXT NULL,
                created_ts INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_scam_reports_user ON scam_reports (user_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_scam_reports_status ON scam_reports (status, created_ts)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_scam_reports_source ON scam_reports (source_hash, created_ts)');
        }
        return $ready = true;
    } catch (Throwable $e) {
        error_log('[Imali Smart] Could not prepare scam_reports table: ' . $e->getMessage());
        return $ready = false;
    }
}

// A one-way fingerprint of the visitor, used only to slow down spam. The raw IP is never stored.
function scam_report_source_hash(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $pepper = (string) (env_value('APP_KEY') ?? env_value('DB_NAME', 'imali'));
    return hash('sha256', $pepper . '|scam-report|' . $ip);
}

// True when this visitor has sent too many reports recently.
function scam_report_rate_limited(): bool
{
    $now = time();
    $recent = array_filter((array) ($_SESSION['_scam_report_times'] ?? []), static fn ($t): bool => ($now - (int) $t) < 3600);
    $_SESSION['_scam_report_times'] = array_values($recent);
    if (count($recent) >= 5) return true;

    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM scam_reports WHERE source_hash = :h AND created_ts >= :since');
        $stmt->execute(['h' => scam_report_source_hash(), 'since' => $now - 3600]);
        if ((int) $stmt->fetchColumn() >= 10) return true;
    } catch (Throwable $e) {
        // If the check fails, fall back to the session limit only.
    }
    return false;
}

// Luhn check used to tell real ID and card numbers apart from other long numbers (such as account numbers).
function scam_report_luhn_ok(string $digits): bool
{
    $sum = 0;
    $alt = false;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $n = (int) $digits[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) $n -= 9;
        }
        $sum += $n;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}

// Looks for things a report should never contain: South African ID numbers and card numbers.
function scam_report_find_sensitive(string $text): ?string
{
    if (preg_match_all('/(?<!\d)\d{13}(?!\d)/', $text, $ids)) {
        foreach ($ids[0] as $candidate) {
            $month = (int) substr($candidate, 2, 2);
            $day = (int) substr($candidate, 4, 2);
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31 && scam_report_luhn_ok($candidate)) {
                return 'It looks like your text contains an ID number. Please remove it and describe what happened without personal ID details.';
            }
        }
    }
    if (preg_match_all('/(?<!\d)(?:\d[ -]?){15}\d(?!\d)/', $text, $cards)) {
        foreach ($cards[0] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate);
            if (strlen($digits) === 16 && scam_report_luhn_ok($digits)) {
                return 'It looks like your text contains a card number. Please remove it. We never need card numbers, PINs, passwords or OTPs.';
            }
        }
    }
    return null;
}

// Reads and checks the report form. Returns [values, error message or null].
function scam_report_read_input(array $post): array
{
    $types = scam_report_types();
    $channels = scam_report_channels();
    $methods = scam_report_loss_methods();

    $type = (string) ($post['scam_type'] ?? '');
    $channel = (string) ($post['channel'] ?? '');
    $method = (string) ($post['loss_method'] ?? '');
    $date = trim((string) ($post['incident_date'] ?? ''));
    $name = trim((string) ($post['suspect_name'] ?? ''));
    $contact = trim((string) ($post['suspect_contact'] ?? ''));
    $amountRaw = trim((string) ($post['amount_lost'] ?? ''));
    $description = trim((string) ($post['description'] ?? ''));
    $email = strtolower(trim((string) ($post['reporter_email'] ?? '')));
    $caseNumber = trim((string) ($post['police_case_number'] ?? ''));

    $values = [
        'scam_type' => isset($types[$type]) ? $type : '',
        'channel' => isset($channels[$channel]) ? $channel : '',
        'loss_method' => ($method !== '' && isset($methods[$method])) ? $method : null,
        'incident_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
        'suspect_name' => $name === '' ? null : mb_substr($name, 0, 140),
        'suspect_contact' => $contact === '' ? null : mb_substr($contact, 0, 255),
        'amount_lost' => $amountRaw === '' ? null : round(parse_money_input($amountRaw), 2),
        'description' => mb_substr($description, 0, 3000),
        'reported_to_police' => !empty($post['reported_to_police']) ? 1 : 0,
        'police_case_number' => $caseNumber === '' ? null : mb_substr($caseNumber, 0, 60),
        'reporter_email' => $email === '' ? null : mb_substr($email, 0, 190),
        'is_anonymous' => !empty($post['is_anonymous']) ? 1 : 0,
    ];

    $error = null;
    if ($values['scam_type'] === '') {
        $error = 'Please choose the type of scam or suspicious activity.';
    } elseif ($values['channel'] === '') {
        $error = 'Please tell us how the scammer contacted you.';
    } elseif (mb_strlen($description) < 20) {
        $error = 'Please describe what happened in a little more detail (at least 20 characters).';
    } elseif ($date !== '' && $values['incident_date'] === null) {
        $error = 'Please enter the date as a valid date.';
    } elseif ($values['incident_date'] !== null && $values['incident_date'] > date('Y-m-d')) {
        $error = 'The date cannot be in the future.';
    } elseif ($values['amount_lost'] !== null && ($values['amount_lost'] < 0 || $values['amount_lost'] > 999999999999.99)) {
        $error = 'Please enter a valid amount, or leave it blank if no money was lost.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'That email address does not look right. Leave it blank if you prefer not to share it.';
    } elseif (empty($post['confirm_accurate'])) {
        $error = 'Please confirm that your report is accurate to the best of your knowledge.';
    }

    if ($error === null) {
        foreach ([$description, $contact, $name, $caseNumber] as $field) {
            $sensitive = scam_report_find_sensitive($field);
            if ($sensitive !== null) { $error = $sensitive; break; }
        }
    }

    // Anonymous reports never keep an email address.
    if ($values['is_anonymous']) $values['reporter_email'] = null;
    if (!$values['reported_to_police']) $values['police_case_number'] = null;
    if ($values['loss_method'] === null && ($values['amount_lost'] ?? 0) <= 0) $values['amount_lost'] = null;

    return [$values, $error];
}

// A readable, hard-to-guess reference such as SR-241024-K7M2QX.
function scam_report_new_reference(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'SR-' . date('ymd') . '-' . $code;
}

// Saves a report and returns its reference.
function scam_report_save(array $v, ?int $userId): string
{
    $sql = 'INSERT INTO scam_reports (reference, user_id, scam_type, channel, incident_date, suspect_name, suspect_contact, amount_lost, loss_method,
                description, reported_to_police, police_case_number, reporter_email, is_anonymous, status, source_hash, created_ts)
            VALUES (:reference, :user_id, :scam_type, :channel, :incident_date, :suspect_name, :suspect_contact, :amount_lost, :loss_method,
                :description, :reported_to_police, :police_case_number, :reporter_email, :is_anonymous, :status, :source_hash, :created_ts)';
    $stmt = db()->prepare($sql);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $reference = scam_report_new_reference();
        try {
            $stmt->execute([
                'reference' => $reference,
                'user_id' => ($v['is_anonymous'] || $userId === null) ? null : $userId,
                'scam_type' => $v['scam_type'], 'channel' => $v['channel'], 'incident_date' => $v['incident_date'],
                'suspect_name' => $v['suspect_name'], 'suspect_contact' => $v['suspect_contact'],
                'amount_lost' => $v['amount_lost'], 'loss_method' => $v['loss_method'],
                'description' => $v['description'], 'reported_to_police' => $v['reported_to_police'],
                'police_case_number' => $v['police_case_number'], 'reporter_email' => $v['reporter_email'],
                'is_anonymous' => $v['is_anonymous'], 'status' => 'received',
                'source_hash' => scam_report_source_hash(), 'created_ts' => time(),
            ]);
            $_SESSION['_scam_report_times'][] = time();
            return $reference;
        } catch (PDOException $e) {
            // A duplicate reference (very unlikely) is retried with a new code; anything else is a real error.
            if ($attempt === 4 || stripos($e->getMessage(), 'reference') === false) throw $e;
        }
    }
    throw new RuntimeException('Could not create a report reference.');
}

// Emails the site owner about a new report when SCAM_REPORT_EMAIL (and an email service) is set up.
// Never throws: a failed email must not stop the report from being saved.
function scam_report_notify(string $reference, array $v): void
{
    try {
        $to = trim((string) env_value('SCAM_REPORT_EMAIL', ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;
        require_once __DIR__ . '/resend.php';
        if (!function_exists('resend_send_email')) return;
        $types = scam_report_types();
        $subject = 'New scam report ' . $reference . ' - ' . ($types[$v['scam_type']] ?? 'Other');
        $text = "A new scam report was submitted.\n\nReference: {$reference}\nType: " . ($types[$v['scam_type']] ?? $v['scam_type'])
            . "\nChannel: " . $v['channel'] . "\nSuspect contact: " . ($v['suspect_contact'] ?? 'not given')
            . "\nAmount lost: " . ($v['amount_lost'] !== null ? 'R ' . number_format((float) $v['amount_lost'], 2) : 'none stated')
            . "\n\nOpen the admin page to read the full report.";
        resend_send_email($to, $subject, $text);
    } catch (Throwable $e) {
        error_log('[Imali Smart] Scam report notification failed: ' . $e->getMessage());
    }
}

// Is the signed-in person a report reviewer? Set ADMIN_EMAILS=you@example.com,other@example.com in .env.
function scam_report_is_reviewer(?array $user): bool
{
    if (!$user || empty($user['email'])) return false;
    $list = array_filter(array_map(static fn ($s): string => strtolower(trim($s)), explode(',', (string) env_value('ADMIN_EMAILS', ''))));
    return in_array(strtolower((string) $user['email']), $list, true);
}
