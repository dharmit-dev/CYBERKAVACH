<?php

declare(strict_types=1);

final class PointsService
{
    public static function awardPoints(int $userId, int $points, string $reason, string $category, ?int $awardedBy = null): void
    {
        $db = db();
        $db->beginTransaction();

        try {
            // 1. Insert points transaction record
            $stmt = $db->prepare(
                'INSERT INTO member_points (user_id, points, reason, category, awarded_by, created_at)
                 VALUES (:user_id, :points, :reason, :category, :awarded_by, NOW())'
            );
            $stmt->execute([
                'user_id' => $userId,
                'points' => $points,
                'reason' => $reason,
                'category' => $category,
                'awarded_by' => $awardedBy,
            ]);

            // 2. Fetch cumulative points total with lock
            $stmt = $db->prepare('SELECT SUM(points) FROM member_points WHERE user_id = :user_id FOR UPDATE');
            $stmt->execute(['user_id' => $userId]);
            $totalPoints = (int) $stmt->fetchColumn();

            // 3. Evaluate and award unlocked badges
            $stmt = $db->prepare(
                'SELECT id, name, description 
                 FROM badges 
                 WHERE threshold_points <= :total 
                   AND id NOT IN (SELECT badge_id FROM user_badges WHERE user_id = :user_id)'
            );
            $stmt->execute([
                'total' => $totalPoints,
                'user_id' => $userId,
            ]);
            $unlockedBadges = $stmt->fetchAll();

            if ($unlockedBadges !== []) {
                $insertBadge = $db->prepare(
                    'INSERT IGNORE INTO user_badges (user_id, badge_id, unlocked_at)
                     VALUES (:user_id, :badge_id, NOW())'
                );

                foreach ($unlockedBadges as $badge) {
                    $insertBadge->execute([
                        'user_id' => $userId,
                        'badge_id' => $badge['id'],
                    ]);

                    // Send in-app notification to member
                    NotificationService::notifyUsers([$userId], [
                        'title' => 'Badge Unlocked: ' . $badge['name'],
                        'message' => 'Congratulations! You have unlocked the "' . $badge['name'] . '" badge: ' . $badge['description'],
                        'type' => 'badge_unlock',
                        'entity_type' => 'badges',
                        'entity_id' => (int) $badge['id'],
                    ]);

                    // Log audit
                    AuditService::record('badge_unlocked', 'rewards', $userId, 'badges', (int) $badge['id']);
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function getUserPointsTotal(int $userId): int
    {
        $stmt = db()->prepare('SELECT SUM(points) FROM member_points WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) ($stmt->fetchColumn() ?? 0);
    }

    public static function getUserPointsLogs(int $userId): array
    {
        $stmt = db()->prepare('
            SELECT mp.*, u.full_name as awarded_by_name
            FROM member_points mp
            LEFT JOIN users u ON u.id = mp.awarded_by
            WHERE mp.user_id = :user_id
            ORDER BY mp.created_at DESC
        ');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function getUserBadges(int $userId): array
    {
        $stmt = db()->prepare('
            SELECT ub.unlocked_at, b.name, b.description, b.icon, b.threshold_points
            FROM user_badges ub
            INNER JOIN badges b ON b.id = ub.badge_id
            WHERE ub.user_id = :user_id
            ORDER BY b.threshold_points ASC
        ');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function getLeaderboard(int $limit = 10): array
    {
        $stmt = db()->prepare('
            SELECT u.id, u.full_name, u.email, COALESCE(SUM(mp.points), 0) as total_points
            FROM users u
            LEFT JOIN member_points mp ON mp.user_id = u.id
            INNER JOIN roles r ON r.id = u.role_id
            WHERE r.role_key = \'club_member\' AND u.status = \'active\'
            GROUP BY u.id, u.full_name, u.email
            ORDER BY total_points DESC, u.full_name ASC
            LIMIT ' . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getCatalog(): array
    {
        $stmt = db()->query('SELECT * FROM reward_items ORDER BY points_cost ASC');
        return $stmt->fetchAll();
    }

    public static function redeemReward(int $userId, int $itemId): array
    {
        $db = db();
        $db->beginTransaction();

        try {
            // 1. Lock reward item and verify stock
            $stmt = $db->prepare('SELECT * FROM reward_items WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $itemId]);
            $item = $stmt->fetch();

            if (!$item) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Reward item not found.'];
            }

            if ((int) $item['stock'] <= 0) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'This item is currently out of stock.'];
            }

            // 2. Lock user points balance
            $stmt = $db->prepare('SELECT SUM(points) FROM member_points WHERE user_id = :user_id FOR UPDATE');
            $stmt->execute(['user_id' => $userId]);
            $currentPoints = (int) ($stmt->fetchColumn() ?? 0);

            if ($currentPoints < (int) $item['points_cost']) {
                $db->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Insufficient points. You need ' . $item['points_cost'] . ' points, but you have ' . $currentPoints . '.',
                ];
            }

            // 3. Deduct points from user ledger
            $deduct = $db->prepare('
                INSERT INTO member_points (user_id, points, reason, category, created_at)
                VALUES (:user_id, :points, :reason, :category, NOW())
            ');
            $deduct->execute([
                'user_id' => $userId,
                'points' => -((int) $item['points_cost']),
                'reason' => 'Redeemed: ' . $item['name'],
                'category' => 'redemption',
            ]);

            // 4. Decrement item inventory stock
            $updateStock = $db->prepare('UPDATE reward_items SET stock = stock - 1 WHERE id = :id');
            $updateStock->execute(['id' => $itemId]);

            // 5. Create approval request for physical fulfillment if needed, or record audit
            AuditService::record('reward_redeemed', 'rewards', $userId, 'reward_items', $itemId);

            $db->commit();
            return ['ok' => true, 'message' => 'Redeemed ' . $item['name'] . ' successfully!'];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
