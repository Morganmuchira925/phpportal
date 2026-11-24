<?php
/**
 * Automated Test Suite for Internet Programming Portal
 * Tests all core features, security, CRUD, sessions, cookies, database, and CSV functionality.
 */

$baseUrl = 'http://localhost/IP2/FP/ip_project';
$cookieJar = tempnam(sys_get_temp_dir(), 'ip_test_cookie_');
$cookieJarUser2 = tempnam(sys_get_temp_dir(), 'ip_test_cookie2_');

$passedTests = 0;
$failedTests = 0;

function report(bool $passed, string $name, string $details = ''): void {
    global $passedTests, $failedTests;
    if ($passed) {
        $passedTests++;
        echo "  [PASS] $name" . ($details ? " ($details)" : "") . "\n";
    } else {
        $failedTests++;
        echo "  [FAIL] $name: $details\n";
    }
}

function httpRequest(string $url, string $method = 'GET', ?array $postData = null, ?string $cookieFile = null, array $headers = [], bool $followRedirects = false): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($postData !== null) {
        if (isset($postData['__is_multipart'])) {
            unset($postData['__is_multipart']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        }
    }

    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headerStr = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'status' => $info['http_code'],
        'headers' => $headerStr,
        'body' => $body,
        'url' => $info['url'],
        'error' => $error,
    ];
}

function extractCsrfToken(string $html): ?string {
    if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

echo "========================================================\n";
echo "   Internet Programming Portal - Automated Test Suite   \n";
echo "========================================================\n\n";

// ----------------------------------------------------
// TEST GROUP 1: Database and Config Verification
// ----------------------------------------------------
echo "1. Database & Config Verification:\n";
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getPDO();
    report(true, "Database connection established via PDO");

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $expectedTables = ['users', 'user_profiles', 'contact_submissions', 'activity_log'];
    $diff = array_diff($expectedTables, $tables);
    report(empty($diff), "All required schema tables exist", "Tables found: " . implode(', ', $tables));

    $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    report($userCount >= 3, "Seed users exist in database", "Count: $userCount");
} catch (Throwable $e) {
    report(false, "Database connection", $e->getMessage());
}

// ----------------------------------------------------
// TEST GROUP 2: Public Pages & Static Assets
// ----------------------------------------------------
echo "\n2. Public Endpoints & Static Files:\n";
$resIndex = httpRequest("$baseUrl/index.php");
report($resIndex['status'] === 200 && str_contains($resIndex['body'], 'Welcome to the Internet Programming Portal'), "GET /index.php (Home page)");

$resLogin = httpRequest("$baseUrl/login.php");
$loginCsrf = extractCsrfToken($resLogin['body']);
report($resLogin['status'] === 200 && !empty($loginCsrf), "GET /login.php with CSRF token");

$resReg = httpRequest("$baseUrl/register.php");
$regCsrf = extractCsrfToken($resReg['body']);
report($resReg['status'] === 200 && !empty($regCsrf), "GET /register.php with CSRF token");

$resRobots = httpRequest("$baseUrl/robots.txt");
report($resRobots['status'] === 200 && str_contains($resRobots['body'], 'Disallow: /pages/'), "GET /robots.txt");

$resSitemap = httpRequest("$baseUrl/sitemap.xml");
report($resSitemap['status'] === 200 && str_contains($resSitemap['body'], '<urlset'), "GET /sitemap.xml");

$resCss = httpRequest("$baseUrl/assets/css/style.css");
report($resCss['status'] === 200 && str_contains($resCss['body'], '--primary'), "GET /assets/css/style.css");

// ----------------------------------------------------
// TEST GROUP 3: Folder Access Protection (.htaccess)
// ----------------------------------------------------
echo "\n3. Security - Direct Directory Access (.htaccess):\n";
$folders = ['config', 'includes', 'logs', 'sql'];
foreach ($folders as $folder) {
    $res = httpRequest("$baseUrl/$folder/");
    report($res['status'] === 403, "Direct access to /$folder/ is forbidden (403)", "Status: {$res['status']}");
}

// ----------------------------------------------------
// TEST GROUP 4: Access Control for Protected Pages (Guests)
// ----------------------------------------------------
echo "\n4. Access Control - Guest Redirection:\n";
$protectedPages = [
    '/pages/dashboard.php',
    '/pages/profile.php',
    '/pages/contact.php',
    '/pages/submissions.php',
    '/pages/hub.php',
    '/pages/export_csv.php',
    '/pages/submission_edit.php?id=1',
];

