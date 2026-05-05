<?php
/**
 * Team Management Service
 * 
 * Manages team members, roles, permissions, and access control.
 * Supports multi-user portfolios with granular permission system.
 */

class TeamManagementService {
    private $pdo;
    private $auditTrail;
    private $userId;
    
    // User roles
    const ROLE_OWNER = 'owner';
    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_MEMBER = 'member';
    const ROLE_VIEWER = 'viewer';
    
    // Permission levels
    const PERM_READ = 'read';
    const PERM_WRITE = 'write';
    const PERM_DELETE = 'delete';
    const PERM_ADMIN = 'admin';
    
    // Invitation statuses
    const INVITE_PENDING = 'pending';
    const INVITE_ACCEPTED = 'accepted';
    const INVITE_DECLINED = 'declined';
    
    public function __construct(PDO $pdo, ?AuditTrail $auditTrail, int $userId) {
        $this->pdo = $pdo;
        $this->auditTrail = $auditTrail;
        $this->userId = $userId;
    }
    
    /**
     * Get user role
     */
    public function getUserRole($userId = null) {
        $userId = $userId ?? $this->userId;
        
        try {
            // Check if user is portfolio owner
            $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['role'] === self::ROLE_OWNER) {
                return self::ROLE_OWNER;
            }
            
            // Check team membership
            $stmt = $this->pdo->prepare("
                SELECT role FROM team_members 
                WHERE user_id = :user_id AND portfolio_id = :portfolio_id
                LIMIT 1
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':portfolio_id' => $this->getPortfolioId()
            ]);
            
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            return $member['role'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Check permission
     */
    public function hasPermission($permission) {
        $role = $this->getUserRole();
        
        $permissions = [
            self::ROLE_OWNER => [self::PERM_READ, self::PERM_WRITE, self::PERM_DELETE, self::PERM_ADMIN],
            self::ROLE_ADMIN => [self::PERM_READ, self::PERM_WRITE, self::PERM_DELETE, self::PERM_ADMIN],
            self::ROLE_MANAGER => [self::PERM_READ, self::PERM_WRITE, self::PERM_DELETE],
            self::ROLE_MEMBER => [self::PERM_READ, self::PERM_WRITE],
            self::ROLE_VIEWER => [self::PERM_READ]
        ];
        
        return isset($permissions[$role]) && in_array($permission, $permissions[$role]);
    }
    
    /**
     * Invite team member
     */
    public function inviteTeamMember($email, $role, $permissions = []) {
        try {
            if (!$this->hasPermission(self::PERM_ADMIN)) {
                throw new Exception("You don't have permission to invite team members");
            }
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address");
            }
            
            // Check if email already invited
            $stmt = $this->pdo->prepare("
                SELECT id FROM team_invitations 
                WHERE email = :email AND portfolio_id = :portfolio_id AND status = :status
            ");
            
            $stmt->execute([
                ':email' => $email,
                ':portfolio_id' => $this->getPortfolioId(),
                ':status' => self::INVITE_PENDING
            ]);
            
            if ($stmt->fetch()) {
                throw new Exception("Invitation already sent to this email");
            }
            
            // Generate invitation token
            $token = bin2hex(random_bytes(32));
            
            // Create invitation
            $stmt = $this->pdo->prepare("
                INSERT INTO team_invitations (portfolio_id, email, role, permissions, token, status, created_at, expires_at)
                VALUES (:portfolio_id, :email, :role, :permissions, :token, :status, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))
            ");
            
            $result = $stmt->execute([
                ':portfolio_id' => $this->getPortfolioId(),
                ':email' => $email,
                ':role' => $role,
                ':permissions' => json_encode($permissions),
                ':token' => $token,
                ':status' => self::INVITE_PENDING
            ]);
            
            if (!$result) {
                throw new Exception("Failed to create invitation");
            }
            
            $invitationId = $this->pdo->lastInsertId();
            
            // Log invitation
            $this->auditTrail->log(
                $this->userId,
                'team_invitation_sent',
                'team_invitations',
                $invitationId,
                [],
                json_encode(['email' => $email, 'role' => $role])
            );
            
            return [
                'id' => $invitationId,
                'token' => $token,
                'email' => $email
            ];
        } catch (Exception $e) {
            throw new Exception("Error inviting team member: " . $e->getMessage());
        }
    }
    
    /**
     * Accept invitation
     */
    public function acceptInvitation($token) {
        try {
            // Verify token exists and not expired
            $stmt = $this->pdo->prepare("
                SELECT * FROM team_invitations 
                WHERE token = :token AND status = :status AND expires_at > NOW()
            ");
            
            $stmt->execute([
                ':token' => $token,
                ':status' => self::INVITE_PENDING
            ]);
            
            $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invitation) {
                throw new Exception("Invalid or expired invitation");
            }
            
            // Add user to team
            $stmt = $this->pdo->prepare("
                INSERT INTO team_members (portfolio_id, user_id, role, permissions, accepted_at)
                VALUES (:portfolio_id, :user_id, :role, :permissions, NOW())
                ON DUPLICATE KEY UPDATE role = :role, permissions = :permissions, accepted_at = NOW()
            ");
            
            $permissions = $invitation['permissions'];
            
            $result = $stmt->execute([
                ':portfolio_id' => $invitation['portfolio_id'],
                ':user_id' => $this->userId,
                ':role' => $invitation['role'],
                ':permissions' => $permissions
            ]);
            
            if (!$result) {
                throw new Exception("Failed to add team member");
            }
            
            // Mark invitation as accepted
            $stmt = $this->pdo->prepare("
                UPDATE team_invitations 
                SET status = :status, accepted_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':status' => self::INVITE_ACCEPTED,
                ':id' => $invitation['id']
            ]);
            
            // Log
            $this->auditTrail->log(
                $this->userId,
                'team_invitation_accepted',
                'team_invitations',
                $invitation['id'],
                [],
                json_encode(['email' => $invitation['email']])
            );
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Error accepting invitation: " . $e->getMessage());
        }
    }
    
    /**
     * Get team members
     */
    public function getTeamMembers() {
        try {
            if (!$this->hasPermission(self::PERM_READ)) {
                throw new Exception("You don't have permission to view team members");
            }
            
            $stmt = $this->pdo->prepare("
                SELECT tm.*, u.email, u.first_name, u.last_name 
                FROM team_members tm
                LEFT JOIN users u ON tm.user_id = u.id
                WHERE tm.portfolio_id = :portfolio_id
                ORDER BY tm.accepted_at DESC
            ");
            
            $stmt->execute([':portfolio_id' => $this->getPortfolioId()]);
            
            $members = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['permissions'] = json_decode($row['permissions'], true) ?: [];
                $members[] = $row;
            }
            
            return $members;
        } catch (Exception $e) {
            throw new Exception("Error retrieving team members: " . $e->getMessage());
        }
    }
    
    /**
     * Get pending invitations
     */
    public function getPendingInvitations() {
        try {
            if (!$this->hasPermission(self::PERM_ADMIN)) {
                throw new Exception("You don't have permission to view invitations");
            }
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM team_invitations 
                WHERE portfolio_id = :portfolio_id AND status = :status
                ORDER BY created_at DESC
            ");
            
            $stmt->execute([
                ':portfolio_id' => $this->getPortfolioId(),
                ':status' => self::INVITE_PENDING
            ]);
            
            $invitations = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['permissions'] = json_decode($row['permissions'], true) ?: [];
                $invitations[] = $row;
            }
            
            return $invitations;
        } catch (Exception $e) {
            throw new Exception("Error retrieving invitations: " . $e->getMessage());
        }
    }
    
