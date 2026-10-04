<?php

namespace BattlenetConnect\Wow;

use BattlenetConnect\Api\BattlenetApi;
use BattlenetConnect\Api\BattlenetApiException;
use BattlenetConnect\BattlenetAccounts;
use BattlenetConnect\BattlenetConfig;
use Concrete\Core\Database\Connection\Connection;
use DateTime;
use DateTimeZone;
use Throwable;

/**
 * Imports the WoW characters of the linked accounts and keeps their data up to date.
 */
class WowCharacterSync
{
    protected const DATE_FORMAT = 'Y-m-d H:i:s';

    /** Values tracked in the snapshots */
    protected const SNAPSHOT_FIELDS = ['level', 'itemLevel', 'achievementPoints', 'mythicRating'];

    /** @var Connection */
    protected $db;

    /** @var BattlenetApi */
    protected $api;

    /** @var BattlenetConfig */
    protected $config;

    public function __construct(Connection $db, BattlenetApi $api, BattlenetConfig $config)
    {
        $this->db = $db;
        $this->api = $api;
        $this->config = $config;
    }

    /**
     * Import the characters of an account, called when the user logs in (that's the only time we have their token).
     * Characters that are not on the account anymore are removed. Versions whose list can't be read are left alone.
     *
     * @param string $userToken OAuth2 access token of the user with the wow.profile scope
     *
     * @return array<string, int|string> number of characters by version, or the error message if it couldn't be read
     */
    public function importAccountCharacters(string $battlenetId, int $uID, string $userToken): array
    {
        $result = [];
        $region = $this->config->getWowApiRegion();
        $now = (new DateTime())->format(self::DATE_FORMAT);
        foreach ($this->config->getWowVersions() as $version) {
            try {
                $characters = $this->api->getAccountCharacters($version, $userToken);
            } catch (BattlenetApiException $e) {
                $result[$version] = $e->getMessage();
                continue;
            }
            $characterIds = [];
            foreach ($characters as $character) {
                if (empty($character['id']) || empty($character['name']) || empty($character['realm']['slug'])) {
                    continue;
                }
                $characterIds[] = (int) $character['id'];
                $key = ['region' => $region, 'version' => $version, 'characterId' => (int) $character['id']];
                $values = [
                    'battlenetId' => $battlenetId,
                    'uID' => $uID,
                    'name' => mb_substr((string) $character['name'], 0, 64),
                    'realmSlug' => mb_substr((string) $character['realm']['slug'], 0, 64),
                    'realmName' => $this->name($character['realm']['name'] ?? null),
                    'level' => isset($character['level']) ? (int) $character['level'] : null,
                    'classId' => isset($character['playable_class']['id']) ? (int) $character['playable_class']['id'] : null,
                    'className' => $this->name($character['playable_class']['name'] ?? null),
                    'raceId' => isset($character['playable_race']['id']) ? (int) $character['playable_race']['id'] : null,
                    'raceName' => $this->name($character['playable_race']['name'] ?? null),
                    'faction' => $character['faction']['type'] ?? null,
                ];
                $id = $this->db->fetchOne('SELECT id FROM BattlenetConnectWowCharacters WHERE region = ? AND version = ? AND characterId = ?', array_values($key));
                if ($id) {
                    $this->db->update('BattlenetConnectWowCharacters', $values, ['id' => $id]);
                } else {
                    $this->db->insert('BattlenetConnectWowCharacters', $key + $values + ['importedAt' => $now]);
                }
            }
            $this->deleteCharacters(
                'battlenetId = ? AND region = ? AND version = ?' . ($characterIds ? ' AND characterId NOT IN (' . implode(',', $characterIds) . ')' : ''),
                [$battlenetId, $region, $version]
            );
            $result[$version] = count($characterIds);
        }

        return $result;
    }

