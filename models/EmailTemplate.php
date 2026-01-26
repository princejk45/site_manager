<?php

class EmailTemplate
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get template by slug
     */
    public function getBySlug($slug)
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM email_templates WHERE slug = ? AND status = "active"');
            $stmt->execute([$slug]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error getting template by slug: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get template by ID
     */
    public function getById($id)
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM email_templates WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error getting template by ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all templates
     */
    public function getAll($activeOnly = true)
    {
        try {
            if ($activeOnly) {
                $stmt = $this->db->prepare('SELECT * FROM email_templates WHERE status = "active" ORDER BY name ASC');
            } else {
                $stmt = $this->db->prepare('SELECT * FROM email_templates ORDER BY name ASC');
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error getting all templates: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a new template
     */
    public function create($data)
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO email_templates (name, slug, subject, body, description, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );

            return $stmt->execute([
                $data['name'] ?? '',
                $data['slug'] ?? '',
                $data['subject'] ?? '',
                $data['body'] ?? '',
                $data['description'] ?? '',
                $data['status'] ?? 'active'
            ]);
        } catch (Exception $e) {
            error_log('Error creating template: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update a template
     */
    public function update($id, $data)
    {
        try {
            $updates = [];
            $values = [];

            foreach (['name', 'subject', 'body', 'description', 'status'] as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return false;
            }

            $updates[] = 'updated_at = NOW()';
            $values[] = $id;

            $query = 'UPDATE email_templates SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $stmt = $this->db->prepare($query);

            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log('Error updating template: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a template
     */
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM email_templates WHERE id = ?');
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log('Error deleting template: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get template with variable substitution preview
     * Uses global header/footer from site settings
     */
    public function renderTemplate($slug, $variables = [])
    {
        $template = $this->getBySlug($slug);

        if (!$template) {
            return null;
        }

        $subject = $template['subject'];
        $body = $template['body'];

        // Get global header and footer from site settings
        require_once APP_PATH . '/models/SiteSettings.php';
        $siteSettings = new SiteSettings($this->db);
        $globalHeader = $siteSettings->getSetting('email_global_header', '');
        $globalFooter = $siteSettings->getSetting('email_global_footer', '');

        // Prefer per-template header/footer if present, otherwise fall back to global
        $templateHeader = $template['header'] ?? '';
        $templateFooter = $template['footer'] ?? '';
        $header = trim($templateHeader) !== '' ? $templateHeader : $globalHeader;
        $footer = trim($templateFooter) !== '' ? $templateFooter : $globalFooter;

        // Replace variables in all parts (subject, header, body, footer)
        foreach ($variables as $key => $value) {
            $placeholder = '{' . $key . '}';
            $subject = str_replace($placeholder, $value, $subject);
            $header = str_replace($placeholder, $value, $header);
            $body = str_replace($placeholder, $value, $body);
            $footer = str_replace($placeholder, $value, $footer);
        }

        return [
            'subject' => $subject,
            'body' => $body,
            'header' => $header,
            'footer' => $footer,
            'html' => $header . $body . $footer,  // Combined HTML for email body
            'original' => $template
        ];
    }

    /**
     * Duplicate a template
     */
    public function duplicate($id, $newName = null)
    {
        try {
            $original = $this->getById($id);

            if (!$original) {
                return false;
            }

            $newSlug = $original['slug'] . '_copy_' . time();
            $newName = $newName ?? $original['name'] . ' (Copy)';

            return $this->create([
                'name' => $newName,
                'slug' => $newSlug,
                'subject' => $original['subject'],
                'body' => $original['body'],
                'description' => $original['description'],
                'status' => $original['status']
            ]);
        } catch (Exception $e) {
            error_log('Error duplicating template: ' . $e->getMessage());
            return false;
        }
    }
}
