<?php
class Hosting
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllHostingPlans()
    {
        $stmt = $this->pdo->query("SELECT * FROM hosting_plans ORDER BY server_name ASC");
        return $stmt->fetchAll();
    }

    public function getHostingPlansWithServiceCounts()
    {
        $sql = "SELECT 
                h.id,
                h.server_name,
                h.provider,
                h.email_address,
                COUNT(w.id) AS service_count
            FROM 
                hosting_plans h
            LEFT JOIN 
                websites w ON h.id = w.hosting_id
            GROUP BY 
                h.id, h.server_name, h.provider, h.email_address
            ORDER BY 
                h.server_name ASC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function getHostingPlanById($id)
    {
        $stmt = $this->pdo->prepare("
        SELECT h.*, 
               (SELECT COUNT(*) FROM websites WHERE hosting_id = h.id) as service_count
        FROM hosting_plans h
        WHERE h.id = ?
    ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function createHostingPlan($data)
    {
        // Keep email format validation but remove duplicate check
        if (!filter_var($data['email_address'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Formato email non valido");
        }

        $stmt = $this->pdo->prepare("
        INSERT INTO hosting_plans 
        (server_name, provider, email_address, ip_address) 
        VALUES (?, ?, ?, ?)
    ");

        return $stmt->execute([
            $data['server_name'],
            $data['provider'] ?? null,
            $data['email_address'],
            $data['ip_address'] ?? null
        ]);
    }

    public function updateHostingPlan($id, $data)
    {
        $stmt = $this->pdo->prepare("
        UPDATE hosting_plans SET 
        server_name = ?, 
        provider = ?, 
        email_address = ?, 
        ip_address = ?
        WHERE id = ?
    ");
        return $stmt->execute([
            $data['server_name'],
            $data['provider'] ?? null,
            $data['email_address'],
            $data['ip_address'] ?? null,
            $id
        ]);
    }

    public function getTotalHosting()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM hosting_plans");
        return $stmt->fetchColumn();
    }


    public function getExpiringHostingCount($days = 30)
    {
        $stmt = $this->pdo->prepare("
        SELECT COUNT(*) 
        FROM hosting_plans 
        WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ");
        $stmt->execute([$days]);
        return $stmt->fetchColumn();
    }

    public function deleteHostingPlan($id)
    {
        // First, set hosting_id to NULL for all websites using this plan
        $stmt = $this->pdo->prepare("UPDATE websites SET hosting_id = NULL WHERE hosting_id = ?");
        $stmt->execute([$id]);

        // Then delete the hosting plan
        $stmt = $this->pdo->prepare("DELETE FROM hosting_plans WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getExpiringHostingPlans($days = 60)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM hosting_plans 
            WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    public function getLiberiHostingServicesCount()
    {
        $stmt = $this->pdo->prepare("
        SELECT COUNT(w.id) 
        FROM websites w
        JOIN hosting_plans h ON w.hosting_id = h.id
        WHERE h.server_name LIKE '%~%'
    ");
        $stmt->execute();
        return $stmt->fetchColumn();
    }


    public function getServicesByHostingId($hostingId)
    {
        $stmt = $this->pdo->prepare("
        SELECT * FROM websites 
        WHERE hosting_id = ? 
        ORDER BY domain ASC
    ");
        $stmt->execute([$hostingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