foreach ($protectedPages as $page) {
    $res = httpRequest("$baseUrl$page");
    $isRedirect = ($res['status'] === 302 && str_contains($res['headers'], 'Location: /IP2/FP/ip_project/login.php'));
    report($isRedirect, "Unauthenticated access to $page redirects to login", "Status: {$res['status']}");
}

// ----------------------------------------------------
// TEST GROUP 5: Registration Flow
// ----------------------------------------------------
echo "\n5. User Registration Flow:\n";
// Clean up any previous test user
$pdo->exec("DELETE FROM users WHERE username = 'testdavid'");

// 5a. Password mismatch
$resRegForMismatch = httpRequest("$baseUrl/register.php", 'GET', null, $cookieJar);
$regCsrfMismatch = extractCsrfToken($resRegForMismatch['body']);
$resMismatch = httpRequest("$baseUrl/register.php", 'POST', [
    'csrf_token' => $regCsrfMismatch,
    'username'   => 'testdavid',
    'email'      => 'david@example.com',
    'password'   => 'Password123!',
    'confirm'    => 'DifferentPass!',
], $cookieJar);
report(str_contains($resMismatch['body'], 'does not match'), "Reject registration with mismatched passwords");

// 5b. Valid registration
$resRegPage = httpRequest("$baseUrl/register.php", 'GET', null, $cookieJar);
$regToken = extractCsrfToken($resRegPage['body']);
$resValidReg = httpRequest("$baseUrl/register.php", 'POST', [
    'csrf_token' => $regToken,
    'username'   => 'testdavid',
    'email'      => 'david@example.com',
    'password'   => 'Password123!',
    'confirm'    => 'Password123!',
], $cookieJar);
$regSuccess = ($resValidReg['status'] === 302 && str_contains($resValidReg['headers'], 'Location: /IP2/FP/ip_project/login.php'));
report($regSuccess, "Successful registration redirects to login page");

// Verify created in DB
$davidUser = $pdo->query("SELECT id, username, email FROM users WHERE username = 'testdavid'")->fetch();
report(!empty($davidUser), "New user record created in DB with username 'testdavid'");
$davidProfile = $pdo->query("SELECT user_id FROM user_profiles WHERE user_id = " . (int)$davidUser['id'])->fetch();
report(!empty($davidProfile), "Corresponding user_profiles record initialized");

// 5c. Duplicate registration
$resRegPage2 = httpRequest("$baseUrl/register.php", 'GET', null, $cookieJar);
$regToken2 = extractCsrfToken($resRegPage2['body']);
$resDupReg = httpRequest("$baseUrl/register.php", 'POST', [
    'csrf_token' => $regToken2,
    'username'   => 'testdavid',
    'email'      => 'david@example.com',
    'password'   => 'Password123!',
    'confirm'    => 'Password123!',
], $cookieJar);
report(str_contains($resDupReg['body'], 'already registered'), "Duplicate username/email rejected with error");

// ----------------------------------------------------
// TEST GROUP 6: Authentication (Login / Logout / Remember Me)
// ----------------------------------------------------
echo "\n6. Authentication Flow:\n";
// 6a. Invalid credentials
$resLoginPage = httpRequest("$baseUrl/login.php", 'GET', null, $cookieJar);
$logToken = extractCsrfToken($resLoginPage['body']);
$resBadLogin = httpRequest("$baseUrl/login.php", 'POST', [
    'csrf_token' => $logToken,
    'identifier' => 'alice',
    'password'   => 'WrongPassword!',
], $cookieJar);
report(str_contains($resBadLogin['body'], 'Invalid username/email or password'), "Login fails with incorrect password");

// 6b. Successful login with Alice and Remember Me
$resLoginPage2 = httpRequest("$baseUrl/login.php", 'GET', null, $cookieJar);
$logToken2 = extractCsrfToken($resLoginPage2['body']);
$resGoodLogin = httpRequest("$baseUrl/login.php", 'POST', [
    'csrf_token' => $logToken2,
    'identifier' => 'alice',
    'password'   => 'Password123!',
    'remember'   => '1',
], $cookieJar);
$loginRedirect = ($resGoodLogin['status'] === 302 && str_contains($resGoodLogin['headers'], 'dashboard.php'));
report($loginRedirect, "Successful login redirects to dashboard");
report(str_contains($resGoodLogin['headers'], 'last_username=alice'), "Remember Me cookie set on login");

