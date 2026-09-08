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
<link rel="stylesheet" href="assets/css/style.css">
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
        <?php if (hasRole(['Administrator'])): ?>
            <?php echo navItem('users', 'index.php?r=users/index', 'bi-people', 'Users'); ?>
        <?php endif; ?>
        <?php if (hasRole(['Administrator', 'Receptionist', 'Manager'])): ?>
            <?php echo navItem('room_types', 'index.php?r=room_types/index', 'bi-grid', 'Room Types'); ?>
        <?php endif; ?>
        <?php if (hasRole(['Administrator', 'Receptionist', 'Manager', 'Housekeeping Staff'])): ?>
            <?php echo navItem('rooms', 'index.php?r=rooms/index', 'bi-door-open', 'Rooms'); ?>
        <?php endif; ?>
        <?php if (hasRole(['Administrator', 'Receptionist', 'Manager'])): ?>
            <?php echo navItem('guests', 'index.php?r=guests/index', 'bi-person', 'Guests'); ?>
        <?php endif; ?>
        <?php if (hasRole(['Administrator', 'Receptionist', 'Manager'])): ?>
            <?php echo navItem('reservations', 'index.php?r=reservations/index', 'bi-calendar-check', 'Reservations'); ?>
        <?php endif; ?>
        <?php if (hasRole(['Administrator', 'Receptionist', 'Manager'])): ?>
            <?php echo navItem('checkin', 'index.php?r=checkin/index', 'bi-box-arrow-in-right', 'Check-in'); ?>
        <?php endif; ?>
        <?php if (hasRole(['Administrator', 'Receptionist', 'Manager'])): ?>
            <?php echo navItem('checkout', 'index.php?r=checkout/index', 'bi-box-arrow-right', 'Check-out'); ?>
        <?php endif; ?>
        <?php if (hasRole(['Administrator', 'Accountant', 'Manager'])): ?>
            <?php echo navItem('billing', 'index.php?r=billing/index', 'bi-receipt', 'Billing'); ?>
        <?php endif; ?>
        <?php echo navItem('notifications', 'index.php?r=notifications/index', 'bi-bell', 'Notifications'); ?>
        <?php if (hasRole(['Administrator', 'Housekeeping Staff'])): ?>
            <?php echo navItem('housekeeping', 'index.php?r=housekeeping/index', 'bi-broom', 'Housekeeping'); ?>
        <?php endif; ?>
        <?php if (hasRole(['Administrator', 'Housekeeping Staff', 'Manager'])): ?>
            <?php echo navItem('maintenance', 'index.php?r=maintenance/index', 'bi-wrench', 'Maintenance'); ?>
        <?php endif; ?>
        <?php if (hasRole(['Administrator', 'Manager', 'Accountant'])): ?>
            <?php echo navItem('reports', 'index.php?r=reports/index', 'bi-file-text', 'Reports'); ?>
        <?php endif; ?>
        <?php if (hasRole(['Administrator'])): ?>
            <?php echo navItem('settings', 'index.php?r=settings/index', 'bi-gear', 'Settings'); ?>
        <?php endif; ?>
        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</div>
<div class="col-md-10 main">

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to logout?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="index.php?r=logout/index" class="btn btn-danger">Logout</a>
      </div>
    </div>
  </div>
</div>
