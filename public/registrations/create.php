<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';
require_once BASE_PATH . '/app/Middleware/auth.php';
require_once BASE_PATH . '/app/Views/dashboard/components.php';

$user = require_auth();
$event = Event::findById((int) ($_GET['event_id'] ?? $_POST['event_id'] ?? 0));

if (!$event || $event['status'] !== 'published') {
    flash('error', 'Event is not available for registration.');
    redirect('events/index.php');
}

if (request_method_is('POST')) {
    verify_csrf();
    $result = EventService::register([
        'event_id' => (int) $event['id'],
        'registration_type' => input_string('registration_type'),
        'team_name' => input_string('team_name'),
        'saved_team_id' => input_string('saved_team_id'),
        'member_ids' => input_string('member_ids'),
    ], $user);

    if (!$result['ok']) {
        flash('error', $result['message']);
        redirect('registrations/create.php?event_id=' . (int) $event['id']);
    }

    flash('success', $result['message']);
    redirect('registrations/history.php');
}

$savedTeams = EventRegistration::savedTeamsForUser((int) $user['id']);
$title = 'Register for Event | ' . app_config('name');
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
.autocomplete-item:hover, .autocomplete-item.active {
    background: #f4f4f5;
}
.autocomplete-item strong {
    font-size: 0.95em;
    color: #111;
}
.autocomplete-item span {
    font-size: 0.85em;
    color: #666;
}
.selected-member-badge {
    background: #18181b;
    color: #ffffff;
    border-radius: 4px;
    padding: 6px 12px;
    font-size: 0.9em;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-right: 8px;
    margin-bottom: 8px;
    border: 1px solid #27272a;
}
.selected-member-badge button {
    background: none;
    border: none;
    color: #a1a1aa;
    cursor: pointer;
    font-size: 1.1em;
    padding: 0;
    line-height: 1;
}
.selected-member-badge button:hover {
    color: #ef4444;
}
</style>

<div class="dashboard-header">
    <h2>Event Registration</h2>
    <div class="actions">
        <a href="<?= h(url('events/index.php')) ?>" class="button button-outline">Back to Events</a>
    </div>
