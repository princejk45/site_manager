<?php
class SettingsModel
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getGoogleSheetsSettings()
    {
        $stmt = $this->pdo->query("SELECT * FROM google_sheets_settings LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'sheet_id' => '',
            'sheet_name' => 'Sheet1',
            'credentials' => '',
            'enabled' => 0
        ];
    }

    public function saveGoogleSheetsSettings($data)
    {
        // Check if settings already exist
        $existing = $this->getGoogleSheetsSettings();

        if (isset($existing['id'])) {
            // Update existing settings
            $stmt = $this->pdo->prepare("
                UPDATE google_sheets_settings 
                SET sheet_id = ?, sheet_name = ?, credentials = ?, enabled = ?
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['sheet_id'],
                $data['sheet_name'],
                $data['credentials'],
                $data['enabled'],
                $existing['id']
            ]);
        } else {
            // Insert new settings
            $stmt = $this->pdo->prepare("
                INSERT INTO google_sheets_settings 
                (sheet_id, sheet_name, credentials, enabled)
                VALUES (?, ?, ?, ?)
            ");
            return $stmt->execute([
                $data['sheet_id'],
                $data['sheet_name'],
                $data['credentials'],
                $data['enabled']
            ]);
        }
    }
}
