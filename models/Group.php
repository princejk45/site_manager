<?php
class Group
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function create($name, $creatorId, $members = [])
    {
        $this->db->beginTransaction();
        try {
            // Create group
            $stmt = $this->db->prepare("INSERT INTO groups (name, created_by) VALUES (?, ?)");
            $stmt->execute([$name, $creatorId]);
            $groupId = $this->db->lastInsertId();

            // Add members
            $stmt = $this->db->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
            foreach (array_unique(array_merge([$creatorId], $members)) as $userId) {
                $stmt->execute([$groupId, $userId]);
            }

            $this->db->commit();
            return $groupId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getUserGroups($userId)
    {
        $stmt = $this->db->prepare("
            SELECT g.* FROM groups g
            JOIN group_members gm ON g.id = gm.group_id
            WHERE gm.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}