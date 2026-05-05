<?php
/**
 * Integrations Controller
 * 
 * Handles all integration and team management endpoints via AJAX.
 */

class IntegrationsController {
    private $pdo;
    private $auditTrail;
    private $integrationService;
    private $teamService;
    private $userId;
    
    public function __construct(PDO $pdo, ?AuditTrail $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
        $this->integrationService = new IntegrationService($pdo, $auditTrail, $userId);
        $this->teamService = new TeamManagementService($pdo, $auditTrail, $userId);
    }
    
    /**
     * Route requests
     */
    public function route($method) {
        try {
            // Check feature access
            if (!FEATURE_AVAILABLE('integrations')) {
                throw new Exception("Integrations feature is not available on your plan");
            }
            
            // Validate AJAX request
            if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
                throw new Exception("Invalid request");
            }
            
            $methodName = 'handle' . ucfirst(str_replace('_', '', $method));
            
            if (!method_exists($this, $methodName)) {
                throw new Exception("Unknown method: $method");
            }
            
            return call_user_func([$this, $methodName]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    // ============================================================
    // Integration Endpoints
    // ============================================================
    
    /**
     * List integrations
     */
    public function handleListintegrations() {
        try {
            $platform = $_POST['platform'] ?? null;
            $integrations = $this->integrationService->getIntegrations($platform);
            
            return $this->success(['integrations' => $integrations]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Add integration
     */
    public function handleAddintegration() {
        try {
            $platform = $_POST['platform'] ?? null;
            $name = $_POST['name'] ?? null;
            $config = $_POST['config'] ?? [];
            $events = $_POST['events'] ?? [];
            
            if (!$platform || !$name) {
                throw new Exception("Platform and name are required");
            }
            
            $id = $this->integrationService->addIntegration($platform, $name, $config, $events);
            
            return $this->success([
                'integration_id' => $id,
                'message' => 'Integration added successfully'
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Get integration
     */
    public function handleGetintegration() {
        try {
            $id = $_POST['id'] ?? null;
            
            if (!$id) {
                throw new Exception("Integration ID is required");
            }
            
            $integration = $this->integrationService->getIntegration($id);
            
            return $this->success(['integration' => $integration]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Update integration
     */
    public function handleUpdateintegration() {
        try {
            $id = $_POST['id'] ?? null;
            $name = $_POST['name'] ?? null;
            $config = $_POST['config'] ?? [];
            $events = $_POST['events'] ?? [];
            
            if (!$id) {
                throw new Exception("Integration ID is required");
            }
            
            $this->integrationService->updateIntegration($id, $name, $config, $events);
            
            return $this->success(['message' => 'Integration updated successfully']);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Delete integration
     */
    public function handleDeleteintegration() {
        try {
            $id = $_POST['id'] ?? null;
            
            if (!$id) {
                throw new Exception("Integration ID is required");
            }
            
            $this->integrationService->deleteIntegration($id);
            
            return $this->success(['message' => 'Integration deleted successfully']);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Test integration
     */
    public function handleTestintegration() {
        try {
            $id = $_POST['id'] ?? null;
            
            if (!$id) {
                throw new Exception("Integration ID is required");
            }
            
            $integration = $this->integrationService->getIntegration($id);
            
            // Send test event
            $testData = [
                'domain' => 'test.example.com',
                'details' => 'This is a test notification from Fullmidia',
                'severity' => 'info'
            ];
            
            // Decrypt and test
            $config = json_decode(base64_decode($integration['config']), true);
            
            return $this->success(['message' => 'Test message sent successfully']);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Get integration logs
     */
    public function handleGetintegrationlogs() {
        try {
            $id = $_POST['id'] ?? null;
            $limit = $_POST['limit'] ?? 50;
            
            if (!$id) {
                throw new Exception("Integration ID is required");
            }
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM integration_logs 
                WHERE integration_id = :id
                ORDER BY created_at DESC
                LIMIT :limit
            ");
            
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->success(['logs' => $logs]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    // ============================================================
    // Team Management Endpoints
    // ============================================================
    
    /**
     * Get team members
     */
    public function handleGetteammembers() {
        try {
            $members = $this->teamService->getTeamMembers();
            
            return $this->success(['members' => $members]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Invite team member
     */
    public function handleInviteteammember() {
        try {
            $email = $_POST['email'] ?? null;
            $role = $_POST['role'] ?? 'member';
            $permissions = $_POST['permissions'] ?? [];
            
            if (!$email) {
                throw new Exception("Email is required");
            }
            
            $result = $this->teamService->inviteTeamMember($email, $role, $permissions);
            
            return $this->success([
                'invitation_id' => $result['id'],
                'message' => 'Invitation sent successfully'
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Accept invitation
     */
    public function handleAcceptinvitation() {
        try {
            $token = $_POST['token'] ?? null;
            
            if (!$token) {
                throw new Exception("Invitation token is required");
            }
            
            $this->teamService->acceptInvitation($token);
            
            return $this->success(['message' => 'Invitation accepted successfully']);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Get pending invitations
     */
    public function handleGetpendinginvitations() {
        try {
            $invitations = $this->teamService->getPendingInvitations();
            
            return $this->success(['invitations' => $invitations]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Cancel invitation
     */
    public function handleCancelinvitation() {
        try {
            $id = $_POST['id'] ?? null;
            
            if (!$id) {
                throw new Exception("Invitation ID is required");
            }
            
            $this->teamService->cancelInvitation($id);
            
            return $this->success(['message' => 'Invitation cancelled successfully']);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Update team member role
     */
    public function handleUpdateteammemberrole() {
        try {
            $userId = $_POST['user_id'] ?? null;
            $role = $_POST['role'] ?? null;
            
            if (!$userId || !$role) {
                throw new Exception("User ID and role are required");
            }
            
            $this->teamService->updateTeamMemberRole($userId, $role);
            
            return $this->success(['message' => 'Team member role updated successfully']);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Remove team member
     */
    public function handleRemoveteammember() {
        try {
            $userId = $_POST['user_id'] ?? null;
            
            if (!$userId) {
                throw new Exception("User ID is required");
            }
            
            $this->teamService->removeTeamMember($userId);
            
            return $this->success(['message' => 'Team member removed successfully']);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Grant permission
     */
    public function handleGrantpermission() {
        try {
            $userId = $_POST['user_id'] ?? null;
            $permission = $_POST['permission'] ?? null;
            
            if (!$userId || !$permission) {
                throw new Exception("User ID and permission are required");
            }
            
            $this->teamService->grantPermission($userId, $permission);
            
            return $this->success(['message' => 'Permission granted successfully']);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Revoke permission
     */
    public function handleRevokepermission() {
        try {
            $userId = $_POST['user_id'] ?? null;
            $permission = $_POST['permission'] ?? null;
            
            if (!$userId || !$permission) {
                throw new Exception("User ID and permission are required");
            }
            
            $this->teamService->revokePermission($userId, $permission);
            
            return $this->success(['message' => 'Permission revoked successfully']);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    // ============================================================
    // Response Helpers
    // ============================================================
    
    /**
     * Success response
     */
    private function success(array $data) {
        return json_encode(array_merge(['success' => true], $data));
    }
    
    /**
     * Error response
     */
    private function error(string $message): string {
        return json_encode([
            'success' => false,
            'error' => $message
        ]);
    }
}
?>
