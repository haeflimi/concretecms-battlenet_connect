<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Form\Service\Widget\UserSelector $userSelector
 * @var string|null $title
 * @var string $mode
 * @var string $memberSource
 * @var int $memberID
 * @var string|null $version
 * @var string $sortBy
 * @var int $progressDays
 * @var int $minLevel
 * @var int $maxItems
 * @var bool|int $onePerUser
 * @var bool|int $showOwner
 * @var array $modeOptions
 * @var array $memberSourceOptions
 * @var array $versionOptions
 * @var array $sortOptions
 * @var array $progressDaysOptions
 * @var bool $wowEnabled
 */
?>
<?php if (!$wowEnabled) { ?>
    <div class="alert alert-warning">
        <?= t('No World of Warcraft version is selected in the <a href="%s" target="_blank">Battle.net authentication type</a>: there are no characters to show.', URL::to('/dashboard/system/registration/authentication')) ?>
    </div>
<?php } ?>

<div class="form-group">
    <?= $form->label('mode', t('Show')) ?>
    <?= $form->select('mode', $modeOptions, $mode) ?>
</div>
<div class="wow-characters-member">
    <div class="form-group">
        <?= $form->label('memberSource', t('Member')) ?>
        <?= $form->select('memberSource', $memberSourceOptions, $memberSource) ?>
        <div class="form-text wow-characters-profile-help"><?= t('Place the block on the public member profile page (/members/profile) to show the characters of every member.') ?></div>
    </div>
    <div class="form-group wow-characters-fixed">
        <?= $form->label('memberID', t('Choose member')) ?>
        <?= $userSelector->selectUser('memberID', (int) $memberID ?: false) ?>
    </div>
</div>
<div class="form-group">
    <?= $form->label('title', t('Title')) ?>
    <?= $form->text('title', (string) $title, ['placeholder' => t('Default: the order')]) ?>
</div>
<div class="row">
    <div class="col-sm-6 form-group">
        <?= $form->label('version', t('WoW version')) ?>
        <?= $form->select('version', $versionOptions, (string) $version) ?>
    </div>
    <div class="col-sm-6 form-group">
        <?= $form->label('sortBy', t('Order by')) ?>
        <?= $form->select('sortBy', $sortOptions, $sortBy) ?>
    </div>
</div>
<div class="form-group wow-characters-progress">
    <?= $form->label('progressDays', t('Period')) ?>
    <?= $form->select('progressDays', $progressDaysOptions, (int) $progressDays) ?>
    <div class="form-text"><?= t('Calculated from the data collected by the Sync Battle.net Data task, so it only covers the time since its first run. Classic Era has no achievements.') ?></div>
</div>
<div class="row">
    <div class="col-sm-6 form-group">
        <?= $form->label('minLevel', t('Minimum level')) ?>
        <?= $form->number('minLevel', (int) $minLevel, ['min' => 0, 'max' => 200]) ?>
    </div>
    <div class="col-sm-6 form-group">
        <?= $form->label('maxItems', t('Number of characters')) ?>
        <?= $form->number('maxItems', (int) $maxItems, ['min' => 1, 'max' => 200]) ?>
    </div>
</div>
<div class="wow-characters-roster">
    <div class="form-check">
        <?= $form->checkbox('onePerUser', '1', (bool) $onePerUser) ?>
        <label class="form-check-label" for="onePerUser"><?= t('Only the best character of every member') ?></label>
    </div>
    <div class="form-check">
        <?= $form->checkbox('showOwner', '1', (bool) $showOwner) ?>
        <label class="form-check-label" for="showOwner"><?= t('Show the user name of the members') ?></label>
    </div>
</div>

<script>
$(function () {
    var $mode = $('#mode'), $source = $('#memberSource'), $sort = $('#sortBy');
    function update() {
        var member = $mode.val() === 'member';
        $('.wow-characters-member').toggle(member);
        $('.wow-characters-roster').toggle(!member);
        $('.wow-characters-fixed').toggle(member && $source.val() === 'fixed');
        $('.wow-characters-profile-help').toggle($source.val() === 'profile');
        $('.wow-characters-progress').toggle($sort.val() === 'progress');
    }
    $mode.add($source).add($sort).on('change', update);
    update();
});
</script>
