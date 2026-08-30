<?php
// =========================================================
// MVC layout header + sidebar (native replacement for includes/header.php)
// Expected variables (set before include by Controller::view()):
//   $active   - key identifying the active nav item (e.g. 'dashboard')
//   $pageTitle - <title> suffix (optional)
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
<link rel="stylesheet" href="app/Public/assets/css/style.css">
</head>
<body>
<div class="container-fluid"><div class="row">
<div class="col-md-2 sidebar">
    <a class="brand" href="index.php?r=dashboard/index">🏨 HMS</a>
    <div class="user-box">
        <small>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></small><br>
        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['user_role'] ?? ''); ?></small>
    </div>
    <nav class="nav flex-column">
        <?php echo navItem('dashboard', 'index.php?r=dashboard/index', 'bi-speedometer2', 'Dashboard'); ?>
        <?php echo navItem('users', 'index.php?r=users/index', 'bi-people', 'Users'); ?>
        <?php echo navItem('room_types', 'index.php?r=room_types/index', 'bi-grid', 'Room Types'); ?>
        <?php echo navItem('rooms', 'index.php?r=rooms/index', 'bi-door-open', 'Rooms'); ?>
        <?php echo navItem('guests', 'index.php?r=guests/index', 'bi-person', 'Guests'); ?>
        <?php echo navItem('reservations', 'index.php?r=reservations/index', 'bi-calendar-check', 'Reservations'); ?>
        <?php echo navItem('checkin', 'index.php?r=checkin/index', 'bi-box-arrow-in-right', 'Check-in'); ?>
        <?php echo navItem('checkout', 'index.php?r=checkout/index', 'bi-box-arrow-right', 'Check-out'); ?>
        <?php echo navItem('billing', 'index.php?r=billing/index', 'bi-receipt', 'Billing'); ?>
        <?php echo navItem('notifications', 'index.php?r=notifications/index', 'bi-bell', 'Notifications'); ?>
        <?php echo navItem('housekeeping', 'index.php?r=housekeeping/index', 'bi-broom', 'Housekeeping'); ?>
        <?php echo navItem('maintenance', 'index.php?r=maintenance/index', 'bi-wrench', 'Maintenance'); ?>
        <?php echo navItem('reports', 'index.php?r=reports/index', 'bi-file-text', 'Reports'); ?>
        <?php echo navItem('settings', 'index.php?r=settings/index', 'bi-gear', 'Settings'); ?>
        <?php echo navItem('', 'index.php?r=auth/logout', 'bi-box-arrow-right', 'Logout'); ?>
    </nav>
</div>
<div class="col-md-10 main">