    /**
     * Update team member role
     */
    public function updateTeamMemberRole(int $userId, $role) {
        try {
            if (!$this->hasPermission(self::PERM_ADMIN)) {
                throw new Exception("You don't have permission to update team members");
            }
            
            // Prevent downgrading owner
            $currentRole = $this->getUserRole($userId);
            if ($currentRole === self::ROLE_OWNER) {
                throw new Exception("Cannot change owner role");
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE team_members 
                SET role = :role, updated_at = NOW()
                WHERE user_id = :user_id AND portfolio_id = :portfolio_id
            ");
            
            $result = $stmt->execute([
                ':role' => $role,
                ':user_id' => $userId,
                ':portfolio_id' => $this->getPortfolioId()
            ]);
            
            if (!$result) {
                throw new Exception("Failed to update role");
            }
            
            // Log
            $this->auditTrail->log(
                $this->userId,
                'team_member_updated',
                'team_members',
                $userId,
                ['role' => $currentRole],
                json_encode(['role' => $role])
            );
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Error updating team member: " . $e->getMessage());
        }
    }
    
    /**
     * Remove team member
     */
    public function removeTeamMember(int $userId) {
        try {
            if (!$this->hasPermission(self::PERM_ADMIN)) {
                throw new Exception("You don't have permission to remove team members");
            }
            
            // Prevent removing owner
            $role = $this->getUserRole($userId);
            if ($role === self::ROLE_OWNER) {
                throw new Exception("Cannot remove owner from team");
            }
            
            $stmt = $this->pdo->prepare("
                DELETE FROM team_members 
                WHERE user_id = :user_id AND portfolio_id = :portfolio_id
            ");
            
            $result = $stmt->execute([
                ':user_id' => $userId,
                ':portfolio_id' => $this->getPortfolioId()
            ]);
            
            if (!$result) {
                throw new Exception("Failed to remove team member");
            }
            
            // Log
            $this->auditTrail->log(
                $this->userId,
                'team_member_removed',
                'team_members',
                $userId,
                ['role' => $role],
                json_encode([])
            );
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Error removing team member: " . $e->getMessage());
        }
    }
    
    /**
     * Cancel invitation
     */
    public function cancelInvitation($invitationId) {
        try {
            if (!$this->hasPermission(self::PERM_ADMIN)) {
                throw new Exception("You don't have permission to cancel invitations");
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE team_invitations 
                SET status = :status, updated_at = NOW()
                WHERE id = :id AND portfolio_id = :portfolio_id
            ");
            
            $result = $stmt->execute([
                ':status' => 'cancelled',
                ':id' => $invitationId,
                ':portfolio_id' => $this->getPortfolioId()
            ]);
            
            if (!$result) {
                throw new Exception("Failed to cancel invitation");
            }
            
            // Log
            $this->auditTrail->log(
                $this->userId,
                'team_invitation_cancelled',
                'team_invitations',
                $invitationId,
                [],
                json_encode([])
            );
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Error cancelling invitation: " . $e->getMessage());
        }
    }
    
    /**
     * Grant permission
     */
    public function grantPermission(int $userId, $permission) {
        try {
            if (!$this->hasPermission(self::PERM_ADMIN)) {
                throw new Exception("You don't have permission to grant permissions");
            }
            
            $stmt = $this->pdo->prepare("
                SELECT permissions FROM team_members 
                WHERE user_id = :user_id AND portfolio_id = :portfolio_id
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':portfolio_id' => $this->getPortfolioId()
            ]);
            
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$member) {
                throw new Exception("Team member not found");
            }
            
            $permissions = json_decode($member['permissions'], true) ?: [];
            if (!in_array($permission, $permissions)) {
                $permissions[] = $permission;
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE team_members 
                SET permissions = :permissions
                WHERE user_id = :user_id AND portfolio_id = :portfolio_id
            ");
            
            $stmt->execute([
                ':permissions' => json_encode($permissions),
                ':user_id' => $userId,
                ':portfolio_id' => $this->getPortfolioId()
            ]);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Error granting permission: " . $e->getMessage());
        }
    }
    
    /**
     * Revoke permission
     */
    public function revokePermission(int $userId, $permission) {
        try {
            if (!$this->hasPermission(self::PERM_ADMIN)) {
                throw new Exception("You don't have permission to revoke permissions");
            }
            
            $stmt = $this->pdo->prepare("
                SELECT permissions FROM team_members 
                WHERE user_id = :user_id AND portfolio_id = :portfolio_id
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':portfolio_id' => $this->getPortfolioId()
            ]);
            
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$member) {
                throw new Exception("Team member not found");
            }
            
            $permissions = json_decode($member['permissions'], true) ?: [];
            $permissions = array_diff($permissions, [$permission]);
            
            $stmt = $this->pdo->prepare("
                UPDATE team_members 
                SET permissions = :permissions
                WHERE user_id = :user_id AND portfolio_id = :portfolio_id
            ");
            
            $stmt->execute([
                ':permissions' => json_encode($permissions),
                ':user_id' => $userId,
                ':portfolio_id' => $this->getPortfolioId()
            ]);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Error revoking permission: " . $e->getMessage());
        }
    }
    
    /**
     * Get portfolio ID (for current user)
     */
    private function getPortfolioId() {
        // In a real implementation, get from context or database
        // For now, return a default
        return 1;
    }
}
?>
