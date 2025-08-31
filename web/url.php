<?php
require 'db.php';
include_once 'translations.php';

$valid_months = [
    'ALL', 'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

function sanitizeInput($input, $type = 'string') {
    if ($input === null) {
        return null;
    }

    $input = strval($input);

    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0]
            ]) ? intval($input) : null;

        case 'alphanum':
            return preg_match('/^[a-zA-Z0-9]+$/', $input) ? $input : null;

        case 'month':
            global $valid_months;
            return in_array($input, $valid_months, true) ? $input : null;

        case 'year':
            $year = filter_var($input, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 2000, 'max_range' => 2100]
            ]);
            return $year !== false ? strval($year) : null;

        default:
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

// session ID sanitize
$current_seshid = sanitizeInput(
    $_GET["seshid"] ?? $_POST["seshidtag"] ?? $_GET["id"] ?? null,
    'alphanum'
);

// Calculate month from session ID if possible
$calculated_month = '';
if ($current_seshid && is_numeric($current_seshid)) {
    $timestamp = intval(substr($current_seshid, 0, -3));
    if ($timestamp > 0 && $timestamp < 2000000000) {
        $month_name = date('F', $timestamp);
        $calculated_month = in_array($month_name, $valid_months, true) ? $month_name : '';
    }
}

// Determine month with validation
$raw_month = $_POST["selmonth"] ?? $_GET["month"] ?? $calculated_month ?? '';
$month = sanitizeInput($raw_month, 'month');

// Build URL
$baselink = ".";
$outurl = $baselink;
$params = [];

// Always add ID if provided and valid
if ($current_seshid) {
    $params['id'] = $current_seshid;
}

// Add month if provided and valid
if (!empty($month)) {
    $params['month'] = $month;
}

// Add profile with validation
$raw_profile = $_POST["selprofile"] ?? $_GET["profile"] ?? null;
if ($raw_profile) {
    $profile = sanitizeInput($raw_profile);
    $lang = isset($_COOKIE['lang']) ? sanitizeInput($_COOKIE['lang'], 'alphanum') : 'en';

    if (isset($translations[$lang]) && $profile === $translations[$lang]['profile.ns']) {
        $profile = 'Not Specified';
    }
    $params['profile'] = $profile;
}

// Add year with validation
$raw_year = $_POST["selyear"] ?? $_GET["year"] ?? null;
if ($raw_year) {
    $year = sanitizeInput($raw_year, 'year');
    if ($year) {
        $params['year'] = $year;
    }
}

if (!empty($params)) {
    $outurl .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

$allowed_hosts = [$_SERVER['HTTP_HOST'], 'localhost'];
$parsed_url = parse_url($outurl);

if (!isset($parsed_url['host']) || in_array($parsed_url['host'], $allowed_hosts, true)) {
    if (strlen($outurl) < 2000) {
        header("Location: " . $outurl);
        exit;
    } else {
        error_log("Potential attack: Long URL attempted: " . $outurl);
        header("Location: " . $baselink);
        exit;
    }
} else {
    header("Location: " . $baselink);
    exit;
}
