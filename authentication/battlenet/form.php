<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string|null $error
 * @var string|null $message
 * @var bool|null $show_email
 * @var string|null $username
 * @var Concrete\Core\Validation\CSRF\Token|null $token
 */

if (!empty($error)) {
    ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
    <?php
}
if (!empty($message)) {
    ?>
    <div class="alert alert-success"><?= h($message) ?></div>
    <?php
}

if (!empty($show_email)) {
    ?>
    <form method="post" action="<?= URL::to('/login/callback/battlenet/handle_register') ?>">
        <p><?= t('Register an account for "%s"', h($username)) ?></p>
        <div class="input-group">
            <input type="email" name="uEmail" placeholder="<?= t('Email Address') ?>" class="form-control" required />
            <button class="btn btn-primary"><?= t('Register') ?></button>
        </div>
        <?= $token->output('battlenet_register') ?>
    </form>
    <?php
} else {
    ?>
    <div class="form-group external-auth-option">
        <div class="d-grid">
            <a href="<?= URL::to('/ccm/system/authentication/oauth2/battlenet/attempt_auth') ?>" class="btn btn-battlenet">
                <i class="fab fa-battle-net"></i>
                <?= t('Log in with %s', 'Battle.net') ?>
            </a>
        </div>
    </div>
    <?php
}
?>
<style>
    .btn-battlenet,
    .ccm-ui .btn-battlenet {
        color: #fff;
        background-color: #148eff;
    }
    .btn-battlenet:focus,
    .btn-battlenet:hover,
    .ccm-ui .btn-battlenet:focus,
    .ccm-ui .btn-battlenet:hover {
        color: #fff;
        background-color: #0074e0;
    }
    .btn-battlenet .fa-battle-net {
        margin: 0 6px 0 3px;
    }
</style>