// Verify last_login updated
$alice = $pdo->query("SELECT last_login FROM users WHERE username = 'alice'")->fetch();
report(!empty($alice['last_login']), "User last_login updated in database", "Timestamp: {$alice['last_login']}");

// ----------------------------------------------------
// TEST GROUP 7: Protected Pages - Dashboard & Profile
// ----------------------------------------------------
echo "\n7. Dashboard & Profile Features:\n";
// 7a. Dashboard
$resDash = httpRequest("$baseUrl/pages/dashboard.php", 'GET', null, $cookieJar);
report($resDash['status'] === 200 && str_contains($resDash['body'], 'alice!'), "Dashboard greets authenticated user alice");
report(str_contains($resDash['body'], 'Registered users'), "Dashboard displays user statistics");

// 7b. Profile View
$resProf = httpRequest("$baseUrl/pages/profile.php", 'GET', null, $cookieJar);
report($resProf['status'] === 200 && str_contains($resProf['body'], 'Alice Wanjiru'), "Profile displays full name");

// 7c. Profile Update
$profCsrf = extractCsrfToken($resProf['body']);
$updatedBio = 'Updated bio via automated test at ' . date('H:i:s');
$resProfPost = httpRequest("$baseUrl/pages/profile.php", 'POST', [
    'csrf_token' => $profCsrf,
    'email'      => 'alice@example.com',
    'full_name'  => 'Alice Wanjiru M.',
    'phone'      => '0711223344',
    'bio'        => $updatedBio,
], $cookieJar);
report($resProfPost['status'] === 302, "Profile update redirects after save");

$aliceProfile = $pdo->query("SELECT full_name, phone, bio FROM user_profiles WHERE user_id = 1")->fetch();
report($aliceProfile['bio'] === $updatedBio && $aliceProfile['phone'] === '0711223344', "Profile changes persisted in database");

// ----------------------------------------------------
// TEST GROUP 8: Contact Form & Submissions CRUD
// ----------------------------------------------------
echo "\n8. Contact Form & Submissions CRUD:\n";
// 8a. Contact Form Prefill & Create Submission
$resContact = httpRequest("$baseUrl/pages/contact.php", 'GET', null, $cookieJar);
report($resContact['status'] === 200 && str_contains($resContact['body'], 'alice@example.com'), "Contact form pre-fills user email");

$contactCsrf = extractCsrfToken($resContact['body']);
$testMsg = 'Automated test message created at ' . date('Y-m-d H:i:s');
$resContactPost = httpRequest("$baseUrl/pages/contact.php", 'POST', [
    'csrf_token' => $contactCsrf,
    'name'       => 'Alice Wanjiru M.',
    'email'      => 'alice@example.com',
    'subject'    => 'Feedback',
    'message'    => $testMsg,
], $cookieJar);
report($resContactPost['status'] === 302 && str_contains($resContactPost['headers'], 'submissions.php'), "Contact submission redirects to submissions directory");

// Verify in DB
$sub = $pdo->query("SELECT id, message FROM contact_submissions WHERE user_id = 1 AND message = " . $pdo->quote($testMsg))->fetch();
report(!empty($sub), "Submission record saved in database", "ID: " . ($sub['id'] ?? 'none'));
$newSubId = (int) ($sub['id'] ?? 0);

// 8b. Read & Search in Submissions Directory
$resSubList = httpRequest("$baseUrl/pages/submissions.php?q=" . urlencode($testMsg), 'GET', null, $cookieJar);
report($resSubList['status'] === 200 && str_contains($resSubList['body'], htmlspecialchars($testMsg)), "Search finds the newly created submission");

$resSubEmpty = httpRequest("$baseUrl/pages/submissions.php?q=completelynonexistentquerytext999", 'GET', null, $cookieJar);
report(str_contains($resSubEmpty['body'], 'No submissions found'), "Empty search returns 'No submissions found'");

// 8c. Edit Submission
$resEditPage = httpRequest("$baseUrl/pages/submission_edit.php?id=$newSubId", 'GET', null, $cookieJar);
report($resEditPage['status'] === 200 && str_contains($resEditPage['body'], htmlspecialchars($testMsg)), "Edit page displays submission details");

