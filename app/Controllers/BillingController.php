<?php
namespace App\Controllers;

use App\Core\Controller;

class BillingController extends Controller
{
    protected $active = 'billing';
    protected $pageTitle = 'Billing';

    private $methods = ['Cash', 'Credit/Debit Card', 'QR Payment', 'Bank Transfer'];
    private $perPage = 15;

    public function __construct()
    {
        $this->loginRequired();
        $this->db = getDB();
    }

    public function indexAction()
    {
        $search = trim($_GET['search'] ?? '');
        $method = trim($_GET['payment_method'] ?? '');
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        $export = $_GET['export'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));

        if ($export === 'csv') {
            $this->exportCsv($search, $method, $startDate, $endDate);
        }

        $base = "FROM payments p
                 JOIN check_ins ci ON p.check_in_id = ci.id
                 JOIN guests g ON ci.guest_id = g.id
                 JOIN rooms rm ON ci.room_id = rm.id
                 LEFT JOIN reservations r ON p.reservation_id = r.id
                 WHERE 1=1";
        $params = [];
        if ($search !== '') {
            $base .= " AND (g.full_name LIKE ? OR rm.room_number LIKE ? OR p.invoice_number LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($method !== '') { $base .= " AND p.payment_method = ?"; $params[] = $method; }
        if ($startDate !== '') { $base .= " AND DATE(p.payment_date) >= ?"; $params[] = $startDate; }
        if ($endDate !== '') { $base .= " AND DATE(p.payment_date) <= ?"; $params[] = $endDate; }

        $countStmt = $this->db->prepare("SELECT COUNT(*) " . $base);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $this->perPage));
        if ($page > $pages) $page = $pages;
        $offset = ($page - 1) * $this->perPage;

        $stmt = $this->db->prepare("SELECT p.*, g.full_name, rm.room_number, r.reservation_number, ci.check_in_time " . $base . " ORDER BY p.payment_date DESC LIMIT {$this->perPage} OFFSET $offset");
        $stmt->execute($params);
        $payments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $summary = [];
        foreach ($this->methods as $m) $summary[$m] = ['count' => 0, 'amount' => 0];
        $sumStmt = $this->db->prepare("SELECT payment_method, COUNT(*) cnt, COALESCE(SUM(amount),0) amt " . $base . " GROUP BY payment_method");
        $sumStmt->execute($params);
        foreach ($sumStmt->fetchAll(\PDO::FETCH_ASSOC) as $s) {
            $summary[$s['payment_method']] = ['count' => $s['cnt'], 'amount' => $s['amt']];
        }
        $grandTotal = array_sum(array_column($summary, 'amount'));

        $this->view('billing/index', [
            'payments'   => $payments,
            'summary'    => $summary,
            'grandTotal' => $grandTotal,
            'methods'    => $this->methods,
            'search'     => $search,
            'method'     => $method,
            'startDate'  => $startDate,
            'endDate'    => $endDate,
            'page'       => $page,
            'pages'      => $pages,
        ], 'billing', 'Billing');
    }

    private function exportCsv($search, $method, $startDate, $endDate)
    {
        $base = "FROM payments p
                 JOIN check_ins ci ON p.check_in_id = ci.id
                 JOIN guests g ON ci.guest_id = g.id
                 JOIN rooms rm ON ci.room_id = rm.id
                 LEFT JOIN reservations r ON p.reservation_id = r.id
                 WHERE 1=1";
        $params = [];
        if ($search !== '') {
            $base .= " AND (g.full_name LIKE ? OR rm.room_number LIKE ? OR p.invoice_number LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($method !== '') { $base .= " AND p.payment_method = ?"; $params[] = $method; }
        if ($startDate !== '') { $base .= " AND DATE(p.payment_date) >= ?"; $params[] = $startDate; }
        if ($endDate !== '') { $base .= " AND DATE(p.payment_date) <= ?"; $params[] = $endDate; }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="billing_report_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Invoice #', 'Guest', 'Room', 'Reservation', 'Amount', 'Method', 'Transaction ID', 'Date']);
        $stmt = $this->db->prepare("SELECT p.*, g.full_name, rm.room_number, r.reservation_number " . $base . " ORDER BY p.payment_date DESC");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            fputcsv($output, [
                $row['invoice_number'], $row['full_name'], $row['room_number'],
                $row['reservation_number'] ?? 'N/A', number_format($row['amount'], 2),
                $row['payment_method'], $row['transaction_id'] ?? '', $row['payment_date']
            ]);
        }
        fclose($output);
        exit;
    }
}
