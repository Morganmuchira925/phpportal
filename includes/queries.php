<?php
/**
 * includes/queries.php
 * Shared query helpers for the Submissions Directory and the CSV export.
 */

/** Whitelisted sort columns (never put user input straight into ORDER BY). */
const SORT_COLUMNS = ['created_at', 'subject', 'name', 'email'];

/** Read and validate GET search/sort parameters. */
function readFilters(): array
{
    $q    = sanitizeInput((string) ($_GET['q'] ?? ''));
    $sort = in_array($_GET['sort'] ?? '', SORT_COLUMNS, true) ? $_GET['sort'] : 'created_at';
    $dir  = (($_GET['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';
    return [mb_substr($q, 0, 80), $sort, $dir];
}

/**
 * Fetch rows for one user using LIKE search, ORDER BY, LIMIT/OFFSET.
 * Pass $limit = null to fetch everything (CSV export).
 */
function fetchSubmissions(PDO $pdo, int $uid, string $q, string $sort, string $dir,
                          ?int $limit = null, int $offset = 0): array
{
    $sql = 'SELECT id, name, email, subject, message, created_at
              FROM contact_submissions
             WHERE user_id = :uid
               AND (name LIKE :q1 OR subject LIKE :q2 OR message LIKE :q3)
             ORDER BY ' . $sort . ' ' . $dir . ', id DESC';
    if ($limit !== null) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }
    $stmt = $pdo->prepare($sql);
    $like = '%' . $q . '%';
    $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindValue(':q1', $like);
    $stmt->bindValue(':q2', $like);
    $stmt->bindValue(':q3', $like);
    if ($limit !== null) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function countSubmissions(PDO $pdo, int $uid, string $q): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM contact_submissions
          WHERE user_id = :uid AND (name LIKE :q1 OR subject LIKE :q2 OR message LIKE :q3)'
    );
    $like = '%' . $q . '%';
    $stmt->execute([':uid' => $uid, ':q1' => $like, ':q2' => $like, ':q3' => $like]);
    return (int) $stmt->fetchColumn();
}
