<?php
/**
 * Email Template Service
 * 
 * Manages email templates with variable support, preview generation,
 * and visual template builder integration.
 */

class EmailTemplateService {
    
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // Template types
    const TYPE_NOTIFICATION = 'notification';
    const TYPE_ALERT = 'alert';
    const TYPE_REPORT = 'report';
    const TYPE_WELCOME = 'welcome';
    const TYPE_CUSTOM = 'custom';
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Create email template
     * 
     * @param int $portfolioId Portfolio ID
     * @param array $template Template data
     * @return int Template ID
     */
    public function createTemplate($portfolioId, $template) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO email_templates (
                    portfolio_id,
                    name,
                    slug,
                    type,
                    subject,
                    body_html,
                    body_text,
                    variables,
                    header_template,
                    footer_template,
                    is_active,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $slug = $template['slug'] ?? $this->generateSlug($template['name']);
            
            $stmt->execute([
                $portfolioId,
                $template['name'],
                $slug,
                $template['type'] ?? self::TYPE_CUSTOM,
                $template['subject'],
                $template['body_html'],
                $template['body_text'] ?? null,
                json_encode($template['variables'] ?? []),
                $template['header_template'] ?? null,
                $template['footer_template'] ?? null,
                $template['is_active'] ? 1 : 0,
                $this->userId
            ]);
            
            $templateId = $this->pdo->lastInsertId();
            $this->auditTrail->log('email_template_created', 'portfolio_id=' . $portfolioId . ';template_id=' . $templateId);
            
            return $templateId;
            
        } catch (PDOException $e) {
            error_log("EmailTemplateService::createTemplate - " . $e->getMessage());
            throw new Exception("Failed to create template");
        }
    }
    
    /**
     * Get templates for portfolio
     * 
     * @param int $portfolioId Portfolio ID
     * @param string $type Filter by type
     * @return array List of templates
     */
    public function getTemplates($portfolioId, $type = null) {
        try {
            $sql = "SELECT * FROM email_templates WHERE portfolio_id = ?";
            $params = [$portfolioId];
            
            if ($type) {
                $sql .= " AND type = ?";
                $params[] = $type;
            }
            
            $sql .= " ORDER BY name";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($templates as &$template) {
                $template['variables'] = json_decode($template['variables'], true);
            }
            
            return $templates;
            
        } catch (PDOException $e) {
            error_log("EmailTemplateService::getTemplates - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Render template with variables
     * 
     * @param int $templateId Template ID
     * @param array $data Variables
     * @return array Rendered email {subject, html, text}
     */
    public function renderTemplate($templateId, $data = []) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                throw new Exception("Template not found");
            }
            
            $variables = json_decode($template['variables'], true) ?? [];
            
            // Build header/footer
            $header = $template['header_template'] ? $this->renderString($template['header_template'], $data) : '';
            $footer = $template['footer_template'] ? $this->renderString($template['footer_template'], $data) : '';
            
            return [
                'subject' => $this->renderString($template['subject'], $data),
                'html' => $header . $this->renderString($template['body_html'], $data) . $footer,
                'text' => $this->renderString($template['body_text'] ?? '', $data),
                'variables_used' => $this->extractUsedVariables($template['subject'], $template['body_html']),
                'variables_available' => array_keys($variables)
            ];
            
        } catch (Exception $e) {
            error_log("EmailTemplateService::renderTemplate - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Preview template
     * 
     * @param int $templateId Template ID
     * @param array $sampleData Sample variable data
     * @return array Preview with rendered content
     */
    public function previewTemplate($templateId, $sampleData = []) {
        try {
            $rendered = $this->renderTemplate($templateId, $sampleData);
            
            return [
                'preview' => true,
                'subject' => $rendered['subject'],
                'html' => $rendered['html'],
                'text' => $rendered['text'],
                'sample_data_used' => array_keys($sampleData)
            ];
            
        } catch (Exception $e) {
            error_log("EmailTemplateService::previewTemplate - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update template
     * 
     * @param int $templateId Template ID
     * @param array $updates Updated fields
     * @return bool Success
     */
    public function updateTemplate($templateId, $updates) {
        try {
            $sets = [];
            $values = [];
            
            $allowedFields = ['name', 'subject', 'body_html', 'body_text', 'variables', 'is_active'];
            
            foreach ($updates as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $sets[] = "$field = ?";
                    $values[] = ($field === 'variables') ? json_encode($value) : $value;
                }
            }
            
            if (empty($sets)) {
                return true;
            }
            
            $values[] = $templateId;
            
            $sql = "UPDATE email_templates SET " . implode(", ", $sets) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            
            $this->auditTrail->log('email_template_updated', 'template_id=' . $templateId);
            return true;
            
        } catch (PDOException $e) {
            error_log("EmailTemplateService::updateTemplate - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete template
     */
    public function deleteTemplate($templateId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM email_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            
            $this->auditTrail->log('email_template_deleted', 'template_id=' . $templateId);
            return true;
            
        } catch (PDOException $e) {
            error_log("EmailTemplateService::deleteTemplate - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Render string with variable substitution
     * 
     * @param string $template Template string with {{variable}} syntax
     * @param array $data Variable data
     * @return string Rendered string
     */
    private function renderString($template, $data) {
        if (!$template) {
            return '';
        }
        
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function($matches) use ($data) {
            $key = trim($matches[1]);
            return isset($data[$key]) ? $data[$key] : $matches[0];
        }, $template);
    }
    
    /**
     * Extract used variables from template
     */
    private function extractUsedVariables($subject, $body) {
        $matches = [];
        $text = $subject . ' ' . $body;
        
        preg_match_all('/\{\{([^}]+)\}\}/', $text, $matches);
        
        return array_unique($matches[1] ?? []);
    }
    
    /**
     * Generate URL-safe slug
     */
    private function generateSlug($name) {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($name)));
    }
    
    /**
     * Get template builder configuration
     * 
     * @param int $templateId Template ID
     * @return array Builder configuration
     */
    public function getBuilderConfig($templateId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                throw new Exception("Template not found");
            }
            
            return [
                'id' => $templateId,
                'name' => $template['name'],
                'subject' => $template['subject'],
                'body_html' => $template['body_html'],
                'body_text' => $template['body_text'],
                'variables' => json_decode($template['variables'], true),
                'header_template' => $template['header_template'],
                'footer_template' => $template['footer_template'],
                'editor_type' => $this->detectEditorType($template['body_html']),
                'available_variables' => $this->getAvailableVariables($template['portfolio_id'])
            ];
            
        } catch (Exception $e) {
            error_log("EmailTemplateService::getBuilderConfig - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Detect editor type (html, markdown, plaintext)
     */
    private function detectEditorType($content) {
        if (strpos($content, '<') !== false && strpos($content, '>') !== false) {
            return 'html';
        } elseif (preg_match('/[*_`#-]+/', $content)) {
            return 'markdown';
        } else {
            return 'plaintext';
        }
    }
    
    /**
     * Get available variables for portfolio
     */
    private function getAvailableVariables($portfolioId) {
        return [
            'portfolio' => ['name', 'id', 'email', 'domain'],
            'user' => ['name', 'email', 'first_name', 'last_name'],
            'website' => ['domain', 'status', 'health_score'],
            'alert' => ['severity', 'title', 'description', 'timestamp'],
            'custom' => ['company', 'support_email', 'support_phone']
        ];
    }
    
    /**
     * Clone template
     */
    public function cloneTemplate($templateId, $newName) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$original) {
                throw new Exception("Template not found");
            }
            
            unset($original['id']);
            $original['name'] = $newName;
            
            return $this->createTemplate($original['portfolio_id'], $original);
            
        } catch (Exception $e) {
            error_log("EmailTemplateService::cloneTemplate - " . $e->getMessage());
            throw $e;
        }
    }
}
?>