$editCsrf = extractCsrfToken($resEditPage['body']);
$updatedMsg = 'Edited submission message at ' . date('Y-m-d H:i:s');
$resEditPost = httpRequest("$baseUrl/pages/submission_edit.php", 'POST', [
    'csrf_token' => $editCsrf,
    'id'         => $newSubId,
    'name'       => 'Alice Wanjiru M.',
    'email'      => 'alice@example.com',
    'subject'    => 'Technical Support',
    'message'    => $updatedMsg,
], $cookieJar);
report($resEditPost['status'] === 302, "Editing submission redirects to submissions list");

$subEdited = $pdo->query("SELECT message, subject FROM contact_submissions WHERE id = $newSubId")->fetch();
report($subEdited['message'] === $updatedMsg && $subEdited['subject'] === 'Technical Support', "Submission edit persisted in database");

// ----------------------------------------------------
// TEST GROUP 9: CSV Export & CSV Import
// ----------------------------------------------------
echo "\n9. CSV Export & Import:\n";
// 9a. Export
$resExport = httpRequest("$baseUrl/pages/export_csv.php", 'GET', null, $cookieJar);
report(str_contains($resExport['headers'], 'Content-Type: text/csv') && str_contains($resExport['body'], 'id,name,email,subject,message,created_at'), "CSV export streams valid CSV with correct headers");
report(str_contains($resExport['body'], 'Technical Support'), "Exported CSV contains Alice's submissions");

// 9b. Import
$csvContent = "name,email,subject,message\n" .
              "Test Importer,importer@example.com,Bug Report,\"First imported row through automated test\"\n" .
              "Test Importer 2,importer2@example.com,Partnership,\"Second imported row through automated test\"\n";
$tempCsv = tempnam(sys_get_temp_dir(), 'test_import_') . '.csv';
file_put_contents($tempCsv, $csvContent);

$resSubPage = httpRequest("$baseUrl/pages/submissions.php", 'GET', null, $cookieJar);
$importCsrf = extractCsrfToken($resSubPage['body']);

$cfile = new CURLFile($tempCsv, 'text/csv', 'import.csv');
$resImport = httpRequest("$baseUrl/pages/submissions.php", 'POST', [
    '__is_multipart' => true,
    'csrf_token'     => $importCsrf,
    'action'         => 'import',
    'csv_file'       => $cfile,
], $cookieJar);
unlink($tempCsv);

report($resImport['status'] === 302, "CSV import processed and redirected");
$importedCount = (int) $pdo->query("SELECT COUNT(*) FROM contact_submissions WHERE message LIKE '%imported row through automated test%'")->fetchColumn();
report($importedCount === 2, "Both CSV rows successfully imported into database", "Count: $importedCount");

// ----------------------------------------------------
// TEST GROUP 10: Delete Submission
// ----------------------------------------------------
echo "\n10. Delete Submission:\n";
$resSubForDel = httpRequest("$baseUrl/pages/submissions.php", 'GET', null, $cookieJar);
$delCsrf = extractCsrfToken($resSubForDel['body']);

$resDel = httpRequest("$baseUrl/pages/submissions.php", 'POST', [
    'csrf_token' => $delCsrf,
    'action'     => 'delete',
    'id'         => $newSubId,
], $cookieJar);
report($resDel['status'] === 302, "Delete action redirects to submissions directory");

$deletedCheck = $pdo->query("SELECT id FROM contact_submissions WHERE id = $newSubId")->fetch();
report(empty($deletedCheck), "Submission #$newSubId removed from database");

// Clean up imported test rows
$pdo->exec("DELETE FROM contact_submissions WHERE message LIKE '%imported row through automated test%'");

// ----------------------------------------------------
// TEST GROUP 11: Cross-User Security / Authorization
// ----------------------------------------------------
echo "\n11. Authorization & Cross-User Security:\n";
// Login as Brian in cookieJarUser2
$resBrianLogin = httpRequest("$baseUrl/login.php", 'GET', null, $cookieJarUser2);
$brianCsrf = extractCsrfToken($resBrianLogin['body']);
httpRequest("$baseUrl/login.php", 'POST', [
    'csrf_token' => $brianCsrf,
    'identifier' => 'brian',
    'password'   => 'Password123!',
], $cookieJarUser2);

