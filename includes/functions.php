<?php
/**
 * includes/functions.php
 * Reusable user-defined functions (validation, security, helpers).
 */

/** Escape output for HTML (XSS protection). */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Trim and strip control characters from input. */
function sanitizeInput(string $value): string
{
    $value = trim($value);
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
}

/** Validate an email address with filter_var(). */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Redirect and stop execution. $path is relative to BASE_URL (e.g. "/login.php"). */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/** Build a URL relative to the application root. */
function url(string $path = ''): string
{
    return BASE_URL . $path;
}

/** Client IP helper using $_SERVER. */
function clientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/* ------------------------------ Flash messages ------------------------------ */

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/* ------------------------------ Auth helpers ------------------------------ */

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/** Guard for protected pages. */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to access that page.');
        redirect('/login.php');
    }
}

/* ------------------------------ CSRF protection ------------------------------ */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfVerify(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ------------------------------ Form parser ------------------------------ */

/**
 * Reusable form parser: sanitises and validates a whole form array against rules.
 *
 * Supported rules per field: required, min, max, email, in (array), match (other field),
 * regex (pattern), raw (do not trim, e.g. passwords), label (friendly name).
 *
 * @return array [cleanedData, errors]
 */
function parseForm(array $input, array $rules): array
{
    $clean  = [];
    $errors = [];

    foreach ($rules as $field => $rule) {
        $label = $rule['label'] ?? ucfirst($field);
        if (!empty($rule['raw'])) {
            $value = isset($input[$field]) && is_string($input[$field]) ? $input[$field] : '';
        } else {
            $value = isset($input[$field]) && is_string($input[$field])
                ? sanitizeInput($input[$field]) : '';
        }
        $clean[$field] = $value;

        if (!empty($rule['required']) && $value === '') {
            $errors[$field] = "$label is required.";
            continue;
        }
        if ($value === '') {
            continue; // optional and empty: skip other checks
        }
        if (isset($rule['min']) && mb_strlen($value) < $rule['min']) {
            $errors[$field] = "$label must be at least {$rule['min']} characters.";
        } elseif (isset($rule['max']) && mb_strlen($value) > $rule['max']) {
            $errors[$field] = "$label must not exceed {$rule['max']} characters.";
        } elseif (!empty($rule['email']) && !validateEmail($value)) {
            $errors[$field] = "$label must be a valid email address.";
        } elseif (isset($rule['in']) && !in_array($value, $rule['in'], true)) {
            $errors[$field] = "$label has an invalid value.";
        } elseif (isset($rule['regex']) && !preg_match($rule['regex'], $value)) {
            $errors[$field] = $rule['regex_message'] ?? "$label has an invalid format.";
        } elseif (isset($rule['match']) && $value !== ($input[$rule['match']] ?? null)) {
            $errors[$field] = "$label does not match.";
        }
    }
    return [$clean, $errors];
}

/* ------------------------------ Logging (file handling) ------------------------------ */

/** Write an event to logs/activity.log and the activity_log table. */
function logEvent(string $action, ?int $userId = null): void
{
    $line = sprintf("[%s] %s | user=%s | ip=%s\n", date('Y-m-d H:i:s'), $action,
        $userId ?? 'guest', clientIp());
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);

    try {
        $stmt = getPDO()->prepare(
            'INSERT INTO activity_log (user_id, action, ip_address) VALUES (:uid, :action, :ip)'
        );
        $stmt->execute([':uid' => $userId, ':action' => $action, ':ip' => clientIp()]);
    } catch (Throwable $e) {
        error_log('logEvent DB failure: ' . $e->getMessage());
    }
}

/* ------------------------------ Output helpers ------------------------------ */

/** Render a simple HTML table from rows (all values escaped). */
function generateTable(array $rows, array $headers): string
{
    if (!$rows) {
        return '<p class="muted">No records found.</p>';
    }
    $html = '<div class="table-wrap"><table><thead><tr>';
    foreach ($headers as $h) {
        $html .= '<th>' . e($h) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach (array_keys($headers) as $key) {
            $html .= '<td>' . e((string) ($row[$key] ?? '')) . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/** Stream rows as a CSV download using PHP file handling (php://output). */
function exportCSV(array $rows, array $headers, string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_values($headers));
    foreach ($rows as $row) {
        $line = [];
        foreach (array_keys($headers) as $key) {
            $value = (string) ($row[$key] ?? '');
            // Neutralise spreadsheet formula injection.
            if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
                $value = "'" . $value;
            }
            $line[] = $value;
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

/** Extract just the labels from the multidimensional subject array. */
function subjectLabels(array $categories): array
{
    return array_column($categories, 'label');
}