    /**
     * @return int[] IDs of all characters of linked accounts in the enabled versions and the configured region
     */
    public function getCharacterIDs(): array
    {
        $versions = $this->config->getWowVersions();
        if ($versions === []) {
            return [];
        }

        return array_map('intval', $this->db->fetchFirstColumn(
            'SELECT c.id FROM BattlenetConnectWowCharacters c INNER JOIN OauthUserMap m ON m.namespace = ? AND m.binding = c.battlenetId'
            . ' WHERE c.region = ? AND c.version IN (?) ORDER BY c.syncedAt IS NOT NULL, c.syncedAt, c.id',
            [BattlenetAccounts::BINDING_NAMESPACE, $this->config->getWowApiRegion(), $versions],
            [\PDO::PARAM_STR, \PDO::PARAM_STR, Connection::PARAM_STR_ARRAY]
        ));
    }

    /**
     * Update the data of a character: summary, images and (retail) Mythic+ rating.
     *
     * @return bool false if the character was not found
     *
     * @throws BattlenetApiException if the API can't be used (credentials, rate limit), see BattlenetApiException::isFatal()
     */
    public function syncCharacter(int $id): bool
    {
        $character = $this->db->fetchAssociative('SELECT * FROM BattlenetConnectWowCharacters WHERE id = ?', [$id]);
        if (!$character) {
            return false;
        }
        $version = (string) $character['version'];
        $now = (new DateTime())->format(self::DATE_FORMAT);

        try {
            $summary = $this->api->getCharacter($version, $character['realmSlug'], $character['name']);
            // The name may belong to another character after a rename
            if ($summary === null || (int) ($summary['id'] ?? 0) !== (int) $character['characterId']) {
                $this->db->update('BattlenetConnectWowCharacters', [
                    'syncedAt' => $now,
                    'syncError' => t('Not found: not played for a long time, renamed or transferred. The owner has to log in with Battle.net again.'),
                ], ['id' => $id]);

                return false;
            }
            $values = [
                'level' => isset($summary['level']) ? (int) $summary['level'] : $character['level'],
                'classId' => $summary['character_class']['id'] ?? $character['classId'],
                'className' => $this->name($summary['character_class']['name'] ?? null) ?? $character['className'],
                'raceId' => $summary['race']['id'] ?? $character['raceId'],
                'raceName' => $this->name($summary['race']['name'] ?? null) ?? $character['raceName'],
                'faction' => $summary['faction']['type'] ?? $character['faction'],
                'realmName' => $this->name($summary['realm']['name'] ?? null) ?? $character['realmName'],
                'activeSpec' => $this->name($summary['active_spec']['name'] ?? null),
                'guildName' => isset($summary['guild']['name']) ? mb_substr((string) $summary['guild']['name'], 0, 255) : null,
                'itemLevel' => isset($summary['equipped_item_level']) ? (int) $summary['equipped_item_level'] : null,
                'achievementPoints' => isset($summary['achievement_points']) ? (int) $summary['achievement_points'] : null,
                'lastPlayedAt' => $this->formatTimestamp($summary['last_login_timestamp'] ?? null),
            ];

            $media = $this->api->getCharacterMedia($version, $character['realmSlug'], $character['name']);
            $assets = [];
            foreach ($media['assets'] ?? [] as $asset) {
                if (isset($asset['key'], $asset['value'])) {
                    $assets[$asset['key']] = mb_substr((string) $asset['value'], 0, 255);
                }
            }
            $values['avatarUrl'] = $assets['avatar'] ?? null;
            $values['insetUrl'] = $assets['inset'] ?? null;
            $values['renderUrl'] = $assets['main-raw'] ?? $assets['main'] ?? null;

            if ($version === BattlenetConfig::WOW_RETAIL) {
                $mythic = $this->api->getMythicKeystoneProfile($character['realmSlug'], $character['name']);
                $rating = $mythic['current_mythic_rating'] ?? null;
                $values['mythicRating'] = isset($rating['rating']) ? round((float) $rating['rating'], 1) : null;
                $values['mythicRatingColor'] = isset($rating['color']['r'], $rating['color']['g'], $rating['color']['b'])
                    ? sprintf('#%02x%02x%02x', $rating['color']['r'], $rating['color']['g'], $rating['color']['b'])
                    : null;
            }
        } catch (BattlenetApiException $e) {
            $this->db->update('BattlenetConnectWowCharacters', ['syncedAt' => $now, 'syncError' => mb_substr($e->getMessage(), 0, 255)], ['id' => $id]);
            throw $e;
        }

        $this->db->update('BattlenetConnectWowCharacters', $values + ['syncedAt' => $now, 'syncError' => null], ['id' => $id]);
        $this->saveSnapshot($id, $values, $now);

        return true;
    }

