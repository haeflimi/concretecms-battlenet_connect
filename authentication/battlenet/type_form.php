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
