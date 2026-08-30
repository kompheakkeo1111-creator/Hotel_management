<?php
namespace App\Models;

use App\Core\Model;
use PDO;

class RoomType extends Model
{
    public function all()
    {
        return $this->db->query(
            "SELECT rt.*,
                    (SELECT COUNT(*) FROM rooms r WHERE r.room_type_id = rt.id) AS room_count
             FROM room_types rt
             ORDER BY rt.id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM room_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($type_name, $description, $price, $capacity)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO room_types (type_name, description, price_per_night, capacity)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$type_name, $description, $price, $capacity]);
        return (int)$this->db->lastInsertId();
    }

    public function update($id, $type_name, $description, $price, $capacity)
    {
        $stmt = $this->db->prepare(
            "UPDATE room_types SET type_name = ?, description = ?, price_per_night = ?, capacity = ? WHERE id = ?"
        );
        $stmt->execute([$type_name, $description, $price, $capacity, $id]);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM room_types WHERE id = ?");
        $stmt->execute([$id]);
    }
}
