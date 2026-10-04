<?php

defined('C5_EXECUTE') or die('Access Denied.');

$user = app(Concrete\Core\User\User::class);
$battlenetId = $this->controller->getBindingForUser($user);
$profile = $this->controller->getProfile($user);
$characters = $this->controller->getBattlenetConfig()->isWowEnabled() ? $this->controller->getWowCharacters($user) : null;
$versionNames = $this->controller->getBattlenetConfig()->getWowVersionNames();
$showVersion = count($this->controller->getBattlenetConfig()->getWowVersions()) > 1;
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

<?php if ($characters !== null) { ?>
    <div class="form-group">
        <span><?= t('World of Warcraft characters') ?></span>
        <hr>
        <?php if ($characters === []) { ?>
            <p class="text-muted"><?= t('No characters found.') ?></p>
        <?php } else { ?>
            <ul class="list-unstyled battlenet-wow-characters">
                <?php foreach ($characters as $character) { ?>
                    <li>
                        <?php if ($character->getAvatarUrl()) { ?>
                            <img src="<?= h($character->getAvatarUrl()) ?>" alt="" width="24" height="24" loading="lazy">
                        <?php } ?>
                        <strong style="<?= $character->getClassColor() ? 'border-bottom: 2px solid ' . h($character->getClassColor()) : '' ?>"><?= h($character->getName()) ?></strong>
                        <span class="text-muted">
                            <?= h(implode(' · ', array_filter([
                                $character->getLevel() ? t('Level %s', $character->getLevel()) : null,
                                trim($character->getRaceName() . ' ' . $character->getClassName()),
                                $character->getRealmName(),
                                $showVersion ? ($versionNames[$character->getVersion()] ?? $character->getVersion()) : null,
                            ]))) ?>
                        </span>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
        <a href="<?= URL::to('/ccm/system/authentication/oauth2/battlenet/attempt_attach') ?>"><?= t('Refresh the list of characters') ?></a>
    </div>
<?php } ?>

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
    .battlenet-wow-characters li {
        margin-bottom: .375rem;
    }
    .battlenet-wow-characters img {
        border-radius: 50%;
        vertical-align: middle;
        margin-right: 4px;
    }
</style>
