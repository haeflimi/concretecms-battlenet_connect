<?php

namespace Concrete\Package\BattlenetConnect;

use Concrete\Core\Authentication\AuthenticationType;
use Concrete\Core\Database\EntityManager\Provider\ProviderAggregateInterface;
use Concrete\Core\Database\EntityManager\Provider\StandardPackageProvider;
use Concrete\Core\Logging\Channels;
use Concrete\Core\Package\Package;
use BattlenetConnect\BattlenetAccounts;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends Package implements ProviderAggregateInterface
{
    protected $pkgHandle = 'battlenet_connect';
    protected $appVersionRequired = '9.0.0';
    protected $pkgVersion = '1.0.0';
    protected $pkgAutoloaderRegistries = [
        'src' => 'BattlenetConnect',
    ];

    public function getPackageName()
    {
        return t('Battle.net Connect');
    }

    public function getPackageDescription()
    {
        return t('Adds an Authenticator for Blizzard\'s Battle.net gaming platform.');
    }

    public function getEntityManagerProvider()
    {
        return new StandardPackageProvider($this->app, $this, [
            'src/Entity' => 'BattlenetConnect\Entity',
        ]);
    }

    public function install()
    {
        $pkg = parent::install();
        $this->installAuthenticationType($pkg);

        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->installAuthenticationType($this->getPackageEntity());
        $this->removeInvalidBindings();
    }

    public function uninstall()
    {
        $type = $this->getAuthenticationType();
        if ($type !== null) {
            $type->delete();
        }
        parent::uninstall();
    }

    protected function installAuthenticationType($pkg): void
    {
        // Checked in the database: loading the type would need its controller, which is not available while installing.
        // Versions before 1.0 installed the type already: it's kept with its settings (auth.battlenet.*) and enabled state.
        if (!$this->app->make('database')->connection()->fetchOne("SELECT 1 FROM AuthenticationTypes WHERE authTypeHandle = 'battlenet'")) {
            // Installed disabled: it has to be configured and enabled in /dashboard/system/registration/authentication.
            AuthenticationType::add('battlenet', 'Battle.net', 0, $pkg)->disable();
        }
    }

    /**
     * Versions before 1.0 stored the OAuth access token as binding instead of the Battle.net account ID. Those
     * bindings can't be resolved to an account: the users have to attach their Battle.net account again.
     */
    protected function removeInvalidBindings(): void
    {
        $accounts = $this->app->make(BattlenetAccounts::class);
        $removed = $accounts->removeInvalidBindings();
        $accounts->pruneUnlinkedAccounts();
        if ($removed > 0) {
            $this->app->make('log/factory')->createLogger(Channels::CHANNEL_PACKAGES)->notice(
                t('Battle.net Connect removed %s invalid Battle.net account links of an old version. These users have to attach their Battle.net account again.', $removed)
            );
        }
    }

    protected function getAuthenticationType(): ?AuthenticationType
    {
        try {
            $type = AuthenticationType::getByHandle('battlenet');
        } catch (Throwable $e) {
            return null;
        }

        return is_object($type) && !$type->isError() ? $type : null;
    }
}
