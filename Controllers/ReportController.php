<?php
namespace App\Controllers;

use App\Core\Controller;

class ReportController extends Controller
{
    protected $active = 'reports';
    protected $pageTitle = 'Reports';

    public function __construct()
    {
        $this->loginRequired();
        $this->roleRequired(['Administrator', 'Manager', 'Accountant']);
        $this->db = getDB();
    }

    public function indexAction()
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        if (!strtotime($from)) $from = date('Y-m-01');
        if (!strtotime($to)) $to = date('Y-m-d');
        if (strtotime($to) < strtotime($from)) { $tmp = $from; $from = $to; $to = $tmp; }
        $rangeParams = [$from, $to];

        $totalRooms = (int)$this->db->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
        $occupiedRooms = (int)$this->db->query("SELECT COUNT(*) FROM rooms WHERE status='Occupied'")->fetchColumn();
        $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

        $revStmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(payment_date) BETWEEN ? AND ?");
        $revStmt->execute($rangeParams);
        $periodRevenue = (float)$revStmt->fetchColumn();
        $totalRevenue = (float)$this->db->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
        $currentGuests = (int)$this->db->query("SELECT COUNT(*) FROM check_ins WHERE status='Active'")->fetchColumn();

        if (($_GET['export'] ?? '') === 'csv') {
            $this->exportCsv($from, $to, $rangeParams, $totalRooms, $occupiedRooms, $occupancyRate, $currentGuests, $periodRevenue, $totalRevenue);
        }

        $roomNights = (int)$this->db->query("SELECT COALESCE(SUM(DATEDIFF(r.check_out_date, r.check_in_date)),0)
                                             FROM reservations r WHERE r.status IN ('Completed','Confirmed')
                                             AND MONTH(r.check_in_date)=MONTH(CURDATE())")->fetchColumn();
        $adr = ($roomNights > 0) ? round($periodRevenue / max($roomNights, 1), 2) : 0;
        $revpar = ($totalRooms > 0) ? round($periodRevenue / max($totalRooms * 30, 1), 2) : 0;

        $months = [];
        $mlabels = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("-{$i} months"));
            $row = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE_FORMAT(payment_date,'%Y-%m')=?");
            $row->execute([$m]);
            $months[] = (float)$row->fetchColumn();
            $mlabels[] = date('M', strtotime($m . '-01'));
        }

        $revTypeStmt = $this->db->prepare("SELECT rt.type_name, COALESCE(SUM(p.amount),0) revenue
                                           FROM room_types rt
                                           LEFT JOIN rooms r ON r.room_type_id=rt.id
                                           LEFT JOIN reservations res ON res.room_id=r.id
                                           LEFT JOIN payments p ON p.reservation_id=res.id AND DATE(p.payment_date) BETWEEN ? AND ?
                                           GROUP BY rt.id, rt.type_name");
        $revTypeStmt->execute($rangeParams);
        $revenueByType = $revTypeStmt->fetchAll(\PDO::FETCH_ASSOC);

        $topGuests = $this->db->query("SELECT g.full_name, COUNT(res.id) stays, COALESCE(SUM(res.total_amount),0) spend
                                       FROM guests g
                                       JOIN reservations res ON res.guest_id=g.id
                                       GROUP BY g.id, g.full_name ORDER BY stays DESC LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC);

        $revByTypeLabels = [];
        $revByTypeData = [];
        foreach ($revenueByType as $t) { $revByTypeLabels[] = $t['type_name']; $revByTypeData[] = (float)$t['revenue']; }

        $this->view('reports/index', [
            'from' => $from, 'to' => $to,
            'totalRooms' => $totalRooms, 'occupiedRooms' => $occupiedRooms, 'occupancyRate' => $occupancyRate,
            'currentGuests' => $currentGuests, 'periodRevenue' => $periodRevenue, 'totalRevenue' => $totalRevenue,
            'adr' => $adr, 'revpar' => $revpar, 'months' => $months, 'mlabels' => $mlabels,
            'revenueByType' => $revenueByType, 'topGuests' => $topGuests,
            'revByTypeLabels' => $revByTypeLabels, 'revByTypeData' => $revByTypeData,
        ], 'reports', 'Reports');
    }

    private function exportCsv($from, $to, $rangeParams, $totalRooms, $occupiedRooms, $occupancyRate, $currentGuests, $periodRevenue, $totalRevenue)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="hotel_report_' . $from . '_to_' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Metric', 'Value']);
        fputcsv($out, ['Period From', $from]);
        fputcsv($out, ['Period To', $to]);
        fputcsv($out, ['Total Rooms', $totalRooms]);
        fputcsv($out, ['Occupied Rooms', $occupiedRooms]);
        fputcsv($out, ['Occupancy Rate (%)', $occupancyRate]);
        fputcsv($out, ['Current Guests In-house', $currentGuests]);
        fputcsv($out, ['Revenue in Period', number_format($periodRevenue, 2)]);
        fputcsv($out, ['Total Revenue (all time)', number_format($totalRevenue, 2)]);
        $dayStmt = $this->db->prepare("SELECT DATE(payment_date) d, COALESCE(SUM(amount),0) amt FROM payments WHERE DATE(payment_date) BETWEEN ? AND ? GROUP BY DATE(payment_date) ORDER BY d");
        $dayStmt->execute($rangeParams);
        fputcsv($out, ['']);
        fputcsv($out, ['Daily Revenue']);
        fputcsv($out, ['Date', 'Amount']);
        foreach ($dayStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            fputcsv($out, [$row['d'], number_format($row['amt'], 2)]);
        }
        fclose($out);
        exit;
    }
}
