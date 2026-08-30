<?php
// =========================================================
// Shared page header + sidebar
// Expected variables (set before include):
//   $active   - key identifying the active nav item (e.g. 'dashboard')
//   $pageTitle- <title> suffix (optional)
// =========================================================
if (empty($active)) $active = '';
$pageTitle = isset($pageTitle) ? $pageTitle : APP_NAME;

function navItem($key, $href, $icon, $label) {
    global $active;
    $cls = ($active === $key) ? ' active' : '';
    return "<a class=\"nav-link$cls\" href=\"$href\"><i class=\"bi $icon\"></i> $label</a>\n";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?> - Hotel Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container-fluid"><div class="row">
<div class="col-md-2 sidebar">
    <a class="brand" href="dashboard.php">🏨 HMS</a>
    <div class="user-box">
        <small>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></small><br>
        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['user_role'] ?? ''); ?></small>
    </div>
    <nav class="nav flex-column">
        <?php echo navItem('dashboard', 'dashboard.php', 'bi-speedometer2', 'Dashboard'); ?>
        <?php echo navItem('users', 'users.php', 'bi-people', 'Users'); ?>
        <?php echo navItem('room_types', 'room_types.php', 'bi-grid', 'Room Types'); ?>
        <?php echo navItem('rooms', 'rooms.php', 'bi-door-open', 'Rooms'); ?>
        <?php echo navItem('guests', 'guests.php', 'bi-person', 'Guests'); ?>
        <?php echo navItem('reservations', 'reservations.php', 'bi-calendar-check', 'Reservations'); ?>
        <?php echo navItem('checkin', 'checkin.php', 'bi-box-arrow-in-right', 'Check-in'); ?>
        <?php echo navItem('checkout', 'checkout.php', 'bi-box-arrow-right', 'Check-out'); ?>
        <?php echo navItem('billing', 'billing.php', 'bi-receipt', 'Billing'); ?>
        <?php echo navItem('notifications', 'notifications.php', 'bi-bell', 'Notifications'); ?>
        <?php echo navItem('housekeeping', 'housekeeping.php', 'bi-broom', 'Housekeeping'); ?>
        <?php echo navItem('maintenance', 'maintenance.php', 'bi-wrench', 'Maintenance'); ?>
        <?php echo navItem('reports', 'reports.php', 'bi-file-text', 'Reports'); ?>
        <?php echo navItem('settings', 'settings.php', 'bi-gear', 'Settings'); ?>
        <?php echo navItem('', 'logout.php', 'bi-box-arrow-right', 'Logout'); ?>
    </nav>
</div>
<div class="col-md-10 main">
