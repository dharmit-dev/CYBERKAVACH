<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/role.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

// 1. Gated with role authorization
$user = require_role(['student_coordinator', 'faculty_coordinator', 'tech_coordinator']);

$eventId = (int) ($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
$teamId = (int) ($_GET['team_id'] ?? $_POST['team_id'] ?? 0);

$event = Event::findById($eventId);
if (!$event) {
    flash('error', 'Event not found.');
    redirect('events/manage.php');
}

$stmt = db()->prepare('SELECT * FROM event_teams WHERE id = :team_id AND event_id = :event_id LIMIT 1');
$stmt->execute(['team_id' => $teamId, 'event_id' => $eventId]);
$team = $stmt->fetch();

if (!$team) {
    flash('error', 'Team not found for this event.');
    redirect('events/registrations.php?id=' . $eventId);
}

// Handle composition overrides
if (request_method_is('POST')) {
    verify_csrf();
    $action = input_string('action');

    try {
        db()->beginTransaction();

        if ($action === 'make_leader') {
            $newLeaderId = (int) input_string('user_id');

            // Verify member exists in team
            $stmt = db()->prepare('SELECT COUNT(*) FROM event_team_members WHERE team_id = :team_id AND user_id = :user_id');
            $stmt->execute(['team_id' => $teamId, 'user_id' => $newLeaderId]);
            if (((int) $stmt->fetchColumn()) === 0) {
                throw new RuntimeException('Selected user is not a member of this team.');
            }

            // Update team table
            $stmt = db()->prepare('UPDATE event_teams SET leader_user_id = :leader_id, updated_at = NOW() WHERE id = :team_id');
            $stmt->execute(['leader_id' => $newLeaderId, 'team_id' => $teamId]);

            // Update team members status
            $stmt = db()->prepare('UPDATE event_team_members SET is_leader = 0 WHERE team_id = :team_id');
            $stmt->execute(['team_id' => $teamId]);

            $stmt = db()->prepare('UPDATE event_team_members SET is_leader = 1 WHERE team_id = :team_id AND user_id = :user_id');
            $stmt->execute(['team_id' => $teamId, 'user_id' => $newLeaderId]);

            // Security: Record override log
            AuditService::record('team_leader_overridden', 'events', (int) $user['id'], 'event_teams', $teamId);
            flash('success', 'Team leader updated successfully.');
        } elseif ($action === 'remove_member') {
            $memberId = (int) input_string('user_id');

            // Cannot remove the leader
            if ($memberId === (int) $team['leader_user_id']) {
                throw new RuntimeException('Cannot remove the team leader. Please promote another member to leader first.');
            }

            // Delete registration entry
            $stmt = db()->prepare('DELETE FROM event_registrations WHERE event_id = :event_id AND user_id = :user_id AND team_id = :team_id');
            $stmt->execute(['event_id' => $eventId, 'user_id' => $memberId, 'team_id' => $teamId]);

            // Delete team member entry
            $stmt = db()->prepare('DELETE FROM event_team_members WHERE team_id = :team_id AND user_id = :user_id');
            $stmt->execute(['team_id' => $teamId, 'user_id' => $memberId]);

            // Security: Record override log
            AuditService::record('team_member_removed', 'events', (int) $user['id'], 'users', $memberId);
            flash('success', 'Team member removed successfully.');
        } elseif ($action === 'add_member') {
            $memberId = (int) input_string('user_id');

            $member = User::findById($memberId);
            if (!$member || $member['status'] !== 'active') {
                throw new RuntimeException('Selected user is not active.');
            }

            // Check if already registered for this event
            if (EventRegistration::userRegistered($eventId, $memberId)) {
                throw new RuntimeException('User is already registered for this event.');
            }

            // Check max team size limit
            $stmt = db()->prepare('SELECT COUNT(*) FROM event_team_members WHERE team_id = :team_id');
            $stmt->execute(['team_id' => $teamId]);
            $currentSize = (int) $stmt->fetchColumn();
            if ($currentSize >= (int) $event['max_team_size']) {
                throw new RuntimeException('Team size limit reached. Maximum allowed: ' . $event['max_team_size']);
            }

            // Insert into event_team_members
            $stmt = db()->prepare('INSERT INTO event_team_members (team_id, user_id, is_leader, joined_at) VALUES (:team_id, :user_id, 0, NOW())');
            $stmt->execute(['team_id' => $teamId, 'user_id' => $memberId]);

            // Insert into event_registrations
            $stmt = db()->prepare(
                "INSERT INTO event_registrations (event_id, user_id, team_id, registration_type, status, registered_at)
                 VALUES (:event_id, :user_id, :team_id, 'team', 'registered', NOW())"
            );
            $stmt->execute(['event_id' => $eventId, 'user_id' => $memberId, 'team_id' => $teamId]);

            // Security: Record override log
            AuditService::record('team_member_added', 'events', (int) $user['id'], 'users', $memberId);
            flash('success', 'Team member added successfully.');
        }

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        flash('error', $e->getMessage());
    }

    redirect('events/override-team.php?team_id=' . $teamId . '&event_id=' . $eventId);
}

// Fetch team members list
$stmt = db()->prepare('
    SELECT etm.*, u.full_name, u.email, u.phone
    FROM event_team_members etm
    INNER JOIN users u ON u.id = etm.user_id
    WHERE etm.team_id = :team_id
    ORDER BY etm.is_leader DESC, u.full_name ASC
');
$stmt->execute(['team_id' => $teamId]);
$members = $stmt->fetchAll();

$title = 'Manage Team | ' . app_config('name');
$navItems = dashboard_nav($user['role_key']);
require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
?>

<style>
.autocomplete-wrapper {
    position: relative;
    width: 100%;
}
.autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    background: #ffffff;
    border: 1px solid #111111;
    border-radius: 4px;
    max-height: 220px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.autocomplete-item {
    padding: 10px 14px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.autocomplete-item:hover {
    background: #f4f4f5;
}
</style>

<div class="dashboard-header">
    <h2>Override Team Composition</h2>
    <div class="actions">
        <a href="<?= h(url('events/registrations.php?id=' . $eventId)) ?>" class="button button-outline">Back to Registrations</a>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
    <!-- Roster Panel -->
    <div class="card">
        <h3>Team Roster: <?= h($team['team_name']) ?> (<?= h($team['team_identifier']) ?>)</h3>
        <p class="muted">Min Size: <?= h((string) $event['min_team_size']) ?> | Max Size: <?= h((string) $event['max_team_size']) ?> | Current Size: <?= count($members) ?></p>

        <?php if (count($members) < (int) $event['min_team_size']): ?>
            <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                Warning: Team is below the minimum required size of <?= h((string) $event['min_team_size']) ?>.
            </div>
        <?php endif; ?>

        <div style="overflow-x: auto; margin-top: 1.5rem;">
            <table class="data-table" style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #eee;">
                        <th style="padding: 0.75rem;">Member</th>
                        <th style="padding: 0.75rem;">Role</th>
                        <th style="padding: 0.75rem; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 0.75rem;">
                                <strong><?= h($m['full_name']) ?></strong><br>
                                <small style="color: #666;"><?= h($m['email']) ?></small>
                            </td>
                            <td style="padding: 0.75rem;">
                                <?php if ((int) $m['is_leader'] === 1): ?>
                                    <span class="badge" style="background: #000; color: #fff; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8em; font-weight: bold;">Leader</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #e9ecef; color: #333; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8em;">Member</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem; text-align: right;">
                                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                    <?php if ((int) $m['is_leader'] !== 1): ?>
                                        <form method="post" style="margin: 0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                            <input type="hidden" name="team_id" value="<?= $teamId ?>">
                                            <input type="hidden" name="user_id" value="<?= $m['user_id'] ?>">
                                            <button type="submit" name="action" value="make_leader" class="button button-small" style="background: #e0a800; color: #000; font-size: 0.8em; padding: 0.25rem 0.5rem;">Promote</button>
                                        </form>

                                        <form method="post" style="margin: 0;" onsubmit="return confirm('Remove this member from the team?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                            <input type="hidden" name="team_id" value="<?= $teamId ?>">
                                            <input type="hidden" name="user_id" value="<?= $m['user_id'] ?>">
                                            <button type="submit" name="action" value="remove_member" class="button button-small" style="background: #dc3545; color: #fff; font-size: 0.8em; padding: 0.25rem 0.5rem;">Remove</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted" style="font-size: 0.9em;">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Member Panel -->
    <div class="card">
        <h3>Add Member (Override)</h3>
        <p class="muted">Search and force add any active club member to this team structure.</p>
        
        <form method="post" id="add_member_form" style="margin-top: 1.5rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="event_id" value="<?= $eventId ?>">
            <input type="hidden" name="team_id" value="<?= $teamId ?>">
            <input type="hidden" name="user_id" id="hidden_add_user_id" value="">
            <input type="hidden" name="action" value="add_member">

            <div class="field">
                <label for="member_search_input">Search User</label>
                <div class="autocomplete-wrapper">
                    <input id="member_search_input" type="text" class="form-control" placeholder="Search by name, email, roll number..." autocomplete="off">
                    <div id="search_results_dropdown" class="autocomplete-dropdown" style="display: none;"></div>
                </div>
            </div>

            <div id="selected_user_preview" style="display: none; border: 1px solid #111; padding: 1rem; border-radius: 4px; background: #fafafa; margin-bottom: 1.5rem;">
                <h4 style="margin: 0 0 0.5rem 0;" id="preview_name">Selected User</h4>
                <div style="font-size: 0.9em; color: #555;" id="preview_details">Email / Roll No</div>
            </div>

            <button type="submit" id="add_member_btn" class="button" disabled style="width: 100%;">Add Member to Team</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('member_search_input');
    const dropdown = document.getElementById('search_results_dropdown');
    const hiddenUserId = document.getElementById('hidden_add_user_id');
    const previewDiv = document.getElementById('selected_user_preview');
    const previewName = document.getElementById('preview_name');
    const previewDetails = document.getElementById('preview_details');
    const submitBtn = document.getElementById('add_member_btn');

    const currentMemberIds = [<?= implode(',', array_column($members, 'user_id')) ?>];

    if (searchInput) {
        let debounceTimer;

        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const query = searchInput.value.trim();

            if (query.length < 2) {
                dropdown.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`<?= h(url('api/members.php?q=')) ?>${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        dropdown.innerHTML = '';
                        if (data.length === 0) {
                            const item = document.createElement('div');
                            item.className = 'autocomplete-item';
                            item.innerHTML = '<span>No active members found.</span>';
                            dropdown.appendChild(item);
                        } else {
                            data.forEach(m => {
                                // Skip if already in the team
                                if (currentMemberIds.includes(parseInt(m.id))) {
                                    return;
                                }

                                const item = document.createElement('div');
                                item.className = 'autocomplete-item';
                                item.innerHTML = `
                                    <div>
                                        <strong>${escapeHtml(m.full_name)}</strong>
                                        <div style="font-size:0.85em; color:#666;">${escapeHtml(m.email)}</div>
                                    </div>
                                    <span>Select</span>
                                `;
                                item.addEventListener('click', () => selectUser(m));
                                dropdown.appendChild(item);
                            });
                        }
                        dropdown.style.display = 'block';
                    })
                    .catch(err => console.error('Error searching members:', err));
            }, 300);
        });

        // Close dropdown on click outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }

    function selectUser(user) {
        hiddenUserId.value = user.id;
        previewName.innerText = user.full_name;
        previewDetails.innerText = `${user.email} | Roll Number: ${user.roll_number || 'N/A'}`;
        previewDiv.style.display = 'block';
        submitBtn.removeAttribute('disabled');
        dropdown.style.display = 'none';
        searchInput.value = '';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#039;');
    }
});
</script>

<?php require BASE_PATH . '/app/Views/layouts/dashboard_footer.php'; ?>