    /**
     * Remove the characters (and their snapshots) of a Battle.net account.
     */
    public function deleteAccountCharacters(string $battlenetId): int
    {
        return $this->deleteCharacters('battlenetId = ?', [$battlenetId]);
    }

    /**
     * Remove the characters of Battle.net accounts that are not linked anymore.
     */
    public function pruneUnlinkedCharacters(): int
    {
        return $this->deleteCharacters('battlenetId NOT IN (SELECT binding FROM OauthUserMap WHERE namespace = ?)', [BattlenetAccounts::BINDING_NAMESPACE]);
    }

    protected function deleteCharacters(string $where, array $params): int
    {
        $ids = $this->db->fetchFirstColumn('SELECT id FROM BattlenetConnectWowCharacters WHERE ' . $where, $params);
        if ($ids === []) {
            return 0;
        }
        $ids = implode(',', array_map('intval', $ids));
        $this->db->transactional(function () use ($ids) {
            $this->db->executeStatement('DELETE FROM BattlenetConnectWowSnapshots WHERE characterRef IN (' . $ids . ')');
            $this->db->executeStatement('DELETE FROM BattlenetConnectWowCharacters WHERE id IN (' . $ids . ')');
        });

        return substr_count($ids, ',') + 1;
    }

    protected function saveSnapshot(int $id, array $values, string $now): void
    {
        $snapshot = array_intersect_key($values, array_flip(self::SNAPSHOT_FIELDS));
        if (count(array_filter($snapshot, static function ($value) { return $value !== null; })) === 0) {
            return;
        }
        $last = $this->db->fetchAssociative(
            'SELECT ' . implode(', ', self::SNAPSHOT_FIELDS) . ' FROM BattlenetConnectWowSnapshots WHERE characterRef = ? ORDER BY recordedAt DESC, id DESC LIMIT 1',
            [$id]
        );
        if ($last) {
            $changed = false;
            foreach (self::SNAPSHOT_FIELDS as $field) {
                if ((string) $last[$field] !== (string) $snapshot[$field] && !($last[$field] !== null && $snapshot[$field] !== null && (float) $last[$field] === (float) $snapshot[$field])) {
                    $changed = true;
                }
            }
            if (!$changed) {
                return;
            }
        }
        $this->db->insert('BattlenetConnectWowSnapshots', ['characterRef' => $id, 'recordedAt' => $now] + $snapshot);
    }

    /**
     * Names are strings when a locale is requested, objects with all translations otherwise.
     *
     * @param string|array|null $name
     */
    protected function name($name): ?string
    {
        if (is_array($name)) {
            $name = $name[$this->config->getWowLocale()] ?? reset($name);
        }

        return is_string($name) && $name !== '' ? mb_substr($name, 0, 64) : null;
    }

    /**
     * @param int|null $timestamp in milliseconds
     */
    protected function formatTimestamp($timestamp): ?string
    {
        if (empty($timestamp)) {
            return null;
        }
        try {
            return (new DateTime('@' . intdiv((int) $timestamp, 1000)))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format(self::DATE_FORMAT);
        } catch (Throwable $e) {
            return null;
        }
    }
}
