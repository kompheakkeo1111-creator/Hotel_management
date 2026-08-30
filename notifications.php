<?php
require_once 'config.php';
requireLogin();
$db = getDB();

function e($v){return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');}

$notifications = [];

$stmt = $db->query("SELECT COUNT(*) FROM reservations WHERE check_in_date=CURDATE() AND status IN ('Pending','Confirmed')");
$n = (int)$stmt->fetchColumn();
if ($n) $notifications[] = ['type'=>'success','icon'=>'bi-box-arrow-in-right','title'=>'Today\'s Check-ins','text'=>$n.' guest reservation(s) are scheduled to check in today.','link'=>'reservations.php'];

$stmt = $db->query("SELECT COUNT(*) FROM reservations WHERE check_out_date=CURDATE() AND status IN ('Pending','Confirmed')");
$n = (int)$stmt->fetchColumn();
if ($n) $notifications[] = ['type'=>'warning','icon'=>'bi-box-arrow-right','title'=>'Today\'s Check-outs','text'=>$n.' guest reservation(s) are scheduled to check out today.','link'=>'checkout.php'];

$stmt = $db->query("SELECT COUNT(*) FROM rooms WHERE status='Cleaning'");
$n = (int)$stmt->fetchColumn();
if ($n) $notifications[] = ['type'=>'warning','icon'=>'bi-broom','title'=>'Rooms Need Cleaning','text'=>$n.' room(s) are waiting for housekeeping.','link'=>'housekeeping.php'];

$stmt = $db->query("SELECT COUNT(*) FROM rooms WHERE status='Maintenance'");
$n = (int)$stmt->fetchColumn();
if ($n) $notifications[] = ['type'=>'danger','icon'=>'bi-wrench','title'=>'Maintenance Required','text'=>$n.' room(s) are currently under maintenance.','link'=>'maintenance.php'];

$stmt = $db->query("SELECT COUNT(*) FROM reservations WHERE status='Pending'");
$n = (int)$stmt->fetchColumn();
if ($n) $notifications[] = ['type'=>'info','icon'=>'bi-calendar-check','title'=>'Pending Reservations','text'=>$n.' reservation(s) are still pending confirmation.','link'=>'reservations.php'];

$stmt = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(payment_date)=CURDATE()");
$todayRevenue = (float)$stmt->fetchColumn();
$settings = getSystemSettings();

$active = 'notifications';
$pageTitle = 'Notifications';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Notifications</h2><p class="page-subtitle mb-0">Hotel activities that need attention</p></div>
    <span class="badge bg-success fs-6">Today Revenue: <?php echo formatCurrency($todayRevenue); ?></span>
</div>

<?php if (!$notifications): ?>
    <div class="card p-5 text-center">
        <i class="bi bi-check-circle text-success" style="font-size:50px"></i>
        <h4 class="mt-3">All Clear</h4>
        <p class="text-muted mb-0">There are no urgent notifications right now.</p>
    </div>
<?php else: ?>
    <div class="notification-list">
    <?php foreach ($notifications as $n): ?>
        <div class="d-flex align-items-center gap-3 mb-3 p-3 bg-white rounded border-start border-4 border-<?php echo $n['type']=='danger'?'danger':($n['type']=='warning'?'warning':'success'); ?> shadow-sm">
            <div class="n-icon"><i class="bi <?php echo $n['icon']; ?>"></i></div>
            <div class="flex-grow-1">
                <h6 class="mb-1"><?php echo $n['title']; ?></h6>
                <div class="text-muted small"><?php echo $n['text']; ?></div>
            </div>
            <a class="btn btn-outline-dark btn-sm" href="<?php echo $n['link']; ?>">View</a>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
