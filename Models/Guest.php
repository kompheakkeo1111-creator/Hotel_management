<?php
namespace App\Models;

use App\Core\Model;
use PDO;

class Guest extends Model
{
    public function all()
    {
        return $this->db->query(
            "SELECT g.*,
                    (SELECT COUNT(*) FROM reservations r WHERE r.guest_id=g.id AND r.status != 'Cancelled') stays,
                    (SELECT COUNT(*) FROM check_ins c WHERE c.guest_id=g.id AND c.status='Active') active_stay
             FROM guests g ORDER BY g.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM guests WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO guests (full_name, gender, date_of_birth, nationality, phone, email, address, identification_number)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['full_name'], $data['gender'], $data['date_of_birth'], $data['nationality'],
            $data['phone'], $data['email'], $data['address'], $data['identification_number']
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update($id, array $data)
    {
        $stmt = $this->db->prepare(
            "UPDATE guests SET full_name=?, gender=?, date_of_birth=?, nationality=?, phone=?, email=?, address=?, identification_number=? WHERE id=?"
        );
        $stmt->execute([
            $data['full_name'], $data['gender'], $data['date_of_birth'], $data['nationality'],
            $data['phone'], $data['email'], $data['address'], $data['identification_number'], $id
        ]);
    }

    public function hasReservations($id)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM reservations WHERE guest_id=?");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM guests WHERE id = ?");
        $stmt->execute([$id]);
    }
}
