<?php

namespace Concrete\Package\BattlenetConnect\Block\WowCharacters;

use BattlenetConnect\BattlenetConfig;
use BattlenetConnect\Entity\WowCharacter;
use BattlenetConnect\Wow\WowRoster;
use Concrete\Core\Block\BlockController;
use Concrete\Core\Page\Page;
use Concrete\Core\User\User;
use Concrete\Core\User\UserInfoRepository;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends BlockController
{
    const MODE_ROSTER = 'roster';
    const MODE_MEMBER = 'member';

    /** The member whose public profile is shown (/members/profile/<id>), or the current user elsewhere */
    const MEMBER_PROFILE = 'profile';
    const MEMBER_CURRENT = 'current';
    const MEMBER_FIXED = 'fixed';

    protected $btTable = 'btBattlenetConnectWowCharacters';
    protected $btInterfaceWidth = 500;
    protected $btInterfaceHeight = 560;
    protected $btDefaultSet = 'social';

    // The data changes once a day (Sync Battle.net Data task). Not cached when it depends on the visitor or page.
    protected $btCacheBlockOutputLifetime = 3600;

    /** @var string|null */
    public $title;
    /** @var string roster or member */
    public $mode;
    /** @var string profile, current or fixed */
    public $memberSource;
    /** @var int user ID for the member source "fixed" */
    public $memberID;
    /** @var string|null WoW version, empty = all enabled versions */
    public $version;
    /** @var string see WowRoster::getSortNames() */
    public $sortBy;
    /** @var int period of the "progress" sort */
    public $progressDays;
    /** @var int */
    public $minLevel;
    /** @var int */
    public $maxItems;
    /** @var bool|int only the best character of every member (roster) */
    public $onePerUser;
    /** @var bool|int show the user name of the member (roster) */
    public $showOwner;

    public function getBlockTypeName()
    {
        return t('WoW Characters');
    }

    public function getBlockTypeDescription()
    {
        return t('World of Warcraft characters of a member, or a ranking of the characters of all members who linked their Battle.net account.');
    }

    public function cacheBlockOutput()
    {
        return $this->isCacheable();
    }

    public function cacheBlockOutputOnPost()
    {
        return $this->isCacheable();
    }

    public function cacheBlockOutputForRegisteredUsers()
    {
        return $this->isCacheable();
    }

    public function add()
    {
        $this->set('title', '');
        $this->set('mode', self::MODE_ROSTER);
        $this->set('memberSource', self::MEMBER_PROFILE);
        $this->set('memberID', 0);
        $this->set('version', '');
        $this->set('sortBy', WowRoster::SORT_ITEM_LEVEL);
        $this->set('progressDays', 30);
        $this->set('minLevel', 0);
        $this->set('maxItems', 10);
        $this->set('onePerUser', true);
        $this->set('showOwner', false);
        $this->setFormOptions();
    }

    public function edit()
    {
        $this->setFormOptions();
    }

    public function validate($args)
    {
        $error = $this->app->make('helper/validation/error');
        if (!in_array($args['mode'] ?? '', [self::MODE_ROSTER, self::MODE_MEMBER], true)) {
            $error->add(t('Please choose what to show.'));
        }
        if (($args['mode'] ?? '') === self::MODE_MEMBER && ($args['memberSource'] ?? '') === self::MEMBER_FIXED
            && !$this->app->make(UserInfoRepository::class)->getByID((int) ($args['memberID'] ?? 0))
        ) {
            $error->add(t('Please choose a member.'));
        }
        if (!isset(WowRoster::getSortNames()[$args['sortBy'] ?? ''])) {
            $error->add(t('Please choose the order.'));
        }

        return $error;
    }

    public function save($args)
    {
        $mode = ($args['mode'] ?? '') === self::MODE_MEMBER ? self::MODE_MEMBER : self::MODE_ROSTER;
        $memberSource = in_array($args['memberSource'] ?? '', [self::MEMBER_CURRENT, self::MEMBER_FIXED], true) ? $args['memberSource'] : self::MEMBER_PROFILE;
        $version = (string) ($args['version'] ?? '');
        parent::save([
            'title' => trim((string) ($args['title'] ?? '')),
            'mode' => $mode,
            'memberSource' => $memberSource,
            'memberID' => $mode === self::MODE_MEMBER && $memberSource === self::MEMBER_FIXED ? (int) ($args['memberID'] ?? 0) : 0,
            'version' => isset($this->app->make(BattlenetConfig::class)->getWowVersionNames()[$version]) ? $version : '',
            'sortBy' => (string) $args['sortBy'],
            'progressDays' => in_array((int) ($args['progressDays'] ?? 30), [7, 14, 30, 90, 365], true) ? (int) $args['progressDays'] : 30,
            'minLevel' => min(200, max(0, (int) ($args['minLevel'] ?? 0))),
            'maxItems' => min(200, max(1, (int) ($args['maxItems'] ?? 10))),
            'onePerUser' => !empty($args['onePerUser']) ? 1 : 0,
            'showOwner' => !empty($args['showOwner']) ? 1 : 0,
        ]);
    }

    public function view()
    {
        $config = $this->app->make(BattlenetConfig::class);
        $isMember = $this->mode === self::MODE_MEMBER;
        $memberID = $isMember ? $this->getMemberID() : null;
        $sort = isset(WowRoster::getSortNames()[$this->sortBy]) ? $this->sortBy : WowRoster::SORT_ITEM_LEVEL;

        $results = [];
        if (!$isMember || $memberID !== null) {
            $results = $this->app->make(WowRoster::class)->getCharacters([
                'uID' => $memberID,
                'version' => (string) $this->version,
                'minLevel' => (int) $this->minLevel,
                'sort' => $sort,
                'progressDays' => (int) $this->progressDays,
                'onePerUser' => !$isMember && $this->onePerUser,
                // A ranking only lists characters with a value, a member's characters are all shown
                'withValueOnly' => !$isMember,
                'limit' => (int) $this->maxItems,
            ]);
        }

        $versionNames = $config->getWowVersionNames();
        $showVersion = (string) $this->version === '' && count($config->getWowVersions()) > 1;
        $rows = [];
        $rank = 0;
        $previous = null;
        foreach ($results as $index => $result) {
            /** @var WowCharacter $character */
            $character = $result['character'];
            if ($result['value'] !== $previous) {
                $rank = $index + 1;
                $previous = $result['value'];
            }
            $rows[] = [
                'rank' => $rank,
                'character' => $character,
                'url' => $character->getProfileUrl($config->getWowLocale()),
                'description' => trim($character->getRaceName() . ' ' . ($character->getActiveSpec() ? $character->getActiveSpec() . ' ' : '') . $character->getClassName()),
                'realm' => $character->getRealmName() ?: $character->getRealmSlug(),
                'version' => $showVersion ? ($versionNames[$character->getVersion()] ?? $character->getVersion()) : null,
                'owner' => !$isMember && $this->showOwner ? $result['ownerName'] : null,
                'value' => $result['value'],
                'valueLabel' => $this->formatValue($sort, $result['value']),
                'lastPlayed' => $character->getLastPlayedAt() ? $this->formatAgo($character->getLastPlayedAt()->getTimestamp()) : null,
            ];
        }

        $heading = $this->title !== null && $this->title !== '' ? $this->title : ($isMember ? t('WoW Characters') : WowRoster::getSortNames()[$sort]);
        $subtitle = [];
        if ((string) $this->version !== '') {
            $subtitle[] = $versionNames[$this->version] ?? $this->version;
        }
        if (!$isMember && $sort === WowRoster::SORT_PROGRESS) {
            $subtitle[] = t2('Last %s day', 'Last %s days', (int) $this->progressDays);
        }

        $this->set('isMember', $isMember);
        $this->set('rows', $rows);
        $this->set('sort', $sort);
        $this->set('heading', $heading);
        $this->set('subtitle', implode(' · ', $subtitle));
    }

    protected function isCacheable(): bool
    {
        return $this->mode !== self::MODE_MEMBER || $this->memberSource === self::MEMBER_FIXED;
    }

    protected function getMemberID(): ?int
    {
        if ($this->memberSource === self::MEMBER_FIXED) {
            return (int) $this->memberID ?: null;
        }
        if ($this->memberSource === self::MEMBER_PROFILE) {
            $page = Page::getCurrentPage();
            if ($page && !$page->isError() && $page->getCollectionPath() === '/members/profile'
                && preg_match('#/members/profile/(\d+)#', $this->request->getPathInfo(), $matches)
            ) {
                return (int) $matches[1];
            }
        }
        // The member's own profile page, or the current user
        $user = $this->app->make(User::class);

        return $user->isRegistered() ? (int) $user->getUserID() : null;
    }

    /**
     * @param float|null $value
     */
    protected function formatValue(string $sort, $value): string
    {
        if ($value === null) {
            return '';
        }
        $numbers = $this->app->make('helper/number');
        switch ($sort) {
            case WowRoster::SORT_MYTHIC_RATING:
                return $numbers->format($value, 0);
            case WowRoster::SORT_ACHIEVEMENT_POINTS:
                return t('%s pts', $numbers->format($value, 0));
            case WowRoster::SORT_PROGRESS:
                return t('+%s pts', $numbers->format($value, 0));
            case WowRoster::SORT_LEVEL:
                return t('Level %s', (int) $value);
            case WowRoster::SORT_LAST_PLAYED:
                return $this->formatAgo((int) $value);
            default:
                return t('%s ilvl', (int) $value);
        }
    }

    protected function formatAgo(int $timestamp): string
    {
        $seconds = time() - $timestamp;
        if ($seconds < 3600) {
            return t('Just now');
        }

        return t('%s ago', $this->app->make('date')->describeInterval($seconds));
    }

    protected function setFormOptions(): void
    {
        $config = $this->app->make(BattlenetConfig::class);
        $versions = ['' => t('All versions')];
        foreach ($config->getWowVersionNames() as $version => $name) {
            if (in_array($version, $config->getWowVersions(), true)) {
                $versions[$version] = $name;
            }
        }
        $this->set('modeOptions', [
            self::MODE_ROSTER => t('Ranking of the characters of all members'),
            self::MODE_MEMBER => t('Characters of one member'),
        ]);
        $this->set('memberSourceOptions', [
            self::MEMBER_PROFILE => t('The member whose profile is shown (or the current user)'),
            self::MEMBER_CURRENT => t('The current user'),
            self::MEMBER_FIXED => t('A specific member'),
        ]);
        $this->set('versionOptions', $versions);
        $this->set('sortOptions', WowRoster::getSortNames());
        $this->set('progressDaysOptions', [7 => t('Last 7 days'), 14 => t('Last 14 days'), 30 => t('Last 30 days'), 90 => t('Last 90 days'), 365 => t('Last 12 months')]);
        $this->set('userSelector', $this->app->make('helper/form/user_selector'));
        $this->set('wowEnabled', $config->isWowEnabled());
    }
}
