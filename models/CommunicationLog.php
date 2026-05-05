<?php
/**
 * CommunicationLog — CRM communication history model
 * Manages client_communications + reads from email_logs for unified timeline
 */
class CommunicationLog
{
    private PDO $pdo;

    // Valid comm_type values (matches DB ENUM)
    const TYPES = [
        'invoice'          => 'Invoice',
        'domain_renewal'   => 'Domain Renewal',
        'hosting_renewal'  => 'Hosting Renewal',
        'email_hosting'    => 'Email Hosting',
        'email_space'      => 'Email Space',
        'website_changes'  => 'Website Changes',
        'health_report'    => 'Health Report',
        'maintenance'      => 'Maintenance',
        'general'          => 'General',
        'other'            => 'Other',
    ];

    // Valid channel values (matches DB ENUM)
    const CHANNELS = [
        'email'     => 'Email',
        'phone'     => 'Phone',
        'whatsapp'  => 'WhatsApp',
        'in_person' => 'In Person',
        'portal'    => 'Portal',
        'other'     => 'Other',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get unified communication timeline — manual entries + system email_logs
     * Returns paginated rows merged and sorted by sent_at DESC
     */
    public function getTimeline(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $conditions = ['1=1'];
        $params     = [];

        if (!empty($filters['hosting_id'])) {
            $conditions[] = 'cc.hosting_id = :hosting_id';
            $params[':hosting_id'] = (int)$filters['hosting_id'];
        }
        if (!empty($filters['comm_type'])) {
            $conditions[] = 'cc.comm_type = :comm_type';
            $params[':comm_type'] = $filters['comm_type'];
        }
        if (!empty($filters['channel'])) {
            $conditions[] = 'cc.channel = :channel';
            $params[':channel'] = $filters['channel'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'cc.sent_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'cc.sent_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(cc.subject LIKE :search OR cc.notes LIKE :search OR h.name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $where  = implode(' AND ', $conditions);
        $offset = ($page - 1) * $perPage;

        // Total count
        $countSql = "
            SELECT COUNT(*) FROM client_communications cc
            LEFT JOIN hosting h ON cc.hosting_id = h.id
            WHERE {$where}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Paginated rows
        $sql = "
            SELECT
                cc.*,
                h.name          AS client_name,
                w.domain        AS website_domain,
                u.username      AS sent_by_name
            FROM client_communications cc
            LEFT JOIN hosting  h ON cc.hosting_id = h.id
            LEFT JOIN websites w ON cc.website_id = w.id
            LEFT JOIN users    u ON cc.sent_by    = u.id
            WHERE {$where}
            ORDER BY cc.sent_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'last_page'  => (int)ceil($total / $perPage),
        ];
    }

    /**
     * Get all system email_logs merged into the timeline format
     * (optionally filtered by hosting_id via websites JOIN)
     */
    public function getSystemEmailLogs(int $hostingId = 0, int $limit = 50): array
    {
        $where = $hostingId ? 'AND w.hosting_id = :hosting_id' : '';
        $sql = "
            SELECT
                el.id,
                el.website_id,
                el.email_type       AS comm_type,
                el.sent_to,
                el.subject,
                el.body             AS notes,
                el.sent_at,
                el.status,
                'system'            AS source,
                'email'             AS channel,
                h.name              AS client_name,
                w.domain            AS website_domain
            FROM email_logs el
            LEFT JOIN websites w ON el.website_id = w.id
            LEFT JOIN hosting  h ON w.hosting_id  = h.id
            WHERE 1=1 {$where}
            ORDER BY el.sent_at DESC
            LIMIT :limit
        ";
        $stmt = $this->pdo->prepare($sql);
        if ($hostingId) {
            $stmt->bindValue(':hosting_id', $hostingId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single communication record
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT cc.*, h.name AS client_name, w.domain AS website_domain, u.username AS sent_by_name
            FROM client_communications cc
            LEFT JOIN hosting  h ON cc.hosting_id = h.id
            LEFT JOIN websites w ON cc.website_id = w.id
            LEFT JOIN users    u ON cc.sent_by    = u.id
            WHERE cc.id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Log a manual communication
     */
    public function log(array $data): int
    {
        $allowedTypes    = array_keys(self::TYPES);
        $allowedChannels = array_keys(self::CHANNELS);

        $commType = in_array($data['comm_type'] ?? '', $allowedTypes, true)
            ? $data['comm_type'] : 'general';
        $channel  = in_array($data['channel'] ?? '', $allowedChannels, true)
            ? $data['channel'] : 'email';

        $stmt = $this->pdo->prepare("
            INSERT INTO client_communications
                (hosting_id, website_id, comm_type, channel, subject, notes, sent_at, sent_by, source)
            VALUES
                (:hosting_id, :website_id, :comm_type, :channel, :subject, :notes, :sent_at, :sent_by, 'manual')
        ");
        $stmt->execute([
            ':hosting_id' => (int)$data['hosting_id'],
            ':website_id' => !empty($data['website_id']) ? (int)$data['website_id'] : null,
            ':comm_type'  => $commType,
            ':channel'    => $channel,
            ':subject'    => mb_substr(trim($data['subject']), 0, 255),
            ':notes'      => trim($data['notes'] ?? ''),
            ':sent_at'    => $data['sent_at'] ?? date('Y-m-d H:i:s'),
            ':sent_by'    => !empty($data['sent_by']) ? (int)$data['sent_by'] : null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Delete a manual communication entry
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM client_communications WHERE id = :id AND source = 'manual'");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get all clients (hosting) for the filter dropdown
     */
    public function getClients(): array
    {
        $stmt = $this->pdo->query("SELECT id, name FROM hosting ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get websites for a given client (for the log modal)
     */
    public function getWebsitesByClient(int $hostingId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, domain, service_type FROM websites WHERE hosting_id = :hid ORDER BY domain ASC
        ");
        $stmt->execute([':hid' => $hostingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Summary counts per client for the header KPI strip
     */
    public function getKpiSummary(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*)                                        AS total,
                SUM(MONTH(sent_at) = MONTH(CURDATE()) AND YEAR(sent_at) = YEAR(CURDATE())) AS month,
                SUM(comm_type = 'invoice')                      AS invoices,
                SUM(comm_type IN ('domain_renewal','hosting_renewal','email_hosting')) AS renewals,
                SUM(DATE(sent_at) = CURDATE())                  AS today
            FROM client_communications
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }
}
