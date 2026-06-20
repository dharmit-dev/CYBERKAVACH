<?php

declare(strict_types=1);

final class AttendanceService
{
    public static function checkIn(int $eventId, int $userId, array $scannerUser, ?int $teamId = null): array
    {
        if (!self::canManageAttendance($scannerUser)) {
            return ['ok' => false, 'message' => 'Unauthorized to scan attendance.'];
        }

        $event = Event::findById($eventId);
        if (!$event || !in_array($event['status'], ['approved', 'published', 'completed'], true)) {
            return ['ok' => false, 'message' => 'Event is not valid or not active.'];
        }

        $user = User::findById($userId);
        if (!$user || $user['status'] !== 'active') {
            return ['ok' => false, 'message' => 'User is not active.'];
        }

        $registrationId = self::getRegistrationId($eventId, $userId, $teamId);
        if (!$registrationId) {
            return ['ok' => false, 'message' => 'Valid registration not found for this user.'];
        }

        if ($teamId > 0 && !self::isValidTeamMember($teamId, $userId)) {
             return ['ok' => false, 'message' => 'User is not part of this team.'];
        }

        if (self::hasCheckedIn($eventId, $userId)) {
            return ['ok' => false, 'message' => 'User has already checked in.'];
        }

        self::recordAttendance($eventId, $userId, (int) $scannerUser['id'], 'check_in', $registrationId, $teamId);

        return ['ok' => true, 'message' => 'Check-in successful.'];
    }

    public static function checkOut(int $eventId, int $userId, array $scannerUser, ?int $teamId = null): array
    {
        if (!self::canManageAttendance($scannerUser)) {
            return ['ok' => false, 'message' => 'Unauthorized to scan attendance.'];
        }

        if (!self::hasCheckedIn($eventId, $userId)) {
            return ['ok' => false, 'message' => 'User must check-in before checking out.'];
        }

        if (self::hasCheckedOut($eventId, $userId)) {
            return ['ok' => false, 'message' => 'User has already checked out.'];
        }

        $registrationId = self::getRegistrationId($eventId, $userId, $teamId);

        self::recordAttendance($eventId, $userId, (int) $scannerUser['id'], 'check_out', $registrationId, $teamId);

        return ['ok' => true, 'message' => 'Check-out successful.'];
    }

    public static function hasCheckedIn(int $eventId, int $userId): bool
    {
        return self::hasAttendanceRecord($eventId, $userId, 'check_in');
    }

    public static function hasCheckedOut(int $eventId, int $userId): bool
    {
        return self::hasAttendanceRecord($eventId, $userId, 'check_out');
    }

    public static function getAttendanceHistory(int $eventId, int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT ea.*, u.full_name as scanner_name 
             FROM event_attendance ea
             LEFT JOIN users u ON u.id = ea.scanned_by_user_id
             WHERE ea.event_id = :event_id AND ea.user_id = :user_id
             ORDER BY ea.scanned_at ASC'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'user_id' => $userId,
        ]);
        
        return $stmt->fetchAll();
    }

    private static function hasAttendanceRecord(int $eventId, int $userId, string $type): bool
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM event_attendance 
             WHERE event_id = :event_id 
             AND user_id = :user_id 
             AND attendance_type = :type'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'user_id' => $userId,
            'type' => $type,
        ]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function recordAttendance(
        int $eventId, 
        int $userId, 
        int $scannerId, 
        string $type, 
        ?int $registrationId, 
        ?int $teamId
    ): void {
        $event = Event::findById($eventId);
        $isLate = 0;
        $isEarlyExit = 0;

        if ($type === 'check_in') {
            if ($event) {
                $eventStart = strtotime($event['event_date'] . ' ' . $event['start_time']);
                $thresholdSeconds = (int) ($event['late_arrival_threshold_minutes'] ?? 15) * 60;
                if (time() > $eventStart + $thresholdSeconds) {
                    $isLate = 1;
                }
            }
        } elseif ($type === 'check_out') {
            if ($event) {
                $eventEnd = strtotime($event['event_date'] . ' ' . $event['end_time']);
                $thresholdSeconds = (int) ($event['early_exit_threshold_minutes'] ?? 15) * 60;
                if (time() < $eventEnd - $thresholdSeconds) {
                    $isEarlyExit = 1;
                }
            }
        }

        $stmt = db()->prepare(
            'INSERT INTO event_attendance 
                (event_id, registration_id, team_id, user_id, attendance_type, is_late, is_early_exit, scanned_by_user_id, scanned_at, created_at)
             VALUES 
                (:event_id, :registration_id, :team_id, :user_id, :type, :is_late, :is_early_exit, :scanned_by, NOW(), NOW())'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'registration_id' => $registrationId,
            'team_id' => $teamId,
            'user_id' => $userId,
            'type' => $type,
            'is_late' => $isLate,
            'is_early_exit' => $isEarlyExit,
            'scanned_by' => $scannerId,
        ]);

        AuditService::record('attendance_' . $type, 'events', $scannerId, 'users', $userId);
    }

    private static function getRegistrationId(int $eventId, int $userId, ?int $teamId): ?int
    {
        if ($teamId > 0) {
            $stmt = db()->prepare(
                'SELECT id FROM event_registrations 
                 WHERE event_id = :event_id AND team_id = :team_id AND status = "registered" LIMIT 1'
            );
            $stmt->execute(['event_id' => $eventId, 'team_id' => $teamId]);
        } else {
            $stmt = db()->prepare(
                'SELECT id FROM event_registrations 
                 WHERE event_id = :event_id AND user_id = :user_id AND status = "registered" LIMIT 1'
            );
            $stmt->execute(['event_id' => $eventId, 'user_id' => $userId]);
        }
        $row = $stmt->fetch();
        
        return $row ? (int) $row['id'] : null;
    }

    private static function isValidTeamMember(int $teamId, int $userId): bool
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM event_team_members 
             WHERE team_id = :team_id AND user_id = :user_id'
        );
        $stmt->execute(['team_id' => $teamId, 'user_id' => $userId]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function canManageAttendance(array $user): bool
    {
        $roleKey = $user['role_key'] ?? '';
        return in_array($roleKey, ['faculty_coordinator', 'student_coordinator', 'tech_coordinator'], true);
    }
}
