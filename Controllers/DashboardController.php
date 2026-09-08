<?php
namespace App\Controllers;

use App\Core\Controller;

class DashboardController extends Controller
{
    protected $active = 'dashboard';
    protected $pageTitle = 'Dashboard';

    public function __construct()
    {
        $this->loginRequired();
        $this->db = getDB();
    }

    public function indexAction()
    {
        $db = $this->db;

        $totalRooms = (int)$db->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
        $availableRooms = (int)$db->query("SELECT COUNT(*) FROM rooms WHERE status='Available'")->fetchColumn();
        $occupiedRooms = (int)$db->query("SELECT COUNT(*) FROM rooms WHERE status='Occupied'")->fetchColumn();
        $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

        $currentGuests = (int)$db->query("SELECT COUNT(*) FROM check_ins WHERE status='Active'")->fetchColumn();
        $todayCheckins = (int)$db->query("SELECT COUNT(*) FROM check_ins WHERE DATE(check_in_time)=CURDATE()")->fetchColumn();
        $todayCheckouts = (int)$db->query("SELECT COUNT(*) FROM check_ins WHERE DATE(actual_check_out)=CURDATE()")->fetchColumn();

        $todayRevenue = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(payment_date)=CURDATE()")->fetchColumn();
        $monthlyRevenue = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())")->fetchColumn();

        $revenueDays = [];
        $revenueLabels = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $row = $db->prepare("SELECT COALESCE(SUM(amount),0) v FROM payments WHERE DATE(payment_date)=?");
            $row->execute([$d]);
            $revenueDays[] = (float)$row->fetchColumn();
            $revenueLabels[] = date('M d', strtotime($d));
        }

        $revByTypeLabels = [];
        $revByTypeData = [];
        foreach ($db->query("SELECT rt.type_name, COALESCE(SUM(p.amount),0) revenue
                            FROM room_types rt
                            LEFT JOIN rooms r ON r.room_type_id=rt.id
                            LEFT JOIN reservations res ON res.room_id=r.id
                            LEFT JOIN payments p ON p.reservation_id=res.id
                            GROUP BY rt.id, rt.type_name ORDER BY revenue DESC") as $t) {
            $revByTypeLabels[] = $t['type_name'];
            $revByTypeData[] = (float)$t['revenue'];
        }

        $arrivals = $db->query("SELECT r.reservation_number, g.full_name, rm.room_number
                                FROM reservations r
                                JOIN guests g ON r.guest_id=g.id
                                LEFT JOIN rooms rm ON r.room_id=rm.id
                                WHERE r.check_in_date=CURDATE() AND r.status IN ('Pending','Confirmed')
                                ORDER BY r.id")->fetchAll(\PDO::FETCH_ASSOC);

        $departures = $db->query("SELECT g.full_name, rm.room_number, c.expected_check_out
                                  FROM check_ins c
                                  JOIN guests g ON c.guest_id=g.id
                                  LEFT JOIN rooms rm ON c.room_id=rm.id
                                  WHERE c.status='Active' AND c.expected_check_out <= CURDATE()
                                  ORDER BY c.expected_check_out")->fetchAll(\PDO::FETCH_ASSOC);

        $recentReservations = $db->query("SELECT r.*, g.full_name guest_name, rm.room_number
                                          FROM reservations r
                                          JOIN guests g ON r.guest_id=g.id
                                          LEFT JOIN rooms rm ON r.room_id=rm.id
                                          ORDER BY r.created_at DESC LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC);

        $rooms = $db->query("SELECT r.id, r.room_number, r.status, rt.type_name,
                                    (SELECT g.full_name FROM check_ins c JOIN guests g ON g.id=c.guest_id
                                      WHERE c.room_id=r.id AND c.status='Active' LIMIT 1) occupant
                             FROM rooms r
                             LEFT JOIN room_types rt ON r.room_type_id=rt.id
                             ORDER BY r.room_number")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('dashboard/index', [
            'totalRooms' => $totalRooms, 'availableRooms' => $availableRooms, 'occupiedRooms' => $occupiedRooms,
            'occupancyRate' => $occupancyRate, 'currentGuests' => $currentGuests,
            'todayCheckins' => $todayCheckins, 'todayCheckouts' => $todayCheckouts,
            'todayRevenue' => $todayRevenue, 'monthlyRevenue' => $monthlyRevenue,
            'revenueDays' => $revenueDays, 'revenueLabels' => $revenueLabels,
            'revByTypeLabels' => $revByTypeLabels, 'revByTypeData' => $revByTypeData,
            'arrivals' => $arrivals, 'departures' => $departures,
            'recentReservations' => $recentReservations, 'rooms' => $rooms,
        ], 'dashboard', 'Dashboard');
    }
}
