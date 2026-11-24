<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/queries.php';
requireLogin();

$uid = (int) $_SESSION['user_id'];
[$q, $sort, $dir] = readFilters();
$rows = fetchSubmissions(getPDO(), $uid, $q, $sort, $dir);   // no LIMIT: export everything that matches

logEvent('csv export (' . count($rows) . ' rows)', $uid);
exportCSV($rows, [
    'id' => 'id', 'name' => 'name', 'email' => 'email',
    'subject' => 'subject', 'message' => 'message', 'created_at' => 'created_at',
], 'submissions_' . date('Ymd_His') . '.csv');
