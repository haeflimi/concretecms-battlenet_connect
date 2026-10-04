<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<div class="form-group">
    <span><?= t('Attach a %s account', 'Battle.net') ?></span>
    <hr>
</div>
<div class="form-group">
    <a href="<?= URL::to('/ccm/system/authentication/oauth2/battlenet/attempt_attach') ?>" class="btn btn-battlenet">
        <i class="fab fa-battle-net"></i>
        <?= t('Attach a %s account', 'Battle.net') ?>
    </a>
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
