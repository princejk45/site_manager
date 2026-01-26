<?php

class SiteSettings
{
    private $db;
    private static $cache = [];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get a single setting by key
     */
    public function getSetting($key, $default = null)
    {
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $stmt = $this->db->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                self::$cache[$key] = $result['setting_value'];
                return $result['setting_value'];
            }

            return $default;
        } catch (Exception $e) {
            error_log('Error getting setting: ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Get multiple settings
     */
    public function getMultiple($keys = [])
    {
        try {
            if (empty($keys)) {
                $stmt = $this->db->prepare('SELECT setting_key, setting_value FROM site_settings');
                $stmt->execute();
            } else {
                $placeholders = implode(',', array_fill(0, count($keys), '?'));
                $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($placeholders)");
                $stmt->execute($keys);
            }

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[$row['setting_key']] = $row['setting_value'];
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }

            return $results;
        } catch (Exception $e) {
            error_log('Error getting multiple settings: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all settings
     */
    public function getAllSettings()
    {
        return $this->getMultiple();
    }

    /**
     * Update a setting
     */
    public function updateSetting($key, $value, $description = null)
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO site_settings (setting_key, setting_value, description, updated_at) 
                 VALUES (?, ?, ?, NOW()) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), 
                 description = IF(VALUES(description) IS NOT NULL, VALUES(description), description),
                 updated_at = NOW()'
            );

            $result = $stmt->execute([$key, $value, $description]);

            if ($result) {
                // Clear cache for this key
                unset(self::$cache[$key]);
            }

            return $result;
        } catch (Exception $e) {
            error_log('Error updating setting: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a setting
     */
    public function deleteSetting($key)
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM site_settings WHERE setting_key = ?');
            $result = $stmt->execute([$key]);

            if ($result) {
                unset(self::$cache[$key]);
            }

            return $result;
        } catch (Exception $e) {
            error_log('Error deleting setting: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear the cache
     */
    public static function clearCache()
    {
        self::$cache = [];
    }
}
