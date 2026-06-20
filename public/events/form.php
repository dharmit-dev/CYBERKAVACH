<?php
$eventTags = $event ? implode(', ', Event::tagsForEvent((int) $event['id'])) : '';
?>
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2><?= $event ? 'Edit Event' : 'Create Event' ?></h2>
            <p>Save as draft first, then submit for approval when ready.</p>
        </div>
    </div>

    <form method="post" action="<?= h(url('events/save.php')) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <?php if ($event): ?><input type="hidden" name="id" value="<?= h((string) $event['id']) ?>"><?php endif; ?>

        <div class="form-grid">
            <div class="field">
                <label for="title">Event name</label>
                <input id="title" name="title" value="<?= h(old('title', $event['title'] ?? '')) ?>" required>
                <?php if (isset($pageErrors['title'])): ?><div class="field-error"><?= h($pageErrors['title']) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <?php $selected = old('category_id', (string) ($event['category_id'] ?? '')) === (string) $category['id']; ?>
                        <option value="<?= h((string) $category['id']) ?>" <?= $selected ? 'selected' : '' ?>><?= h($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field field-wide">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" required><?= h(old('description', $event['description'] ?? '')) ?></textarea>
            </div>

            <div class="field">
                <label for="event_date">Date</label>
                <input id="event_date" type="date" name="event_date" value="<?= h(old('event_date', $event['event_date'] ?? '')) ?>" required>
            </div>

            <div class="field">
                <label for="start_time">Start time</label>
                <input id="start_time" type="time" name="start_time" value="<?= h(old('start_time', isset($event['start_time']) ? substr($event['start_time'], 0, 5) : '')) ?>" required>
            </div>

            <div class="field">
                <label for="end_time">End time</label>
                <input id="end_time" type="time" name="end_time" value="<?= h(old('end_time', isset($event['end_time']) ? substr($event['end_time'], 0, 5) : '')) ?>" required>
            </div>

            <div class="field">
                <label for="venue">Venue</label>
                <input id="venue" name="venue" value="<?= h(old('venue', $event['venue'] ?? '')) ?>" required>
            </div>

            <div class="field">
                <label for="registration_deadline_date">Registration deadline date</label>
                <input id="registration_deadline_date" type="date" name="registration_deadline_date" value="<?= h(old('registration_deadline_date', isset($event['registration_deadline']) ? substr($event['registration_deadline'], 0, 10) : '')) ?>" required>
            </div>

            <div class="field">
                <label for="registration_deadline_time">Registration deadline time</label>
                <input id="registration_deadline_time" type="time" name="registration_deadline_time" value="<?= h(old('registration_deadline_time', isset($event['registration_deadline']) ? substr($event['registration_deadline'], 11, 5) : '')) ?>" required>
            </div>

            <div class="field">
                <label for="capacity">Capacity</label>
                <input id="capacity" type="number" min="1" name="capacity" value="<?= h(old('capacity', (string) ($event['capacity'] ?? ''))) ?>" required>
            </div>

            <div class="field">
                <label for="late_arrival_threshold_minutes">Late Arrival Threshold (mins)</label>
                <input id="late_arrival_threshold_minutes" type="number" min="0" name="late_arrival_threshold_minutes" value="<?= h(old('late_arrival_threshold_minutes', (string) ($event['late_arrival_threshold_minutes'] ?? '15'))) ?>" required>
            </div>

            <div class="field">
                <label for="early_exit_threshold_minutes">Early Exit Threshold (mins)</label>
                <input id="early_exit_threshold_minutes" type="number" min="0" name="early_exit_threshold_minutes" value="<?= h(old('early_exit_threshold_minutes', (string) ($event['early_exit_threshold_minutes'] ?? '15'))) ?>" required>
            </div>

            <div class="field">
                <label for="poster">Event poster</label>
                <input id="poster" type="file" name="poster" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($event['poster_path'])): ?><img class="poster-preview" src="<?= h(url($event['poster_path'])) ?>" alt="Event poster preview"><?php endif; ?>
                <?php if (isset($pageErrors['poster'])): ?><div class="field-error"><?= h($pageErrors['poster']) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label><input class="checkbox-inline" type="checkbox" name="team_allowed" value="1" <?= old('team_allowed', !empty($event['team_allowed']) ? '1' : '') === '1' ? 'checked' : '' ?>> Team allowed</label>
            </div>

            <div class="field">
                <label for="min_team_size">Min team size</label>
                <input id="min_team_size" type="number" min="2" name="min_team_size" value="<?= h(old('min_team_size', (string) ($event['min_team_size'] ?? ''))) ?>">
            </div>

            <div class="field">
                <label for="max_team_size">Max team size</label>
                <input id="max_team_size" type="number" min="2" name="max_team_size" value="<?= h(old('max_team_size', (string) ($event['max_team_size'] ?? ''))) ?>">
                <?php if (isset($pageErrors['max_team_size'])): ?><div class="field-error"><?= h($pageErrors['max_team_size']) ?></div><?php endif; ?>
            </div>

            <div class="field field-wide">
                <label for="event_rules">Event rules</label>
                <textarea id="event_rules" name="event_rules" rows="4"><?= h(old('event_rules', $event['event_rules'] ?? '')) ?></textarea>
            </div>

            <div class="field field-wide">
                <label for="tags">Tags</label>
                <input id="tags" name="tags" value="<?= h(old('tags', $eventTags)) ?>" placeholder="cybersecurity, workshop, awareness">
            </div>
        </div>

        <div class="button-row">
            <button class="button button-small" type="submit" name="action" value="save">Save draft</button>
            <?php if ($event && in_array($event['status'], ['draft', 'rejected'], true)): ?>
                <button class="button button-small button-approve" type="submit" name="action" value="submit" style="margin-left: 0.5rem;">Save & Submit for approval</button>
            <?php elseif ($event && in_array($event['status'], ['approved', 'published', 'pending_approval', 'under_review'], true)): ?>
                <button class="button button-small button-approve" type="submit" name="action" value="submit" style="margin-left: 0.5rem; background: #e0a800; color: #000;">Save & Request Re-approval</button>
            <?php endif; ?>
            <a class="button button-small button-return" href="<?= h(url('events/manage.php')) ?>" style="margin-left: 0.5rem;">Cancel</a>
        </div>
    </form>
</section>