</div>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2><?= h($event['title']) ?></h2>
            <p><?= h($event['description']) ?></p>
        </div>
    </div>

    <form method="post" action="<?= h(url('registrations/create.php?event_id=' . (int) $event['id'])) ?>" id="registration_form">
        <?= csrf_field() ?>
        <input type="hidden" name="event_id" value="<?= h((string) $event['id']) ?>">
        <input type="hidden" name="member_ids" id="hidden_member_ids" value="">

        <div class="field">
            <label for="registration_type">Registration Mode</label>
            <select id="registration_type" name="registration_type" class="form-control" style="max-width: 300px;">
                <option value="individual">Individual Participant</option>
                <?php if ((int) $event['team_allowed'] === 1): ?>
                    <option value="team">Team Participation</option>
                <?php endif; ?>
            </select>
        </div>

        <?php if ((int) $event['team_allowed'] === 1): ?>
            <!-- Team Specific Fields -->
            <div id="team_fields" style="display: none;">
                <div class="field">
                    <label for="team_name">Team Name</label>
                    <input id="team_name" name="team_name" class="form-control" placeholder="Enter a unique name for your team">
                </div>

                <div class="field">
                    <label for="saved_team_id">Select Previously Saved Team</label>
                    <select id="saved_team_id" name="saved_team_id" class="form-control">
                        <option value="">-- Create new team structure manually --</option>
                        <?php foreach ($savedTeams as $savedTeam): ?>
                            <option value="<?= h((string) $savedTeam['id']) ?>"><?= h($savedTeam['team_name']) ?> (<?= h((string) $savedTeam['member_count']) ?> members)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Custom Autocomplete Member Selector -->
                <div id="manual_members_section">
                    <div class="field">
                        <label for="member_search_input">Add Team Members</label>
                        <div class="autocomplete-wrapper">
                            <input id="member_search_input" type="text" class="form-control" placeholder="Search registered members by name or email..." autocomplete="off">
                            <div id="search_results_dropdown" class="autocomplete-dropdown" style="display: none;"></div>
                        </div>
                        <p class="muted" style="margin-top: 4px;">Search and add active club members. Leader (you) is added automatically.</p>
                    </div>

                    <div class="field">
                        <label>Selected Team Members</label>
                        <div id="selected_members_container" style="min-height: 40px; border: 1px dashed #ccc; border-radius: 4px; padding: 10px; background: #fafafa;">
                            <div style="color: #888; font-size: 0.9em;" id="no_members_placeholder">No additional members added yet.</div>
                            <div id="selected_members_list"></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 2rem;">
            <button class="button" type="submit">Submit Registration</button>
        </div>
    </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const regType = document.getElementById('registration_type');
    const teamFields = document.getElementById('team_fields');
    const teamNameInput = document.getElementById('team_name');
    const savedTeamSelect = document.getElementById('saved_team_id');
    const manualMembersSection = document.getElementById('manual_members_section');
    
    // Autocomplete elements
    const searchInput = document.getElementById('member_search_input');
    const dropdown = document.getElementById('search_results_dropdown');
    const selectedList = document.getElementById('selected_members_list');
    const placeholder = document.getElementById('no_members_placeholder');
    const hiddenIds = document.getElementById('hidden_member_ids');

    let selectedMembers = [];
    const minSize = <?= (int) ($event['min_team_size'] ?? 2) ?>;
    const maxSize = <?= (int) ($event['max_team_size'] ?? 10) ?>;

    // Toggle registration fields
    if (regType && teamFields) {
        regType.addEventListener('change', () => {
            if (regType.value === 'team') {
                teamFields.style.display = 'block';
                teamNameInput.required = true;
            } else {
                teamFields.style.display = 'none';
                teamNameInput.required = false;
            }
        });
    }

    // Toggle saved team selection
    if (savedTeamSelect) {
        savedTeamSelect.addEventListener('change', () => {
            if (savedTeamSelect.value !== '') {
                manualMembersSection.style.display = 'none';
            } else {
                manualMembersSection.style.display = 'block';
            }
        });
    }

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
                                // Check if already selected
                                if (selectedMembers.some(sel => sel.id === m.id)) {
                                    return;
                                }
                                
                                const item = document.createElement('div');
                                item.className = 'autocomplete-item';
                                item.innerHTML = `
                                    <div>
                                        <strong>${escapeHtml(m.full_name)}</strong>
                                        <div style="font-size:0.8em; color:#666;">${escapeHtml(m.email)}</div>
                                    </div>
                                    <span>Add</span>
                                `;
                                item.addEventListener('click', () => addMember(m));
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

    function addMember(member) {
        if (selectedMembers.length + 1 >= maxSize) {
            alert(`Maximum team size is ${maxSize} members including yourself.`);
            return;
        }
        
        selectedMembers.push(member);
        renderSelectedMembers();
        dropdown.style.display = 'none';
        searchInput.value = '';
    }

    function removeMember(memberId) {
        selectedMembers = selectedMembers.filter(m => m.id !== memberId);
        renderSelectedMembers();
    }

    function renderSelectedMembers() {
        selectedList.innerHTML = '';
        if (selectedMembers.length === 0) {
            placeholder.style.display = 'block';
        } else {
            placeholder.style.display = 'none';
            selectedMembers.forEach(m => {
                const badge = document.createElement('span');
                badge.className = 'selected-member-badge';
                badge.innerHTML = `
                    ${escapeHtml(m.full_name)}
                    <button type="button" data-id="${m.id}">&times;</button>
                `;
                badge.querySelector('button').addEventListener('click', () => removeMember(m.id));
                selectedList.appendChild(badge);
            });
        }
        
        // Sync ids
        const ids = selectedMembers.map(m => m.id);
        hiddenIds.value = ids.join(',');
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
