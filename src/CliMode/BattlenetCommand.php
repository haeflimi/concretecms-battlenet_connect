<?php

namespace BattlenetConnect\CliMode;

use CliMode\Command\ClosureCommand;
use CliMode\Terminal\Context;
use Concrete\Core\Authentication\AuthenticationType;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * CLI Mode command "battlenet" (registered by the package controller when cli_mode is installed): the browser leaves the
 * terminal for the Battle.net login. Guests sign in, members connect their Battle.net account (or hear it is connected).
 */
class BattlenetCommand
{
    public static function make(): ClosureCommand
    {
        return ClosureCommand::make('battlenet', t('Sign in with %1$s or connect your %1$s account', 'Battle.net'), static function (Context $c, InputInterface $in, OutputInterface $out) {
            try {
                $type = AuthenticationType::getByHandle('battlenet');
            } catch (Throwable $e) {
                $type = null;
            }
            if (!$type || !$type->isEnabled()) {
                $out->writeln('<error> ' . t('%s login is switched off on this site.', 'Battle.net') . ' </error>');

                return 1;
            }
            $urls = $c->app->make('url/manager');
            if (!$c->isRegistered()) {
                $out->writeln(t('Off to %s. You come back signed in.', 'Battle.net'));
                $c->addAction('redirect', ['url' => (string) $urls->resolve(['/ccm/system/authentication/oauth2/battlenet/attempt_auth'])]);

                return 0;
            }
            if ($type->getController()->getBindingForUser($c->user)) {
                $out->writeln('<ok>' . t('Your %s account is already connected.', 'Battle.net') . '</ok>');

                return 0;
            }
            $out->writeln(t('Off to %s to connect your account.', 'Battle.net'));
            $c->addAction('redirect', ['url' => (string) $urls->resolve(['/ccm/system/authentication/oauth2/battlenet/attempt_attach'])]);

            return 0;
        }, ['category' => 'system', 'aliases' => ['bnet', 'blizzard'], 'help' => t('Not logged in: takes you to the %1$s login, you come back signed in. Logged in: connects your %1$s account to your club account.', 'Battle.net')]);
    }
}
