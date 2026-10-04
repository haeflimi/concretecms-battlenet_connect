<?php

defined('C5_EXECUTE') or die('Access Denied.');

$user = app(Concrete\Core\User\User::class);
$battlenetId = $this->controller->getBindingForUser($user);
$profile = $this->controller->getProfile($user);
?>

<div class="form-group">
    <span><?= t('Detach your %s account', 'Battle.net') ?></span>
    <hr>
</div>
<div class="form-group">
    <a href="<?= URL::to('/ccm/system/authentication/oauth2/battlenet/attempt_detach') ?>" class="btn btn-battlenet">
        <i class="fab fa-battle-net"></i>
        <?= t('Detach your %s account', 'Battle.net') ?>
    </a>
    <?php if ($battlenetId) { ?>
        <div class="form-text">
            <?= t('Connected Battle.net account: %s', h($profile !== null ? $profile->getDisplayName() : $battlenetId)) ?>
        </div>
    <?php } ?>
</div>

<style>
    .btn-battlenet,
    .ccm-ui .btn-battlenet {
        color: #fff;
        background-color: #148eff;
    }
    .btn-battlenet:hover,
    .ccm-ui .btn-battlenet:hover {
        color: #fff;
        background-color: #0074e0;
    }
    .btn-battlenet .fa-battle-net {
        margin: 0 6px 0 3px;
    }
</style>
