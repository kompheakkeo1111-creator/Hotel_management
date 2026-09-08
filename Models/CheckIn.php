<?php
namespace App\Models;

use App\Core\Model;
use PDO;

class CheckIn extends Model
{
    public function readyReservations()
    {
        return $this->db->query(
            "SELECT r.id, r.reservation_number, g.full_name guest_name, rm.room_number, r.check_in_date, r.check_out_date, r.status
             FROM reservations r
             JOIN guests g ON r.guest_id=g.id
             LEFT JOIN rooms rm ON r.room_id=rm.id
             WHERE r.status IN ('Pending','Confirmed') AND r.room_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM check_ins c WHERE c.reservation_id=r.id AND c.status='Active')
             ORDER BY r.check_in_date ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activeStays()
    {
        return $this->db->query(
            "SELECT c.id, g.full_name guest_name, rm.room_number, c.check_in_time, c.expected_check_out, r.reservation_number
             FROM check_ins c
             JOIN guests g ON c.guest_id=g.id
             JOIN rooms rm ON c.room_id=rm.id
             LEFT JOIN reservations r ON c.reservation_id=r.id
             WHERE c.status='Active' ORDER BY c.check_in_time DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activeStaysForCheckout()
    {
        return $this->db->query(
            "SELECT c.id, c.check_in_time, c.expected_check_out,
                    g.full_name guest_name, rm.room_number, rm.price_per_night,
                    r.reservation_number,
                    (SELECT COALESCE(SUM(amount),0) FROM extra_charges WHERE check_in_id=c.id) extra_total,
                    (SELECT COUNT(*) FROM extra_charges WHERE check_in_id=c.id) extra_count
             FROM check_ins c
             JOIN guests g ON c.guest_id=g.id
             JOIN rooms rm ON c.room_id=rm.id
             LEFT JOIN reservations r ON c.reservation_id=r.id
             WHERE c.status='Active'
             ORDER BY c.check_in_time ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByReservation($reservationId)
    {
        $stmt = $this->db->prepare("SELECT * FROM check_ins WHERE reservation_id=? AND status='Active'");
        $stmt->execute([$reservationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function activeCountForReservation($reservationId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM check_ins WHERE reservation_id=? AND status='Active'");
        $stmt->execute([$reservationId]);
        return (int)$stmt->fetchColumn();
    }

    public function roomCurrentlyOccupied($roomId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM check_ins WHERE room_id=? AND status='Active'");
        $stmt->execute([$roomId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function activeCountForRoomOverlap($roomId, $checkIn, $checkOut)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM check_ins WHERE room_id=? AND status='Active'");
        $stmt->execute([$roomId]);
        return (int)$stmt->fetchColumn();
    }

    public function insert($reservationId, $guestId, $roomId, $expectedOut, $userId)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO check_ins (reservation_id, guest_id, room_id, check_in_time, expected_check_out, status, created_by)
             VALUES (?,?,?,NOW(),?,'Active',?)"
        );
        $stmt->execute([$reservationId, $guestId, $roomId, $expectedOut, $userId]);
        return (int)$this->db->lastInsertId();
    }

    public function checkoutDetail($checkInId)
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, g.full_name AS guest_name, g.id AS guest_id,
                    rm.room_number, rm.id AS room_id, rm.price_per_night,
                    r.reservation_number, r.check_in_date, r.check_out_date, r.id AS reservation_id
             FROM check_ins c
             JOIN guests g ON c.guest_id=g.id
             JOIN rooms rm ON c.room_id=rm.id
             LEFT JOIN reservations r ON c.reservation_id=r.id
             WHERE c.id=? AND c.status='Active' LIMIT 1"
        );
        $stmt->execute([$checkInId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function markCheckedOut($checkInId)
    {
        $this->db->prepare("UPDATE check_ins SET status='Checked Out', actual_check_out=NOW() WHERE id=?")
            ->execute([$checkInId]);
    }

    public function extraChargesTotal($checkInId)
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM extra_charges WHERE check_in_id=?");
        $stmt->execute([$checkInId]);
        return (float)$stmt->fetchColumn();
    }

    public function addExtraCharge($checkInId, $type, $desc, $amount)
    {
        $stmt = $this->db->prepare("INSERT INTO extra_charges (check_in_id, charge_type, description, amount) VALUES (?,?,?,?)");
        $stmt->execute([$checkInId, $type, $desc, $amount]);
    }
}
