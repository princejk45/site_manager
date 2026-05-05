<?php
class Hosting
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllHostingPlans()
    {
        $stmt = $this->pdo->query("SELECT * FROM hosting ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public function getHostingPlansWithServiceCounts()
    {
        $sql = "SELECT 
                h.id,
                h.name,
                h.status,
                h.expiry_date,
                COUNT(w.id) AS service_count
            FROM 
                hosting h
            LEFT JOIN websites w ON w.hosting_id = h.id
            GROUP BY
                h.id, h.name, h.status, h.expiry_date
            ORDER BY 
                h.name ASC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function getHostingPlanById(int $id)
    {
        $stmt = $this->pdo->prepare("
        SELECT h.* 
        FROM hosting h
        WHERE h.id = ?
    ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function createHostingPlan(array $data)
    {
        $stmt = $this->pdo->prepare("
        INSERT INTO hosting 
        (name, status, expiry_date, notes) 
        VALUES (?, ?, ?, ?)
    ");

        return $stmt->execute([
            $data['name'] ?? '',
            $data['status'] ?? 'active',
            $data['expiry_date'] ?? null,
            $data['notes'] ?? null
        ]);
    }

    public function updateHostingPlan(int $id, array $data)
    {
        $stmt = $this->pdo->prepare("
        UPDATE hosting SET 
        name = ?, 
        status = ?, 
        expiry_date = ?, 
        notes = ?
        WHERE id = ?
    ");
        return $stmt->execute([
            $data['name'] ?? '',
            $data['status'] ?? 'active',
            $data['expiry_date'] ?? null,
            $data['notes'] ?? null,
            $id
        ]);
    }

    public function getTotalHosting()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM hosting");
        return $stmt->fetchColumn();
    }


    public function getExpiringHostingCount($days = 30)
    {
        $stmt = $this->pdo->prepare("
        SELECT COUNT(*) 
        FROM hosting 
        WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ");
        $stmt->execute([$days]);
        return $stmt->fetchColumn();
    }

    public function deleteHostingPlan(int $id)
    {
        // Delete the hosting plan
        $stmt = $this->pdo->prepare("DELETE FROM hosting WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getExpiringHostingPlans($days = 60)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM hosting 
            WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    public function getExpiredHostingPlans(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM hosting 
            WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()
            ORDER BY expiry_date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getLiberiHostingServicesCount()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM websites WHERE hosting_id IS NULL");
        return (int)$stmt->fetchColumn();
    }


    public function getServicesByHostingId($hostingId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM websites WHERE hosting_id = ? ORDER BY domain ASC");
        $stmt->execute([(int)$hostingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
