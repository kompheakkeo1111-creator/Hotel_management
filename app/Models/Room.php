<?php
namespace App\Models;

use App\Core\Model;
use PDO;

class Room extends Model
{
    public function all(array $with = [])
    {
        $sql = "SELECT r.*, rt.type_name,
                       (SELECT g.full_name FROM check_ins c JOIN guests g ON g.id=c.guest_id
                         WHERE c.room_id=r.id AND c.status='Active' LIMIT 1) occupant
                FROM rooms r LEFT JOIN room_types rt ON r.room_type_id=rt.id";

        $where = [];
        $params = [];
        if (isset($with['status']) && $with['status'] !== '') { $where[] = 'r.status=?'; $params[] = $with['status']; }
        if (isset($with['floor']) && $with['floor'] !== '') { $where[] = 'r.floor=?'; $params[] = (int)$with['floor']; }
        if (isset($with['type']) && $with['type'] !== '') { $where[] = 'r.room_type_id=?'; $params[] = (int)$with['type']; }

        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY r.room_number';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM rooms WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO rooms (room_number, room_type_id, floor, capacity, price_per_night, description, status)
             VALUES (?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['room_number'], $data['room_type_id'], $data['floor'], $data['capacity'],
            $data['price_per_night'], $data['description'], $data['status']
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update($id, array $data)
    {
        $stmt = $this->db->prepare(
            "UPDATE rooms SET room_number=?, room_type_id=?, floor=?, capacity=?, price_per_night=?, description=?, status=? WHERE id=?"
        );
        $stmt->execute([
            $data['room_number'], $data['room_type_id'], $data['floor'], $data['capacity'],
            $data['price_per_night'], $data['description'], $data['status'], $id
        ]);
    }

    public function setStatus($id, $status)
    {
        $stmt = $this->db->prepare("UPDATE rooms SET status=? WHERE id=?");
        $stmt->execute([$status, $id]);
    }

    public function byStatuses(array $statuses)
    {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $stmt = $this->db->prepare(
            "SELECT r.*, rt.type_name FROM rooms r LEFT JOIN room_types rt ON r.room_type_id=rt.id
             WHERE r.status IN ($placeholders) ORDER BY r.room_number"
        );
        $stmt->execute($statuses);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hasReservations($id)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM reservations WHERE room_id=? AND status NOT IN ('Cancelled')");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function distinctFloors()
    {
        return $this->db->query("SELECT DISTINCT floor FROM rooms WHERE floor IS NOT NULL ORDER BY floor")
            ->fetchAll(PDO::FETCH_COLUMN);
    }
}