// Alice has submission with ID 1. Brian tries to edit Alice's submission #1
$resUnauthorizedEdit = httpRequest("$baseUrl/pages/submission_edit.php?id=1", 'GET', null, $cookieJarUser2);
report($resUnauthorizedEdit['status'] === 302 && str_contains($resUnauthorizedEdit['headers'], 'submissions.php'), "Brian cannot access Alice's submission edit page (redirected)");

// Brian tries to POST delete Alice's submission #1
$resBrianSubPage = httpRequest("$baseUrl/pages/submissions.php", 'GET', null, $cookieJarUser2);
$brianDelCsrf = extractCsrfToken($resBrianSubPage['body']);
httpRequest("$baseUrl/pages/submissions.php", 'POST', [
    'csrf_token' => $brianDelCsrf,
    'action'     => 'delete',
    'id'         => 1,
], $cookieJarUser2);

$aliceSubStillExists = $pdo->query("SELECT id FROM contact_submissions WHERE id = 1")->fetch();
report(!empty($aliceSubStillExists), "Alice's submission #1 remains intact (tampered delete rejected)");

// ----------------------------------------------------
// TEST GROUP 12: Information Hub & Theme Cookie
// ----------------------------------------------------
echo "\n12. Information Hub (String Tools, Reports, Theme Preference):\n";
$sampleText = "The quick brown fox jumps over the lazy dog";
$resHub = httpRequest("$baseUrl/pages/hub.php?text=" . urlencode($sampleText), 'GET', null, $cookieJar);
report($resHub['status'] === 200, "Information Hub page loads (200 OK)");
report(str_contains($resHub['body'], 'Length (strlen)') && str_contains($resHub['body'], '43'), "String tools calculates length 43");
report(str_contains($resHub['body'], 'Word count (count)') && str_contains($resHub['body'], '9'), "String tools calculates 9 words");
report(str_contains($resHub['body'], 'THE QUICK BROWN FOX'), "String tools calculates uppercase");

// Hub reports: Aggregation and joins
report(str_contains($resHub['body'], 'Submissions per user') || str_contains($resHub['body'], 'Submissions by subject'), "Hub reports rendered with aggregate data");

// Theme switch to dark
$hubCsrf = extractCsrfToken($resHub['body']);
$resTheme = httpRequest("$baseUrl/pages/hub.php", 'POST', [
    'csrf_token' => $hubCsrf,
    'action'     => 'theme',
    'theme'      => 'dark',
], $cookieJar);
report($resTheme['status'] === 302, "Theme change form redirects");
report(str_contains($resTheme['headers'], 'theme=dark'), "Theme cookie set to 'dark'");

// Verify theme is applied to HTML data-theme attribute
$resHubDark = httpRequest("$baseUrl/pages/hub.php", 'GET', null, $cookieJar);
report(str_contains($resHubDark['body'], 'data-theme="dark"'), "HTML root element reflects data-theme=\"dark\"");

// ----------------------------------------------------
// TEST GROUP 13: Activity Logging & File Handling
// ----------------------------------------------------
echo "\n13. Activity Logging (DB & Log File):\n";
$logFileContent = @file_get_contents(LOG_FILE);
report(!empty($logFileContent) && str_contains($logFileContent, 'login'), "Activity written to logs/activity.log file");

$dbLogs = (int) $pdo->query("SELECT COUNT(*) FROM activity_log WHERE user_id = 1")->fetchColumn();
report($dbLogs > 0, "Activity log records recorded in database table", "Alice events: $dbLogs");

// ----------------------------------------------------
// TEST GROUP 14: Logout Flow
// ----------------------------------------------------
echo "\n14. Logout Flow:\n";
$resLogout = httpRequest("$baseUrl/logout.php", 'GET', null, $cookieJar);
report($resLogout['status'] === 302 && str_contains($resLogout['headers'], 'login.php'), "Logout redirects to login page");

$resPostLogout = httpRequest("$baseUrl/pages/dashboard.php", 'GET', null, $cookieJar);
report($resPostLogout['status'] === 302 && str_contains($resPostLogout['headers'], 'login.php'), "Accessing dashboard after logout redirects to login");

// Clean up temporary cookies
@unlink($cookieJar);
@unlink($cookieJarUser2);

echo "\n========================================================\n";
echo "SUMMARY: Passed: $passedTests | Failed: $failedTests\n";
echo "========================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
