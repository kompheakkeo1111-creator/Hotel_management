<?php
namespace App\Models;

use App\Core\Model;
use PDO;

class User extends Model
{
    public function all()
    {
        return $this->db->query(
            "SELECT * FROM users ORDER BY created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByUsername($username)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function usernameExists($username, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) FROM users WHERE username = ?";
        $params = [$username];
        if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function emailExists($email, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        $params = [$email];
        if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(array $data)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['username'], password_hash($data['password'], PASSWORD_DEFAULT),
            $data['full_name'], $data['email'], $data['phone'], $data['role'], $data['status']
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update($id, array $data)
    {
        $sql = "UPDATE users SET username=?, full_name=?, email=?, phone=?, role=?, status=? WHERE id=?";
        $params = [$data['username'], $data['full_name'], $data['email'], $data['phone'], $data['role'], $data['status'], $id];
        if (!empty($data['password'])) {
            $sql = "UPDATE users SET username=?, full_name=?, email=?, phone=?, role=?, status=?, password=? WHERE id=?";
            $params = [$data['username'], $data['full_name'], $data['email'], $data['phone'], $data['role'], $data['status'], password_hash($data['password'], PASSWORD_DEFAULT), $id];
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
    }
}
