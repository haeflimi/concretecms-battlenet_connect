<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var bool $isMember
 * @var string $sort
 * @var string $heading
 * @var string $subtitle
 * @var array[] $rows rank, character, url, description, realm, version, owner, value, valueLabel, lastPlayed
 */

use BattlenetConnect\Wow\WowRoster;

$numbers = app('helper/number');
// A bar only makes sense for values that start at zero
$showBar = in_array($sort, [WowRoster::SORT_MYTHIC_RATING, WowRoster::SORT_ACHIEVEMENT_POINTS, WowRoster::SORT_PROGRESS], true);
$max = $rows ? max(array_map('floatval', array_column($rows, 'value'))) : 0;
$name = static function (array $row): string {
    $html = h($row['character']->getName());

    return $row['url'] ? '<a href="' . h($row['url']) . '" target="_blank" rel="noopener">' . $html . '</a>' : $html;
};
?>
<div class="wow-characters<?= $isMember ? ' wow-characters-cards' : '' ?>">
    <div class="wow-characters-head">
        <?php if ($heading !== '') { ?>
            <h3 class="wow-characters-title"><?= h($heading) ?></h3>
        <?php } ?>
        <?php if ($subtitle !== '') { ?>
            <p class="wow-characters-subtitle"><?= h($subtitle) ?></p>
        <?php } ?>
    </div>

    <?php if (!$rows) { ?>
        <p class="wow-characters-empty"><?= t('No characters yet.') ?></p>
    <?php } elseif ($isMember) { ?>
        <div class="wow-characters-grid">
            <?php foreach ($rows as $row) {
                $character = $row['character'];
                $image = $character->getInsetUrl() ?: $character->getAvatarUrl();
                ?>
                <article class="wow-character-card" style="--wow-class: <?= h($character->getClassColor() ?: 'var(--wc-border)') ?>">
                    <?php if ($image) { ?>
                        <img class="wow-character-card-image" src="<?= h($image) ?>" alt="" loading="lazy">
                    <?php } ?>
                    <div class="wow-character-card-body">
                        <h4 class="wow-character-card-name"><?= $name($row) ?></h4>
                        <p class="wow-character-card-meta">
                            <?= h(implode(' · ', array_filter([$character->getLevel() ? t('Level %s', $character->getLevel()) : null, $row['description']]))) ?>
                        </p>
                        <p class="wow-character-card-meta">
                            <?= h(implode(' · ', array_filter([$character->getGuildName() ? '<' . $character->getGuildName() . '>' : null, $row['realm'], $row['version']]))) ?>
                        </p>
                        <dl class="wow-character-card-stats">
                            <?php if ($character->getItemLevel()) { ?>
                                <div><dt><?= t('Item level') ?></dt><dd><?= (int) $character->getItemLevel() ?></dd></div>
                            <?php } ?>
                            <?php if ($character->getMythicRating()) { ?>
                                <div><dt><?= t('Mythic+') ?></dt><dd style="<?= $character->getMythicRatingColor() ? 'color: ' . h($character->getMythicRatingColor()) : '' ?>"><?= $numbers->format($character->getMythicRating(), 0) ?></dd></div>
                            <?php } ?>
                            <?php if ($character->getAchievementPoints()) { ?>
                                <div><dt><?= t('Achievements') ?></dt><dd><?= $numbers->format($character->getAchievementPoints(), 0) ?></dd></div>
                            <?php } ?>
                            <?php if ($row['lastPlayed']) { ?>
                                <div><dt><?= t('Last played') ?></dt><dd><?= h($row['lastPlayed']) ?></dd></div>
                            <?php } ?>
                        </dl>
                    </div>
                </article>
            <?php } ?>
        </div>
    <?php } else { ?>
        <ol class="wow-characters-list">
            <?php foreach ($rows as $row) {
                $character = $row['character'];
                $width = $showBar && $max > 0 ? max(2, round((float) $row['value'] / $max * 100, 1)) : 0;
                $meta = array_filter([$character->getLevel() ? t('Level %s', $character->getLevel()) : null, $row['description'], $row['realm'], $row['version'], $row['owner']]);
                ?>
                <li class="wow-characters-row<?= $row['rank'] <= 3 ? ' wow-characters-top' : '' ?><?= $showBar ? ' wow-characters-has-bar' : '' ?>" style="--wow-class: <?= h($character->getClassColor() ?: 'var(--wc-border)') ?>">
                    <span class="wow-characters-rank"><?= (int) $row['rank'] ?></span>
                    <?php if ($character->getAvatarUrl()) { ?>
                        <img class="wow-characters-image" src="<?= h($character->getAvatarUrl()) ?>" alt="" width="40" height="40" loading="lazy">
                    <?php } else { ?>
                        <span class="wow-characters-image wow-characters-image-empty" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($character->getName(), 0, 1))) ?></span>
                    <?php } ?>
                    <span class="wow-characters-main">
                        <span class="wow-characters-name"><?= $name($row) ?></span>
                        <span class="wow-characters-meta"><?= h(implode(' · ', $meta)) ?></span>
                    </span>
                    <span class="wow-characters-value"<?= $sort === WowRoster::SORT_MYTHIC_RATING && $character->getMythicRatingColor() ? ' style="color: ' . h($character->getMythicRatingColor()) . '"' : '' ?>><?= h($row['valueLabel']) ?></span>
                    <?php if ($showBar) { ?>
                        <span class="wow-characters-bar" aria-hidden="true"><span style="width: <?= $width ?>%"></span></span>
                    <?php } ?>
                </li>
            <?php } ?>
        </ol>
    <?php } ?>
</div>
