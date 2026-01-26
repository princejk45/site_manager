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
    
    public function getById($groupId)
    {
        $stmt = $this->db->prepare("SELECT * FROM groups WHERE id = ?");
        $stmt->execute([$groupId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getMembers($groupId)
    {
        $stmt = $this->db->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
        $stmt->execute([$groupId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'user_id');
    }
    
    public function update($groupId, $name, $members = [])
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE groups SET name = ? WHERE id = ?");
            $stmt->execute([$name, $groupId]);

            // Remove existing members
            $stmt = $this->db->prepare("DELETE FROM group_members WHERE group_id = ?");
            $stmt->execute([$groupId]);

            // Add members (include creator if present in members list)
            $stmt = $this->db->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
            foreach (array_unique($members) as $userId) {
                $stmt->execute([$groupId, $userId]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function delete($groupId)
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("DELETE FROM group_members WHERE group_id = ?");
            $stmt->execute([$groupId]);

            $stmt = $this->db->prepare("DELETE FROM groups WHERE id = ?");
            $stmt->execute([$groupId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}