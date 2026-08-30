<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hotel_management');

// Application configuration
define('APP_NAME', 'Hotel Management System');

// Session configuration
session_start();

// Database connection
function getDB() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    if (!is_array($roles)) $roles = [$roles];
    return in_array($_SESSION['user_role'], $roles);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php?r=auth/login');
        exit();
    }
}

// Redirect if no permission
function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        header('Location: index.php?r=dashboard/index');
        exit();
    }
}

// Generate reservation number
function generateReservationNumber() {
    return 'RES-' . date('Ymd') . '-' . rand(1000, 9999);
}

// Format currency
function formatCurrency($amount) {
    $settings = getSystemSettings();
    $currency = $settings['currency'] ?? 'USD';
    return $currency . ' ' . number_format($amount, 2);
}

// Compute stay billing breakdown for a check-in (nights x rate + extras + tax)
function computeCheckinBill($db, $check_in_id, $tax_rate = 0) {
    $stmt = $db->prepare("SELECT c.check_in_time, c.expected_check_out, rm.price_per_night
                          FROM check_ins c JOIN rooms rm ON c.room_id=rm.id WHERE c.id=?");
    $stmt->execute([$check_in_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $checkin = date('Y-m-d', strtotime($row['check_in_time']));
    $checkout = $row['expected_check_out'];
    if (strtotime($checkout) < strtotime($checkin)) $checkout = $checkin;
    $nights = max(1, (int)((strtotime(date('Y-m-d', strtotime($checkout))) - strtotime(date('Y-m-d', strtotime($checkin)))) / 86400));

    $room_charge = round($nights * (float)$row['price_per_night'], 2);

    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM extra_charges WHERE check_in_id=?");
    $stmt->execute([$check_in_id]);
    $extra = (float)$stmt->fetchColumn();

    $subtotal = $room_charge + $extra;
    $tax = round($subtotal * $tax_rate / 100, 2);
    return [
        'nights' => $nights,
        'rate' => (float)$row['price_per_night'],
        'room_charge' => $room_charge,
        'extra' => $extra,
        'tax' => $tax,
        'total' => round($subtotal + $tax, 2)
    ];
}

// Get system settings
function getSystemSettings() {
    $db = getDB();
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}
?>