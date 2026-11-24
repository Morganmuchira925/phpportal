<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/queries.php';
requireLogin();

$pdo    = getPDO();
$uid    = (int) $_SESSION['user_id'];
$labels = subjectLabels($SUBJECT_CATEGORIES);

/* ---------- POST actions: delete one record, import CSV ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid or expired form token.');
        redirect('/pages/submissions.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            // Conditional DELETE: only the owner's record.
            $stmt = $pdo->prepare('DELETE FROM contact_submissions WHERE id = :id AND user_id = :uid');
            $stmt->execute([':id' => $id, ':uid' => $uid]);
            if ($stmt->rowCount() === 1) {
                logEvent('submission #' . $id . ' deleted', $uid);
                setFlash('success', 'Submission deleted.');
            } else {
                setFlash('error', 'Record not found.');
            }
        }
        redirect('/pages/submissions.php');
    }

    if ($action === 'import') {
        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            setFlash('error', 'Please choose a CSV file to upload.');
        } elseif ($file['size'] > 1024 * 1024 || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
            setFlash('error', 'Only .csv files up to 1 MB are allowed.');
        } else {
            $fh = fopen($file['tmp_name'], 'r');
            $header = fgetcsv($fh);
            $map = $header ? array_flip(array_map(fn($h) => strtolower(trim((string) $h)), $header)) : [];
            if (!isset($map['name'], $map['email'], $map['subject'], $map['message'])) {
                setFlash('error', 'CSV needs a header row with: name, email, subject, message.');
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO contact_submissions (user_id, name, email, subject, message)
                     VALUES (:uid, :n, :e, :s, :m)'
                );
                $ok = $skipped = 0;
                while (($row = fgetcsv($fh)) !== false && ($ok + $skipped) < 200) {
                    $input = ['name' => $row[$map['name']] ?? '', 'email' => $row[$map['email']] ?? '',
                              'subject' => $row[$map['subject']] ?? '', 'message' => $row[$map['message']] ?? ''];
                    [$d, $errs] = parseForm($input, [
                        'name'    => ['required' => true, 'max' => 80],
                        'email'   => ['required' => true, 'email' => true, 'max' => 120],
                        'subject' => ['required' => true, 'in' => $labels],
                        'message' => ['required' => true, 'min' => 10, 'max' => 2000],
                    ]);
                    if ($errs) { $skipped++; continue; }
                    $ins->execute([':uid' => $uid, ':n' => $d['name'], ':e' => strtolower($d['email']),
                                   ':s' => $d['subject'], ':m' => $d['message']]);
                    $ok++;
                }
                logEvent("csv import: $ok rows", $uid);
                setFlash('success', "Imported $ok row(s); skipped $skipped invalid row(s).");
            }
            fclose($fh);
        }
        redirect('/pages/submissions.php');
    }
}

/* ---------- GET: search, sort, paginate ---------- */
[$q, $sort, $dir] = readFilters();
$total  = countSubmissions($pdo, $uid, $q);
$pages  = max(1, (int) ceil($total / PER_PAGE));
$page   = min(max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1), $pages);
$offset = ($page - 1) * PER_PAGE;
$rows   = fetchSubmissions($pdo, $uid, $q, $sort, $dir, PER_PAGE, $offset);

$baseQuery = ['q' => $q, 'sort' => $sort, 'dir' => strtolower($dir)];
$pageUrl   = fn(int $p) => '?' . http_build_query($baseQuery + ['page' => $p]);
$sortUrl   = fn(string $col) => '?' . http_build_query([
    'q' => $q, 'sort' => $col, 'dir' => ($sort === $col && $dir === 'ASC') ? 'desc' : 'asc',
]);

$pageTitle = 'Submissions Directory';
$pageDescription = 'Search, edit, delete, import and export your Contact Us submissions.';
include __DIR__ . '/../includes/header.php';
?>
<h1>Submissions directory</h1>

<form method="get" action="" class="toolbar" role="search">
    <label for="q" class="sr-only">Search</label>
    <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Search name, subject or message">
    <input type="hidden" name="sort" value="<?= e($sort) ?>">
    <input type="hidden" name="dir" value="<?= e(strtolower($dir)) ?>">
    <button type="submit" class="btn">Search</button>
    <?php if ($q !== ''): ?><a href="?" class="btn btn-secondary">Clear</a><?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(url('/pages/export_csv.php') . '?' . http_build_query($baseQuery)) ?>">Export CSV</a>
</form>

<p class="muted"><?= $total ?> record(s) &middot; page <?= $page ?> of <?= $pages ?></p>

<?php if ($rows): ?>
<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>#</th>
            <th><a href="<?= e($sortUrl('name')) ?>">Name</a></th>
            <th><a href="<?= e($sortUrl('email')) ?>">Email</a></th>
            <th><a href="<?= e($sortUrl('subject')) ?>">Subject</a></th>
            <th>Message</th>
            <th><a href="<?= e($sortUrl('created_at')) ?>">Date</a></th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
        <tr>
            <td><?= $offset + $i + 1 ?></td>
            <td><?= e($r['name']) ?></td>
            <td><?= e($r['email']) ?></td>
            <td><?= e($r['subject']) ?></td>
            <td><?= e(mb_strimwidth($r['message'], 0, 60, '...')) ?></td>
            <td><?= e($r['created_at']) ?></td>
            <td class="actions">
                <a href="<?= e(url('/pages/submission_edit.php') . '?id=' . (int) $r['id']) ?>">Edit</a>
                <form method="post" action="" onsubmit="return confirm('Delete this submission?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="btn-danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?><a href="<?= e($pageUrl($page - 1)) ?>">&laquo; Prev</a><?php endif; ?>
    <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="<?= e($pageUrl($p)) ?>" class="<?= $p === $page ? 'current' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="<?= e($pageUrl($page + 1)) ?>">Next &raquo;</a><?php endif; ?>
</nav>
<?php else: ?>
    <p class="muted">No submissions found. <a href="<?= e(url('/pages/contact.php')) ?>">Send your first message</a>.</p>
<?php endif; ?>

<form method="post" action="" enctype="multipart/form-data" class="card form">
    <h2>Import from CSV</h2>
    <p class="muted">Header row required: <code>name,email,subject,message</code> (an exported file works too).</p>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="import">
    <label for="csv_file">CSV file (max 1 MB)</label>
    <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
    <button type="submit" class="btn">Import</button>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
