<?php
namespace App\Models;

use App\Core\Model;
use PDO;

class Reservation extends Model
{
    public function all($status = '', $q = '')
    {
        $where = [];
        $params = [];
        if ($status !== '') { $where[] = 'r.status=?'; $params[] = $status; }
        if ($q !== '') { $where[] = '(r.reservation_number LIKE ? OR g.full_name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

        $sql = "SELECT r.*, g.full_name guest_name, rm.room_number, rm.price_per_night, rt.type_name,
                DATEDIFF(r.check_out_date, r.check_in_date) nights
                FROM reservations r
                JOIN guests g ON r.guest_id=g.id
                LEFT JOIN rooms rm ON r.room_id=rm.id
                LEFT JOIN room_types rt ON rm.room_type_id=rt.id";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY r.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function roomIsAvailable($room_id, $check_in, $check_out, $excludeResId = 0)
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM reservations
             WHERE room_id=? AND status NOT IN ('Cancelled')
             AND id <> ? AND check_in_date < ? AND check_out_date > ?"
        );
        $stmt->execute([$room_id, $excludeResId, $check_out, $check_in]);
        return (int)$stmt->fetchColumn() === 0;
    }

    public function roomNightlyRate($room_id)
    {
        $stmt = $this->db->prepare("SELECT price_per_night FROM rooms WHERE id=?");
        $stmt->execute([$room_id]);
        return (float)$stmt->fetchColumn();
    }

    public function create(array $data)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO reservations (reservation_number, guest_id, room_id, check_in_date, check_out_date, number_of_guests, status, special_requests, total_amount, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['reservation_number'], $data['guest_id'], $data['room_id'],
            $data['check_in_date'], $data['check_out_date'], $data['number_of_guests'],
            $data['status'], $data['special_requests'], $data['total_amount'], $data['created_by']
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateDatesAndStatus($id, $check_in, $check_out, $numGuests, $status, $requests, $totalAmount)
    {
        $stmt = $this->db->prepare(
            "UPDATE reservations SET check_in_date=?, check_out_date=?, number_of_guests=?, status=?, special_requests=?, total_amount=? WHERE id=?"
        );
        $stmt->execute([$check_in, $check_out, $numGuests, $status, $requests, $totalAmount, $id]);
    }

    public function setStatus($id, $status)
    {
        $stmt = $this->db->prepare("UPDATE reservations SET status=? WHERE id=?");
        $stmt->execute([$status, $id]);
    }

    public function roomIdFor($id)
    {
        $stmt = $this->db->prepare("SELECT room_id FROM reservations WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    public function hasActiveCheckIn($id)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM check_ins WHERE reservation_id=? AND status='Active'");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function hasOtherNonCancelledForRoom($room_id)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM reservations WHERE room_id=? AND status NOT IN ('Cancelled')");
        $stmt->execute([$room_id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM reservations WHERE id=?");
        $stmt->execute([$id]);
    }

    public function setRoomStatus($room_id, $status)
    {
        $stmt = $this->db->prepare("UPDATE rooms SET status=? WHERE id=? AND status='Reserved'");
        $stmt->execute([$status, $room_id]);
    }
}
