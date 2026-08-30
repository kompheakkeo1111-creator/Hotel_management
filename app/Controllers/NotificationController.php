<?php
namespace App\Controllers;

use App\Core\Controller;

class NotificationController extends Controller
{
    protected $active = 'notifications';
    protected $pageTitle = 'Notifications';

    public function __construct()
    {
        $this->loginRequired();
        $this->db = getDB();
    }

    public function indexAction()
    {
        $notifications = [];

        $stmt = $this->db->query("SELECT COUNT(*) FROM reservations WHERE check_in_date=CURDATE() AND status IN ('Pending','Confirmed')");
        $n = (int)$stmt->fetchColumn();
        if ($n) $notifications[] = ['type'=>'success','icon'=>'bi-box-arrow-in-right','title'=>'Today\'s Check-ins','text'=>$n.' guest reservation(s) are scheduled to check in today.','link'=>'index.php?r=checkin/index'];

        $stmt = $this->db->query("SELECT COUNT(*) FROM reservations WHERE check_out_date=CURDATE() AND status IN ('Pending','Confirmed')");
        $n = (int)$stmt->fetchColumn();
        if ($n) $notifications[] = ['type'=>'warning','icon'=>'bi-box-arrow-right','title'=>'Today\'s Check-outs','text'=>$n.' guest reservation(s) are scheduled to check out today.','link'=>'index.php?r=checkout/index'];

        $stmt = $this->db->query("SELECT COUNT(*) FROM rooms WHERE status='Cleaning'");
        $n = (int)$stmt->fetchColumn();
        if ($n) $notifications[] = ['type'=>'warning','icon'=>'bi-broom','title'=>'Rooms Need Cleaning','text'=>$n.' room(s) are waiting for housekeeping.','link'=>'index.php?r=housekeeping/index'];

        $stmt = $this->db->query("SELECT COUNT(*) FROM rooms WHERE status='Maintenance'");
        $n = (int)$stmt->fetchColumn();
        if ($n) $notifications[] = ['type'=>'danger','icon'=>'bi-wrench','title'=>'Maintenance Required','text'=>$n.' room(s) are currently under maintenance.','link'=>'index.php?r=maintenance/index'];

        $stmt = $this->db->query("SELECT COUNT(*) FROM reservations WHERE status='Pending'");
        $n = (int)$stmt->fetchColumn();
        if ($n) $notifications[] = ['type'=>'info','icon'=>'bi-calendar-check','title'=>'Pending Reservations','text'=>$n.' reservation(s) are still pending confirmation.','link'=>'index.php?r=reservations/index'];

        $stmt = $this->db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(payment_date)=CURDATE()");
        $todayRevenue = (float)$stmt->fetchColumn();

        $this->view('notifications/index', [
            'notifications' => $notifications,
            'todayRevenue'  => $todayRevenue,
        ], 'notifications', 'Notifications');
    }
}
