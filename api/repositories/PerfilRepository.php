<?php
require_once __DIR__ . '/../config/Database.php';

class PerfilRepository {
    public function findById($userId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, username, email, avatar, created_at, updated_at FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function updateAvatar($userId, $avatarUrl) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->bind_param('si', $avatarUrl, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}