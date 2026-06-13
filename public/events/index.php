<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/bootstrap.php';

$user = current_user();
$categoryId = (int) ($_GET['category_id'] ?? 0) ?: null;
$search = trim((string) ($_GET['q'] ?? ''));
$events = Event::listPublished($categoryId, $search);
$categories = Event::categories();

$title = 'Published Events | ' . app_config('name');
if ($user) {
    require_once BASE_PATH . '/app/Views/dashboard/components.php';
    $navItems = dashboard_nav($user['role_key']);
    require BASE_PATH . '/app/Views/layouts/dashboard_header.php';
} else {
    require BASE_PATH . '/app/Views/layouts/header.php';
}
?>
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Published Events</h2>
            <p>Browse approved CyberKavach events and register before the deadline.</p>
        </div>
        <?php if (!$user): ?><a class="button button-small" href="<?= h(url('login.php')) ?>">Login to register</a><?php endif; ?>
    </div>

    <form class="inline-filter" method="get" action="<?= h(url('events/index.php')) ?>">
        <input name="q" value="<?= h($search) ?>" placeholder="Search events">
        <select name="category_id">
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= h((string) $category['id']) ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= h($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-small" type="submit">Filter</button>
    </form>

    <div class="event-grid">
        <?php if ($events === []): ?><p>No published events found.</p><?php endif; ?>
        <?php foreach ($events as $event): ?>
            <article class="event-card">
                <?php if (!empty($event['poster_path'])): ?><img src="<?= h(url($event['poster_path'])) ?>" alt="<?= h($event['title']) ?> poster"><?php endif; ?>
                <div>
                    <span class="muted"><?= h($event['category_name']) ?></span>
                    <h3><?= h($event['title']) ?></h3>
                    <p><?= h(substr($event['description'], 0, 140)) ?><?= strlen($event['description']) > 140 ? '...' : '' ?></p>
                    <div class="tag-row">
                        <?php foreach (Event::tagsForEvent((int) $event['id']) as $tag): ?><span><?= h($tag) ?></span><?php endforeach; ?>
                    </div>
                    <a class="button button-small" href="<?= h(url('events/show.php?slug=' . urlencode($event['slug']))) ?>">View details</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php
if ($user) {
    require BASE_PATH . '/app/Views/layouts/dashboard_footer.php';
} else {
    require BASE_PATH . '/app/Views/layouts/footer.php';
}
?>
