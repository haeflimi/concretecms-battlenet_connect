<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Widget\GroupSelector $groupSelector
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $callbackUrl
 * @var string $clientId
 * @var string $clientSecret
 * @var string $region
 * @var array<string, string> $regions
 * @var array<string, string> $wowVersionNames
 * @var string[] $wowVersions
 * @var string $wowApiRegion
 * @var array<string, string> $wowApiRegions
 * @var string $wowLocale
 * @var array<string, string> $wowLocales
 * @var bool $registrationEnabled
 * @var int|null $registrationGroup
 */
?>

<div class="alert alert-info">
    <?= t('Create a client in the <a href="%s" target="_blank" rel="noopener">Blizzard Developer Portal</a>, copy its client ID and secret, and add this redirect URL:', 'https://develop.battle.net/access/clients') ?>
    <code class="d-block mt-1 user-select-all"><?= h($callbackUrl) ?></code>
</div>

<div class="form-group">
    <?= $form->label('client_id', t('Client ID')) ?>
    <?= $form->text('client_id', $clientId, ['autocomplete' => 'off', 'class' => 'font-monospace', 'spellcheck' => 'false']) ?>
</div>
<div class="form-group">
    <?= $form->label('client_secret', t('Client Secret')) ?>
    <?= $form->password('client_secret', $clientSecret, ['autocomplete' => 'off', 'class' => 'font-monospace', 'spellcheck' => 'false']) ?>
</div>
<div class="form-group">
    <?= $form->label('region', t('Region')) ?>
    <?= $form->select('region', $regions, $region) ?>
    <div class="form-text"><?= t('Accounts from China use a separate login server.') ?></div>
</div>

<fieldset>
    <legend><?= t('World of Warcraft') ?></legend>
    <div class="form-group">
        <?= $form->label('', t('Import the characters of')) ?>
        <?php foreach ($wowVersionNames as $version => $name) { ?>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="wow_versions[]" id="wow_version_<?= h($version) ?>" value="<?= h($version) ?>"<?= in_array($version, $wowVersions, true) ? ' checked' : '' ?>>
                <label class="form-check-label" for="wow_version_<?= h($version) ?>"><?= h($name) ?></label>
            </div>
        <?php } ?>
        <div class="form-text"><?= t('The characters are imported when users log in with Battle.net or attach their account (they are asked for access to their WoW profile), and updated by the "Sync Battle.net Data" task. Nothing is imported if no version is selected.') ?></div>
    </div>
    <div class="row">
        <div class="col-sm-6 form-group">
            <?= $form->label('wow_api_region', t('Game region')) ?>
            <?= $form->select('wow_api_region', $wowApiRegions, $wowApiRegion) ?>
        </div>
        <div class="col-sm-6 form-group">
            <?= $form->label('wow_locale', t('Language of names')) ?>
            <?= $form->select('wow_locale', $wowLocales, $wowLocale) ?>
        </div>
    </div>
</fieldset>

<fieldset>
    <legend><?= t('Registration') ?></legend>
    <div class="form-group">
        <div class="form-check">
            <?= $form->checkbox('registration_enabled', '1', $registrationEnabled) ?>
            <label class="form-check-label" for="registration_enabled"><?= t('Allow automatic registration') ?></label>
        </div>
        <div class="form-text"><?= t('Battle.net doesn\'t share email addresses: new users have to enter and confirm one.') ?></div>
    </div>
    <div class="form-group registration-group">
        <?= $form->label('registration_group', t('Group to enter on registration')) ?>
        <?= $groupSelector->selectGroup('registration_group', $registrationGroup, tc('Group', 'None')) ?>
    </div>
</fieldset>

<script>
$(function() {
    $('input[name="registration_enabled"]')
        .on('change', function () {
            $('div.registration-group').toggle($(this).is(':checked'));
        })
        .trigger('change');
});
</script>
