<?php
/**
 * WhitelabelService
 * 
 * Manages white-labeling and custom branding for enterprise customers.
 * Supports custom logos, colors, domains, and email branding.
 */

class WhitelabelService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Get whitelabel settings for portfolio
     * 
     * @param int $portfolioId Portfolio ID
     * @return array Whitelabel configuration
     */
    public function getWhitelabelConfig($portfolioId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM whitelabel_settings 
                WHERE portfolio_id = ?
            ");
            $stmt->execute([$portfolioId]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$config) {
                return $this->getDefaultConfig($portfolioId);
            }
            
            // Decode JSON fields
            if ($config['colors']) {
                $config['colors'] = json_decode($config['colors'], true);
            }
            if ($config['social_links']) {
                $config['social_links'] = json_decode($config['social_links'], true);
            }
            
            return $config;
            
        } catch (PDOException $e) {
            error_log("WhitelabelService::getWhitelabelConfig - " . $e->getMessage());
            return $this->getDefaultConfig($portfolioId);
        }
    }
    
    /**
     * Get default configuration
     * 
     * @param int $portfolioId Portfolio ID
     * @return array Default config
     */
    private function getDefaultConfig($portfolioId) {
        return [
            'portfolio_id' => $portfolioId,
            'company_name' => 'Fullmidia',
            'logo_url' => null,
            'favicon_url' => null,
            'primary_color' => '#0066cc',
            'secondary_color' => '#f0f0f0',
            'colors' => [
                'primary' => '#0066cc',
                'secondary' => '#f0f0f0',
                'success' => '#28a745',
                'danger' => '#dc3545',
                'warning' => '#ffc107',
                'info' => '#17a2b8'
            ],
            'custom_domain' => null,
            'email_from_name' => 'Fullmidia',
            'email_footer_text' => 'Powered by Fullmidia',
            'custom_css' => null,
            'social_links' => [],
            'enabled' => false
        ];
    }
    
    /**
     * Update whitelabel settings
     * 
     * @param int $portfolioId Portfolio ID
     * @param array $settings New settings
     * @return bool Success
     */
    public function updateWhitelabelSettings($portfolioId, $settings) {
        try {
            // Check if record exists
            $stmt = $this->pdo->prepare("SELECT id FROM whitelabel_settings WHERE portfolio_id = ?");
            $stmt->execute([$portfolioId]);
            $exists = $stmt->fetch();
            
            // Prepare data
            $data = [
                'company_name' => $settings['company_name'] ?? 'Fullmidia',
                'primary_color' => $settings['primary_color'] ?? '#0066cc',
                'secondary_color' => $settings['secondary_color'] ?? '#f0f0f0',
                'colors' => json_encode($settings['colors'] ?? []),
                'custom_domain' => $settings['custom_domain'] ?? null,
                'email_from_name' => $settings['email_from_name'] ?? 'Fullmidia',
                'email_footer_text' => $settings['email_footer_text'] ?? 'Powered by Fullmidia',
                'custom_css' => $settings['custom_css'] ?? null,
                'social_links' => json_encode($settings['social_links'] ?? []),
                'enabled' => $settings['enabled'] ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($exists) {
                // Update existing
                $sql = "UPDATE whitelabel_settings SET ";
                $sets = [];
                $values = [];
                
                foreach ($data as $key => $value) {
                    $sets[] = "$key = ?";
                    $values[] = $value;
                }
                $values[] = $portfolioId;
                
                $sql .= implode(", ", $sets) . " WHERE portfolio_id = ?";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($values);
            } else {
                // Insert new
                $cols = array_keys($data);
                $cols[] = 'portfolio_id';
                $placeholders = array_fill(0, count($cols), '?');
                
                $sql = "INSERT INTO whitelabel_settings (" . implode(", ", $cols) . ") 
                        VALUES (" . implode(", ", $placeholders) . ")";
                
                $values = array_values($data);
                $values[] = $portfolioId;
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($values);
            }
            
            $this->auditTrail->log('whitelabel_updated', 'portfolio_id=' . $portfolioId);
            return true;
            
        } catch (PDOException $e) {
            error_log("WhitelabelService::updateWhitelabelSettings - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload logo
     * 
     * @param int $portfolioId Portfolio ID
     * @param array $file Uploaded file
     * @return string Logo URL or false on failure
     */
    public function uploadLogo($portfolioId, $file) {
        try {
            $uploadDir = __DIR__ . '/../../uploads/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Validate file
            $allowedTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Invalid file type');
            }
            
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception('File too large (max 2MB)');
            }
            
            // Generate filename
            $filename = 'logo_' . $portfolioId . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $filepath = $uploadDir . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to save file');
            }
            
            // Update database
            $stmt = $this->pdo->prepare("
                UPDATE whitelabel_settings 
                SET logo_url = ? 
                WHERE portfolio_id = ?
            ");
            $stmt->execute(['/uploads/logos/' . $filename, $portfolioId]);
            
            $this->auditTrail->log('logo_uploaded', 'portfolio_id=' . $portfolioId);
            return '/uploads/logos/' . $filename;
            
        } catch (Exception $e) {
            error_log("WhitelabelService::uploadLogo - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate custom CSS
     * 
     * @param int $portfolioId Portfolio ID
     * @param array $branding Branding configuration
     * @return string Generated CSS
     */
    public function generateCustomCss($portfolioId, $branding) {
        $css = ":root {\n";
        
        if (isset($branding['colors'])) {
            $css .= "  --primary-color: " . $branding['colors']['primary'] . ";\n";
            $css .= "  --secondary-color: " . $branding['colors']['secondary'] . ";\n";
            $css .= "  --success-color: " . $branding['colors']['success'] . ";\n";
            $css .= "  --danger-color: " . $branding['colors']['danger'] . ";\n";
            $css .= "  --warning-color: " . $branding['colors']['warning'] . ";\n";
            $css .= "  --info-color: " . $branding['colors']['info'] . ";\n";
        }
        
        $css .= "}\n\n";
        
        // Primary color overrides
        if (isset($branding['colors']['primary'])) {
            $primary = $branding['colors']['primary'];
            $css .= ".btn-primary, .btn-primary:hover { background-color: $primary; border-color: $primary; }\n";
            $css .= ".nav-link.active { color: $primary; }\n";
            $css .= ".sidebar-dark .nav-sidebar > .nav-item > .nav-link.active { background-color: $primary; }\n";
            $css .= ".card-header { background-color: $primary; }\n";
        }
        
        // Custom CSS append
        if (isset($branding['custom_css'])) {
            $css .= "\n/* Custom CSS */\n" . $branding['custom_css'];
        }
        
        return $css;
    }
    
    /**
     * Get whitelabel theme variables
     * 
     * @param int $portfolioId Portfolio ID
     * @return array Theme variables for frontend
     */
    public function getThemeVariables($portfolioId) {
        $config = $this->getWhitelabelConfig($portfolioId);
        
        return [
            'company_name' => $config['company_name'],
            'logo_url' => $config['logo_url'],
            'favicon_url' => $config['favicon_url'],
            'primary_color' => $config['primary_color'],
            'secondary_color' => $config['secondary_color'],
            'email_from_name' => $config['email_from_name'],
            'email_footer_text' => $config['email_footer_text'],
            'custom_domain' => $config['custom_domain'],
            'colors' => $config['colors'],
            'social_links' => $config['social_links'],
            'enabled' => $config['enabled']
        ];
    }
    
    /**
     * Validate custom domain
     * 
     * @param string $domain Domain to validate
     * @return bool Valid domain
     */
    public function validateCustomDomain($domain) {
        if (!$domain) return true; // Optional field
        
        // Check valid domain format
        if (!preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $domain)) {
            return false;
        }
        
        // Check not already in use
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM whitelabel_settings 
            WHERE custom_domain = ? AND portfolio_id != ?
        ");
        $stmt->execute([$domain, 0]); // Assuming 0 for new
        
        return $stmt->fetch()['count'] == 0;
    }
}
?>
