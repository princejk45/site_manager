<?php
class MessageThread
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function createThread($subject, $creatorId, $participantIds, $content, $groupId = null, $serviceId = null, $clientCcEmail = null)
    {
        $this->db->beginTransaction();
        try {
            // Create thread
            $stmt = $this->db->prepare("
                INSERT INTO message_threads (subject, group_id, service_id, client_cc_email) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$subject, $groupId, $serviceId, $clientCcEmail]);
            $threadId = $this->db->lastInsertId();

            // Add participants
            $stmt = $this->db->prepare("
                INSERT INTO thread_participants (thread_id, user_id) 
                VALUES (?, ?)
            ");
            foreach (array_unique(array_merge([$creatorId], $participantIds)) as $userId) {
                $stmt->execute([$threadId, $userId]);
            }

            // Add first message
            $this->addMessage($threadId, $creatorId, $content, true);

            $this->db->commit();
            return $threadId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function addMessage($threadId, $senderId, $content, $isFirst = false)
    {
        $stmt = $this->db->prepare("
            INSERT INTO messages (thread_id, sender_id, content, is_first) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$threadId, $senderId, $content, $isFirst]);
        return $this->db->lastInsertId();
    }

    public function getThreadMessages($threadId, $userId)
    {
        // Mark as read
        $this->markAsRead($threadId, $userId);

        // Get messages
        $stmt = $this->db->prepare("
            SELECT m.*, u.username 
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.thread_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$threadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markAsRead($threadId, $userId)
    {
        $stmt = $this->db->prepare("
            UPDATE thread_participants 
            SET last_read_at = NOW() 
            WHERE thread_id = ? AND user_id = ?
        ");
        $stmt->execute([$threadId, $userId]);
    }

    public function getUnreadCount($userId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM messages m
            JOIN thread_participants tp ON m.thread_id = tp.thread_id
            WHERE tp.user_id = ? 
            AND (tp.last_read_at IS NULL OR m.created_at > tp.last_read_at)
            AND m.sender_id != ?
        ");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchColumn();
    }

    // NEW METHODS NEEDED BY CONTROLLER
    public function getUserThreads($userId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                t.id,
                t.subject,
                t.created_at,
                (SELECT content FROM messages WHERE thread_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT COUNT(*) FROM messages m 
                 JOIN thread_participants tp ON m.thread_id = tp.thread_id 
                 WHERE tp.user_id = ? AND tp.thread_id = t.id 
                 AND (tp.last_read_at IS NULL OR m.created_at > tp.last_read_at)
                 AND m.sender_id != ?) as unread_count,
                (SELECT username FROM users u 
                 JOIN messages m ON m.sender_id = u.id 
                 WHERE m.thread_id = t.id 
                 ORDER BY m.created_at DESC LIMIT 1) as last_sender
            FROM message_threads t
            JOIN thread_participants p ON t.id = p.thread_id
            WHERE p.user_id = ?
            ORDER BY (SELECT MAX(created_at) FROM messages WHERE thread_id = t.id) DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFirstMessage($threadId)
    {
        $stmt = $this->db->prepare("
            SELECT m.content, t.subject 
            FROM messages m
            JOIN message_threads t ON m.thread_id = t.id
            WHERE m.thread_id = ? AND m.is_first = TRUE
            LIMIT 1
        ");
        $stmt->execute([$threadId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getThreadParticipants($threadId, $excludeUserId = null)
    {
        $sql = "
            SELECT u.id, u.email, u.username 
            FROM thread_participants tp
            JOIN users u ON tp.user_id = u.id
            WHERE tp.thread_id = ?
        ";

        $params = [$threadId];

        if ($excludeUserId) {
            $sql .= " AND tp.user_id != ?";
            $params[] = $excludeUserId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMessageById($messageId)
    {
        $stmt = $this->db->prepare("
            SELECT m.*, u.username, u.email
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.id = ?
            LIMIT 1
        ");
        $stmt->execute([$messageId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getThreadParticipantsWithDetails($threadId)
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT 
                u.id,
                u.username,
                u.email,
                (SELECT COUNT(*) FROM messages WHERE thread_id = ? AND sender_id = u.id) as message_count
            FROM thread_participants tp
            JOIN users u ON tp.user_id = u.id
            WHERE tp.thread_id = ?
            ORDER BY u.username ASC
        ");
        $stmt->execute([$threadId, $threadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getThreadSummary($threadId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                t.id,
                t.subject,
                t.created_at,
                t.service_id,
                t.client_cc_email,
                (SELECT COUNT(*) FROM messages WHERE thread_id = ?) as message_count,
                (SELECT COUNT(*) FROM thread_participants WHERE thread_id = ?) as participant_count,
                (SELECT u.username FROM users u JOIN messages m ON m.sender_id = u.id WHERE m.thread_id = ? AND m.is_first = TRUE LIMIT 1) as creator_name
            FROM message_threads t
            WHERE t.id = ?
        ");
        $stmt->execute([$threadId, $threadId, $threadId, $threadId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getMessagesSinceRead($threadId, $userId)
    {
        // Get last read time for user in this thread
        $stmt = $this->db->prepare("
            SELECT last_read_at FROM thread_participants WHERE thread_id = ? AND user_id = ?
        ");
        $stmt->execute([$threadId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastReadAt = $result['last_read_at'] ?? null;

        // Get new messages since last read
        $sql = "
            SELECT m.*, u.username, u.email
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.thread_id = ?
        ";
        $params = [$threadId];

        if ($lastReadAt) {
            $sql .= " AND m.created_at > ?";
            $params[] = $lastReadAt;
        }

        $sql .= " ORDER BY m.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getThreadCreator($threadId)
    {
        $stmt = $this->db->prepare("
            SELECT sender_id FROM messages WHERE thread_id = ? AND is_first = TRUE LIMIT 1
        ");
        $stmt->execute([$threadId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['sender_id'] ?? null;
    }

    public function deleteThread($threadId)
    {
        $this->db->beginTransaction();
        try {
            // Delete all messages in the thread
            $stmt = $this->db->prepare("DELETE FROM messages WHERE thread_id = ?");
            $stmt->execute([$threadId]);

            // Delete all thread participants
            $stmt = $this->db->prepare("DELETE FROM thread_participants WHERE thread_id = ?");
            $stmt->execute([$threadId]);

            // Delete the thread
            $stmt = $this->db->prepare("DELETE FROM message_threads WHERE id = ?");
            $stmt->execute([$threadId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // Toggle star on thread
    public function toggleStar($threadId, $userId)
    {
        $stmt = $this->db->prepare("SELECT is_starred FROM thread_participants WHERE thread_id = ? AND user_id = ?");
        $stmt->execute([$threadId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $isStarred = $result['is_starred'] ?? false;

        $newStarred = !$isStarred;
        $stmt = $this->db->prepare("UPDATE thread_participants SET is_starred = ? WHERE thread_id = ? AND user_id = ?");
        $stmt->execute([$newStarred, $threadId, $userId]);
        return $newStarred;
    }

    // Check if thread is starred
    public function isStarred($threadId, $userId)
    {
        $stmt = $this->db->prepare("SELECT is_starred FROM thread_participants WHERE thread_id = ? AND user_id = ?");
        $stmt->execute([$threadId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['is_starred'] ?? false;
    }

    // Get starred threads for user
    public function getStarredThreads($userId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                t.id,
                t.subject,
                t.created_at,
                (SELECT content FROM messages WHERE thread_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT COUNT(*) FROM messages m 
                 JOIN thread_participants tp ON m.thread_id = tp.thread_id 
                 WHERE tp.user_id = ? AND tp.thread_id = t.id 
                 AND (tp.last_read_at IS NULL OR m.created_at > tp.last_read_at)
                 AND m.sender_id != ?) as unread_count,
                (SELECT username FROM users u 
                 JOIN messages m ON m.sender_id = u.id 
                 WHERE m.thread_id = t.id 
                 ORDER BY m.created_at DESC LIMIT 1) as last_sender
            FROM message_threads t
            JOIN thread_participants p ON t.id = p.thread_id
            WHERE p.user_id = ? AND p.is_starred = TRUE
            ORDER BY (SELECT MAX(created_at) FROM messages WHERE thread_id = t.id) DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Mark thread as read
    public function markThreadAsRead($threadId, $userId)
    {
        $stmt = $this->db->prepare("UPDATE thread_participants SET last_read_at = NOW() WHERE thread_id = ? AND user_id = ?");
        $stmt->execute([$threadId, $userId]);
        return true;
    }

    // Mark thread as unread
    public function markThreadAsUnread($threadId, $userId)
    {
        $stmt = $this->db->prepare("UPDATE thread_participants SET last_read_at = NULL WHERE thread_id = ? AND user_id = ?");
        $stmt->execute([$threadId, $userId]);
        return true;
    }

    // Bulk mark as read
    public function bulkMarkAsRead($threadIds, $userId)
    {
        if (empty($threadIds)) return false;
        $placeholders = implode(',', array_fill(0, count($threadIds), '?'));
        $stmt = $this->db->prepare("UPDATE thread_participants SET last_read_at = NOW() WHERE thread_id IN ($placeholders) AND user_id = ?");
        $params = array_merge($threadIds, [$userId]);
        $stmt->execute($params);
        return true;
    }

    // Bulk mark as unread
    public function bulkMarkAsUnread($threadIds, $userId)
    {
        if (empty($threadIds)) return false;
        $placeholders = implode(',', array_fill(0, count($threadIds), '?'));
        $stmt = $this->db->prepare("UPDATE thread_participants SET last_read_at = NULL WHERE thread_id IN ($placeholders) AND user_id = ?");
        $params = array_merge($threadIds, [$userId]);
        $stmt->execute($params);
        return true;
    }

    // Bulk star threads
    public function bulkStar($threadIds, $userId, $starred = true)
    {
        if (empty($threadIds)) return false;
        $placeholders = implode(',', array_fill(0, count($threadIds), '?'));
        $stmt = $this->db->prepare("UPDATE thread_participants SET is_starred = ? WHERE thread_id IN ($placeholders) AND user_id = ?");
        $params = array_merge([$starred], $threadIds, [$userId]);
        $stmt->execute($params);
        return true;
    }
}
