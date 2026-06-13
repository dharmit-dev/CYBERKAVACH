<?php

declare(strict_types=1);

final class EventRegistration
{
    public static function userRegistered(int $eventId, int $userId): bool
    {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM event_registrations
             WHERE event_id = :event_id AND user_id = :user_id AND status = 'registered'"
        );
        $stmt->execute(['event_id' => $eventId, 'user_id' => $userId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function countForEvent(int $eventId): int
    {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM event_registrations WHERE event_id = :event_id AND status = 'registered'"
        );
        $stmt->execute(['event_id' => $eventId]);

        return (int) $stmt->fetchColumn();
    }

    public static function teamCountForEvent(int $eventId): int
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM event_teams WHERE event_id = :event_id AND status = 'registered'");
        $stmt->execute(['event_id' => $eventId]);

        return (int) $stmt->fetchColumn();
    }

    public static function registerIndividual(int $eventId, int $userId): int
    {
        $stmt = db()->prepare(
            "INSERT INTO event_registrations (event_id, user_id, registration_type, status, registered_at)
             VALUES (:event_id, :user_id, 'individual', 'registered', NOW())"
        );
        $stmt->execute(['event_id' => $eventId, 'user_id' => $userId]);

        return (int) db()->lastInsertId();
    }

    public static function createTeamRegistration(int $eventId, int $leaderId, string $teamName, array $memberIds): int
    {
        $teamIdentifier = 'CK-' . strtoupper(bin2hex(random_bytes(4)));
        $qrPayload = 'CYBERKAVACH:TEAM:' . $teamIdentifier;
        $qrPath = QRService::generateTeamQr($teamIdentifier, $qrPayload);

        $stmt = db()->prepare(
            "INSERT INTO saved_teams (owner_user_id, team_name, created_at, updated_at)
             VALUES (:owner_user_id, :team_name, NOW(), NOW())"
        );
        $stmt->execute(['owner_user_id' => $leaderId, 'team_name' => $teamName]);
        $savedTeamId = (int) db()->lastInsertId();

        $stmt = db()->prepare(
            "INSERT INTO event_teams
                (event_id, saved_team_id, team_identifier, team_name, leader_user_id, qr_payload, qr_path, status, created_at, updated_at)
             VALUES
                (:event_id, :saved_team_id, :team_identifier, :team_name, :leader_user_id, :qr_payload, :qr_path, 'registered', NOW(), NOW())"
        );
        $stmt->execute([
            'event_id' => $eventId,
            'saved_team_id' => $savedTeamId,
            'team_identifier' => $teamIdentifier,
            'team_name' => $teamName,
            'leader_user_id' => $leaderId,
            'qr_payload' => $qrPayload,
            'qr_path' => $qrPath,
        ]);
        $teamId = (int) db()->lastInsertId();

        $saveMember = db()->prepare('INSERT IGNORE INTO saved_team_members (saved_team_id, user_id, created_at) VALUES (:saved_team_id, :user_id, NOW())');
        $teamMember = db()->prepare('INSERT INTO event_team_members (team_id, user_id, is_leader, joined_at) VALUES (:team_id, :user_id, :is_leader, NOW())');
        $registration = db()->prepare(
            "INSERT INTO event_registrations (event_id, user_id, team_id, registration_type, status, registered_at)
             VALUES (:event_id, :user_id, :team_id, 'team', 'registered', NOW())"
        );

        foreach ($memberIds as $memberId) {
            $isLeader = (int) $memberId === $leaderId ? 1 : 0;
            $saveMember->execute(['saved_team_id' => $savedTeamId, 'user_id' => $memberId]);
            $teamMember->execute(['team_id' => $teamId, 'user_id' => $memberId, 'is_leader' => $isLeader]);
            $registration->execute(['event_id' => $eventId, 'user_id' => $memberId, 'team_id' => $teamId]);
        }

        return $teamId;
    }

    public static function participantsForEvent(int $eventId, ?string $search = null): array
    {
        $sql = 'SELECT er.*, users.full_name, users.email, users.phone,
                    et.team_name, et.team_identifier
                FROM event_registrations er
                INNER JOIN users ON users.id = er.user_id
                LEFT JOIN event_teams et ON et.id = er.team_id
                WHERE er.event_id = :event_id AND er.status = "registered"';
        $params = ['event_id' => $eventId];

        if ($search) {
            $sql .= ' AND (users.full_name LIKE :search OR users.email LIKE :search OR et.team_name LIKE :search OR et.team_identifier LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY er.registered_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function historyForUser(int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT er.*, events.title, events.slug, events.event_date, events.venue, et.team_name, et.team_identifier, et.qr_path
             FROM event_registrations er
             INNER JOIN events ON events.id = er.event_id
             LEFT JOIN event_teams et ON et.id = er.team_id
             WHERE er.user_id = :user_id
             ORDER BY er.registered_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function savedTeamsForUser(int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT saved_teams.*,
                    COUNT(saved_team_members.id) AS member_count
             FROM saved_teams
             LEFT JOIN saved_team_members ON saved_team_members.saved_team_id = saved_teams.id
             WHERE saved_teams.owner_user_id = :user_id
             GROUP BY saved_teams.id
             ORDER BY saved_teams.updated_at DESC, saved_teams.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function savedTeamMemberIds(int $savedTeamId, int $ownerUserId): array
    {
        $stmt = db()->prepare(
            'SELECT saved_team_members.user_id
             FROM saved_teams
             INNER JOIN saved_team_members ON saved_team_members.saved_team_id = saved_teams.id
             WHERE saved_teams.id = :saved_team_id AND saved_teams.owner_user_id = :owner_user_id'
        );
        $stmt->execute([
            'saved_team_id' => $savedTeamId,
            'owner_user_id' => $ownerUserId,
        ]);

        return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    }
}
