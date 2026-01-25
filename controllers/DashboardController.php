<?php
class DashboardController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $websiteModel = new Website($this->pdo);
        $hostingModel = new Hosting($this->pdo);

        // Get all counts for the CTA boxes
        $totalWebsites = $websiteModel->getTotalWebsites();
        $expiringWebsitesCount = $websiteModel->getExpiringWebsitesCount(30); // Next 30 days
        $totalHosting = $hostingModel->getTotalHosting();
        $expiringHostingCount = $hostingModel->getExpiringHostingCount(30); // Next 30 days
        $buggyWebsitesCount = $websiteModel->getBuggyWebsitesCount();
        $expiredWebsitesCount = $websiteModel->getExpiredWebsitesCount();
        $liberiCount = $hostingModel->getLiberiHostingServicesCount();

        // Get detailed lists for tables
        $expiringWebsites = $websiteModel->getExpiringWebsites(30); // Next 30 days
        $expiringHosting = $hostingModel->getExpiringHostingPlans(30); // Next 30 days
        $buggyWebsites = $websiteModel->getBuggyWebsites();
        $expiredWebsites = $websiteModel->getExpiredWebsites();
        $hostingWithCounts = $hostingModel->getHostingPlansWithServiceCounts();

        require APP_PATH . '/views/dashboard/index.php';
    }
}
