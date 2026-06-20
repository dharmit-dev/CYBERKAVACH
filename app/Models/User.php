<?php

declare(strict_types=1);

final class User
{
    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT users.*, roles.role_key, roles.role_name
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare(
            'SELECT users.*, roles.role_key, roles.role_name
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => strtolower($email)]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO users
                (role_id, full_name, email, phone, password_hash, status, created_at, updated_at)
             VALUES
                (:role_id, :full_name, :email, :phone, :password_hash, :status, NOW(), NOW())'
        );

        $stmt->execute([
            'role_id' => $data['role_id'],
            'full_name' => $data['full_name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'status' => 'pending_email',
        ]);

        return (int) db()->lastInsertId();
    }

    public static function allowedPublicRegistrationRoles(): array
    {
        $stmt = db()->query(
            "SELECT id, role_name, role_key
             FROM roles
             WHERE role_key IN ('club_member', 'guest_participant')
             ORDER BY FIELD(role_key, 'club_member', 'guest_participant')"
        );

        return $stmt->fetchAll();
    }

    public static function coordinatorRoles(): array
    {
        $stmt = db()->query(
            "SELECT id, role_name, role_key
             FROM roles
             WHERE role_key IN (
                'faculty_coordinator',
                'student_coordinator',
                'tech_coordinator',
                'content_coordinator',
                'social_media_coordinator'
             )
             ORDER BY FIELD(role_key,
                'faculty_coordinator',
                'student_coordinator',
                'tech_coordinator',
                'content_coordinator',
                'social_media_coordinator'
             )"
        );

        return $stmt->fetchAll();
    }

    public static function createCoordinator(array $data): int
    {
        $stmt = db()->prepare(
            "INSERT INTO users
                (role_id, full_name, email, phone, password_hash, status, email_verified_at, created_at, updated_at)
             VALUES
                (:role_id, :full_name, :email, :phone, :password_hash, 'active', NOW(), NOW(), NOW())"
        );
        $stmt->execute([
            'role_id' => $data['role_id'],
            'full_name' => $data['full_name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);

        return (int) db()->lastInsertId();
    }

    public static function activeCoordinatorCount(): int
    {
        $stmt = db()->query(
            "SELECT COUNT(*)
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.status = 'active'
                AND roles.role_key IN (
                    'faculty_coordinator',
                    'student_coordinator',
                    'tech_coordinator',
                    'content_coordinator',
                    'social_media_coordinator'
                )"
        );

        return (int) $stmt->fetchColumn();
    }

    public static function searchActiveUsers(string $query, int $limit = 10): array
    {
        $like = '%' . $query . '%';
        $stmt = db()->prepare(
            "SELECT users.id, users.full_name, users.email, users.phone,
                    roles.role_name, user_profiles.college_id, user_profiles.roll_number
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             LEFT JOIN user_profiles ON user_profiles.user_id = users.id
             WHERE users.status = 'active'
                AND (
                    users.full_name LIKE :query1
                    OR users.email LIKE :query2
                    OR user_profiles.college_id LIKE :query3
                    OR user_profiles.roll_number LIKE :query4
                )
             ORDER BY users.full_name ASC
             LIMIT " . max(1, min(25, $limit))
        );
        $stmt->execute([
            'query1' => $like,
            'query2' => $like,
            'query3' => $like,
            'query4' => $like,
        ]);

        return $stmt->fetchAll();
    }

    public static function verifyEmailAndMarkPendingApproval(int $userId): void
    {
        $stmt = db()->prepare(
            "UPDATE users
             SET email_verified_at = NOW(), status = 'pending_approval', updated_at = NOW()
             WHERE id = :id AND status = 'pending_email'"
        );
        $stmt->execute(['id' => $userId]);
    }

    public static function activate(int $userId): void
    {
        $stmt = db()->prepare(
            "UPDATE users
             SET status = 'active', updated_at = NOW()
             WHERE id = :id AND status IN ('pending_approval', 'rejected')"
        );
        $stmt->execute(['id' => $userId]);
    }

    public static function reject(int $userId): void
    {
        $stmt = db()->prepare(
            "UPDATE users
             SET status = 'rejected', updated_at = NOW()
             WHERE id = :id AND status = 'pending_approval'"
        );
        $stmt->execute(['id' => $userId]);
    }

    public static function countByStatus(string $status): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE status = :status');
        $stmt->execute(['status' => $status]);

        return (int) $stmt->fetchColumn();
    }

    public static function countByRoleKey(string $roleKey): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE roles.role_key = :role_key AND users.status = :status'
        );
        $stmt->execute([
            'role_key' => $roleKey,
            'status' => 'active',
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function updatePassword(int $userId, string $password): void
    {
        $stmt = db()->prepare(
            'UPDATE users
             SET password_hash = :password_hash, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public static function updateRole(int $userId, int $roleId): void
    {
        $stmt = db()->prepare(
            'UPDATE users SET role_id = :role_id, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $userId, 'role_id' => $roleId]);
    }

    public static function block(int $userId): void
    {
        $stmt = db()->prepare(
            "UPDATE users SET status = 'blocked', updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['id' => $userId]);
    }

    public static function unblock(int $userId): void
    {
        $stmt = db()->prepare(
            "UPDATE users SET status = 'active', updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['id' => $userId]);
    }

    public static function listAll(string $search = '', int $limit = 100): array
    {
        $sql = "SELECT users.*, roles.role_name, roles.role_key,
                       up.college_id, up.department, up.roll_number
                FROM users
                INNER JOIN roles ON roles.id = users.role_id
                LEFT JOIN user_profiles up ON up.user_id = users.id";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE users.full_name LIKE :search 
                         OR users.email LIKE :search 
                         OR up.college_id LIKE :search 
                         OR up.roll_number LIKE :search";
            $params['search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY users.created_at DESC LIMIT " . (int)$limit;
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function allRoles(): array
    {
        $stmt = db()->query("SELECT id, role_name, role_key FROM roles ORDER BY id ASC");
        return $stmt->fetchAll();
    }
}
