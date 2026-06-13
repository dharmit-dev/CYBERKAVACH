<?php

declare(strict_types=1);

final class Event
{
    public static function categories(): array
    {
        return db()->query('SELECT * FROM event_categories WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO events
                (category_id, title, slug, description, event_date, start_time, end_time, venue,
                 registration_deadline, capacity, poster_path, team_allowed, min_team_size,
                 max_team_size, event_rules, status, created_by, created_at, updated_at)
             VALUES
                (:category_id, :title, :slug, :description, :event_date, :start_time, :end_time, :venue,
                 :registration_deadline, :capacity, :poster_path, :team_allowed, :min_team_size,
                 :max_team_size, :event_rules, :status, :created_by, NOW(), NOW())'
        );
        $stmt->execute(self::payload($data));
        $eventId = (int) db()->lastInsertId();
        self::syncTags($eventId, (string) ($data['tags'] ?? ''));

        return $eventId;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE events
             SET category_id = :category_id,
                 title = :title,
                 slug = :slug,
                 description = :description,
                 event_date = :event_date,
                 start_time = :start_time,
                 end_time = :end_time,
                 venue = :venue,
                 registration_deadline = :registration_deadline,
                 capacity = :capacity,
                 poster_path = :poster_path,
                 team_allowed = :team_allowed,
                 min_team_size = :min_team_size,
                 max_team_size = :max_team_size,
                 event_rules = :event_rules,
                 status = :status,
                 approved_by = CASE WHEN :status_check = "draft" THEN NULL ELSE approved_by END,
                 published_at = CASE WHEN :status_check2 = "draft" THEN NULL ELSE published_at END,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'event_date' => $data['event_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'venue' => $data['venue'],
            'registration_deadline' => $data['registration_deadline'],
            'capacity' => $data['capacity'],
            'poster_path' => $data['poster_path'] ?? null,
            'team_allowed' => !empty($data['team_allowed']) ? 1 : 0,
            'min_team_size' => !empty($data['team_allowed']) ? $data['min_team_size'] : null,
            'max_team_size' => !empty($data['team_allowed']) ? $data['max_team_size'] : null,
            'event_rules' => $data['event_rules'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'status_check' => $data['status'] ?? 'draft',
            'status_check2' => $data['status'] ?? 'draft',
            'id' => $id,
        ]);
        self::syncTags($id, (string) ($data['tags'] ?? ''));
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT events.*, event_categories.name AS category_name, users.full_name AS creator_name
             FROM events
             INNER JOIN event_categories ON event_categories.id = events.category_id
             INNER JOIN users ON users.id = events.created_by
             WHERE events.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $event = $stmt->fetch();

        return $event ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = db()->prepare(
            'SELECT events.*, event_categories.name AS category_name, users.full_name AS creator_name
             FROM events
             INNER JOIN event_categories ON event_categories.id = events.category_id
             INNER JOIN users ON users.id = events.created_by
             WHERE events.slug = :slug
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $event = $stmt->fetch();

        return $event ?: null;
    }

    public static function listManaged(?string $search = null): array
    {
        $sql = 'SELECT events.*, event_categories.name AS category_name,
                    COUNT(DISTINCT event_registrations.id) AS registration_count,
                    COUNT(DISTINCT event_teams.id) AS team_count
                FROM events
                INNER JOIN event_categories ON event_categories.id = events.category_id
                LEFT JOIN event_registrations ON event_registrations.event_id = events.id
                    AND event_registrations.status = "registered"
                LEFT JOIN event_teams ON event_teams.event_id = events.id
                    AND event_teams.status = "registered"';
        $params = [];

        if ($search) {
            $sql .= ' WHERE events.title LIKE :search OR events.venue LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' GROUP BY events.id ORDER BY events.event_date DESC, events.start_time DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function listPublished(?int $categoryId = null, ?string $search = null): array
    {
        $sql = 'SELECT events.*, event_categories.name AS category_name
                FROM events
                INNER JOIN event_categories ON event_categories.id = events.category_id
                WHERE events.status = "published"';
        $params = [];

        if ($categoryId) {
            $sql .= ' AND events.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if ($search) {
            $sql .= ' AND (events.title LIKE :search OR events.description LIKE :search OR events.venue LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY events.event_date ASC, events.start_time ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function tagsForEvent(int $eventId): array
    {
        $stmt = db()->prepare(
            'SELECT event_tags.name
             FROM event_tag_mapping
             INNER JOIN event_tags ON event_tags.id = event_tag_mapping.tag_id
             WHERE event_tag_mapping.event_id = :event_id
             ORDER BY event_tags.name ASC'
        );
        $stmt->execute(['event_id' => $eventId]);

        return array_column($stmt->fetchAll(), 'name');
    }

    public static function submitForApproval(int $eventId): void
    {
        $stmt = db()->prepare(
            "UPDATE events SET status = 'pending_approval', updated_at = NOW()
             WHERE id = :id AND status IN ('draft', 'rejected')"
        );
        $stmt->execute(['id' => $eventId]);
    }

    public static function publish(int $eventId, ?int $approvedBy = null): void
    {
        $stmt = db()->prepare(
            "UPDATE events
             SET status = 'published', approved_by = :approved_by, published_at = NOW(), updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'id' => $eventId,
            'approved_by' => $approvedBy,
        ]);
    }

    public static function markUnderReview(int $eventId): void
    {
        $stmt = db()->prepare("UPDATE events SET status = 'under_review', updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $eventId]);
    }

    public static function markRejected(int $eventId): void
    {
        $stmt = db()->prepare("UPDATE events SET status = 'rejected', updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $eventId]);
    }

    public static function cancel(int $eventId): void
    {
        $stmt = db()->prepare(
            "UPDATE events SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW()
             WHERE id = :id AND status != 'completed'"
        );
        $stmt->execute(['id' => $eventId]);
    }

    public static function countByStatus(string $status): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM events WHERE status = :status');
        $stmt->execute(['status' => $status]);

        return (int) $stmt->fetchColumn();
    }

    private static function payload(array $data): array
    {
        return [
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'event_date' => $data['event_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'venue' => $data['venue'],
            'registration_deadline' => $data['registration_deadline'],
            'capacity' => $data['capacity'],
            'poster_path' => $data['poster_path'] ?? null,
            'team_allowed' => !empty($data['team_allowed']) ? 1 : 0,
            'min_team_size' => !empty($data['team_allowed']) ? $data['min_team_size'] : null,
            'max_team_size' => !empty($data['team_allowed']) ? $data['max_team_size'] : null,
            'event_rules' => $data['event_rules'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'created_by' => $data['created_by'],
        ];
    }

    private static function syncTags(int $eventId, string $tags): void
    {
        db()->prepare('DELETE FROM event_tag_mapping WHERE event_id = :event_id')->execute(['event_id' => $eventId]);
        $names = array_filter(array_unique(array_map(
            static fn (string $tag): string => trim($tag),
            explode(',', $tags)
        )));

        $find = db()->prepare('SELECT id FROM event_tags WHERE name = :name LIMIT 1');
        $insert = db()->prepare('INSERT INTO event_tags (name, created_at) VALUES (:name, NOW())');
        $map = db()->prepare('INSERT IGNORE INTO event_tag_mapping (event_id, tag_id) VALUES (:event_id, :tag_id)');

        foreach ($names as $name) {
            if (strlen($name) > 80) {
                continue;
            }
            $find->execute(['name' => $name]);
            $tagId = $find->fetchColumn();

            if (!$tagId) {
                $insert->execute(['name' => $name]);
                $tagId = db()->lastInsertId();
            }

            $map->execute(['event_id' => $eventId, 'tag_id' => $tagId]);
        }
    }
}
