<?php

namespace BattlenetConnect\Figure;

use CommunityEventManager\Figure\CallbackFigure;
use CommunityEventManager\Figure\FigureContext;
use CommunityEventManager\Figure\FigureOptions as O;
use CommunityEventManager\Figure\FigureRegistry;
use CommunityEventManager\Figure\FigureValue;

/**
 * Figures for the block "Event Figure" (community_event_manager), registered on on_event_figures_register.
 */
class BattlenetFigures
{
    public static function register(FigureRegistry $registry): void
    {
        $group = t('Battle.net');
        $registry->register(CallbackFigure::make('battlenet.accounts', t('Connected Battle.net accounts'), $group, 'fas fa-link', [], static function (FigureContext $c, array $o) {
            return FigureValue::number($c->db->fetchOne('SELECT COUNT(*) FROM BattlenetConnectProfiles WHERE uID IS NOT NULL'));
        }));

        $registry->register(CallbackFigure::make('wow.characters', t('WoW characters'), $group, 'fas fa-dragon', [], static function (FigureContext $c, array $o) {
            return FigureValue::number($c->db->fetchOne('SELECT COUNT(*) FROM BattlenetConnectWowCharacters'));
        }));

        $registry->register(CallbackFigure::make('wow.max_level', t('WoW characters at max level'), $group, 'fas fa-crown', [], static function (FigureContext $c, array $o) {
            return FigureValue::number($c->db->fetchOne('SELECT COUNT(*) FROM BattlenetConnectWowCharacters WHERE level = (SELECT MAX(level) FROM BattlenetConnectWowCharacters)'));
        }));
    }
}
