<?php
/**
 * IGNOU Landing Page — plain-PHP entry point for Vercel (vercel-php runtime).
 *
 * Replaces the original Laravel routes + WebHomeController with no framework
 * and no database. The lead form still posts to the LeadSquared API exactly
 * like the original app did.
 *
 * Routes:
 *   GET  /                     -> landing page (templates/index.php)
 *   POST /students-lead-save   -> validate + send lead to LeadSquared, then redirect
 *   GET  /thank-you            -> thank-you page (templates/thankyou.php)
 */

// ----- locate the templates folder (robust to how the runtime lays out files) -----
$candidates = [
    __DIR__ . '/../templates',
    getcwd() . '/templates',
    dirname(__DIR__) . '/templates',
];
$TEMPLATES = null;
foreach ($candidates as $c) {
    if (is_dir($c)) { $TEMPLATES = rtrim($c, '/'); break; }
}
if ($TEMPLATES === null) { $TEMPLATES = __DIR__ . '/../templates'; }

// ----- work out the request path -----
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path   = '/' . trim($path, '/');            // normalise: "/", "/thank-you", ...
if ($path === '/') { $path = '/'; }
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/** Render a template file. $errorMessage is made available to the template. */
function render(string $file, string $errorMessage = ''): void {
    if (!is_file($file)) {
        http_response_code(500);
        echo 'Template not found: ' . htmlspecialchars(basename($file));
        return;
    }
    include $file;
}

// =====================================================================
//  POST /students-lead-save  — validate then push the lead to LeadSquared
// =====================================================================
if ($method === 'POST' && $path === '/students-lead-save') {

    $name         = trim($_POST['name'] ?? '');
    $contactno    = trim($_POST['contactno'] ?? '');
    $selectcourse = trim($_POST['selectcourse'] ?? '');
    $city         = trim($_POST['city'] ?? '');
    $email        = trim($_POST['email'] ?? '');

    // ----- validation (mirrors the original Laravel rules) -----
    $errors = [];
    if ($name === '')                                        { $errors[] = 'Name is required.'; }
    if (!preg_match('/^\d{10}$/', $contactno))               { $errors[] = 'A valid 10-digit phone number is required.'; }
    if ($selectcourse === '')                                { $errors[] = 'Please select a course.'; }
    if ($city === '')                                        { $errors[] = 'Please select a state.'; }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Please enter a valid email address.'; }

    if ($errors) {
        // send the user back to the form with the first error
        header('Location: /?error=' . rawurlencode($errors[0]));
        exit;
    }

    // ----- build the LeadSquared payload (same attributes as the original) -----
    $data = [
        ["Attribute" => "FirstName",   "Value" => $name],
        ["Attribute" => "Phone",       "Value" => $contactno],
        ["Attribute" => "mx_State",    "Value" => $city],
        ["Attribute" => "mx_Course",   "Value" => $selectcourse],
        ["Attribute" => "Source",      "Value" => 'IGL'],
        ["Attribute" => "mx_University","Value" => 'No University'],
        ["Attribute" => "mx_Medium",   "Value" => $_POST['medium']    ?? ''],
        ["Attribute" => "mx_Campaign", "Value" => $_POST['campaign']  ?? ''],
        ["Attribute" => "mx_AdGroup",  "Value" => $_POST['adgroup']   ?? ''],
        ["Attribute" => "mx_Keyword",  "Value" => $_POST['keyword']   ?? ''],
        ["Attribute" => "mx_MatchType","Value" => $_POST['matchtype'] ?? ''],
    ];
    if ($email !== '') {
        $data[] = ["Attribute" => "EmailAddress", "Value" => $email];
    }

    // ----- API keys come from environment variables (set them in Vercel) -----
    $accessKey = getenv('LSQ_ACCESS_KEY') ?: '';
    $secretKey = getenv('LSQ_SECRET_KEY') ?: '';
    $host      = getenv('LSQ_HOST') ?: 'api-in21.leadsquared.com';

    if ($accessKey === '' || $secretKey === '') {
        header('Location: /?error=' . rawurlencode('Server not configured. Please try again later.'));
        exit;
    }

    $url = "https://{$host}/v2/LeadManagement.svc/Lead.Capture?accessKey={$accessKey}&secretKey={$secretKey}";

    // ----- send it -----
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        header('Location: /?error=' . rawurlencode('Server Error! Try again.'));
        exit;
    }

    $result = json_decode($response, true);
    $ok = !empty($result) && (
        (isset($result['Status'])  && $result['Status']  === 'Success') ||
        (isset($result['Message']) && $result['Message'] === 'Success')
    );

    if ($ok) {
        header('Location: /thank-you');
        exit;
    }

    header('Location: /?error=' . rawurlencode('Lead not submitted. Please try again!'));
    exit;
}

// =====================================================================
//  GET /thank-you
// =====================================================================
if ($method === 'GET' && $path === '/thank-you') {
    render($TEMPLATES . '/thankyou.php');
    exit;
}

// =====================================================================
//  GET /  (home / landing page)
// =====================================================================
if ($method === 'GET' && $path === '/') {
    $errorMessage = isset($_GET['error']) ? (string) $_GET['error'] : '';
    render($TEMPLATES . '/index.php', $errorMessage);
    exit;
}

// =====================================================================
//  Fallback — unknown route
// =====================================================================
http_response_code(404);
echo 'Not Found';
