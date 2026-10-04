<?php

namespace BattlenetConnect\Wow;

use BattlenetConnect\BattlenetAccounts;
use BattlenetConnect\BattlenetConfig;
use BattlenetConnect\Entity\WowCharacter;
use Concrete\Core\Database\Connection\Connection;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lists and rankings of the WoW characters of the users who linked their Battle.net account.
 */
class WowRoster
{
    const SORT_ITEM_LEVEL = 'item_level';
    const SORT_MYTHIC_RATING = 'mythic_rating';
    const SORT_ACHIEVEMENT_POINTS = 'achievement_points';
    const SORT_LEVEL = 'level';
    const SORT_LAST_PLAYED = 'last_played';
    /** Achievement points gained in a period, from the snapshots */
    const SORT_PROGRESS = 'progress';

    /** @var Connection */
    protected $db;

    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var BattlenetConfig */
    protected $config;

    public function __construct(Connection $db, EntityManagerInterface $entityManager, BattlenetConfig $config)
    {
        $this->db = $db;
        $this->entityManager = $entityManager;
        $this->config = $config;
    }

    /**
     * @return array<string, string>
     */
    public static function getSortNames(): array
    {
        return [
            self::SORT_ITEM_LEVEL => t('Item level'),
            self::SORT_MYTHIC_RATING => t('Mythic+ rating (retail)'),
            self::SORT_ACHIEVEMENT_POINTS => t('Achievement points'),
            self::SORT_LEVEL => t('Level'),
            self::SORT_LAST_PLAYED => t('Last played'),
            self::SORT_PROGRESS => t('Most active (achievement points gained)'),
        ];
    }

    /**
     * @param array $options
     *                       - uID: int|null, characters of this user only
     *                       - version: string|null, of this WoW version only
     *                       - minLevel: int
     *                       - sort: one of the SORT_ constants
     *                       - progressDays: int, period of SORT_PROGRESS
     *                       - onePerUser: bool, only the best character of every user
     *                       - withValueOnly: bool, skip characters without a value for the sort (e.g. no Mythic+ rating)
     *                       - limit: int|null
     *
     * @return array<array{character: WowCharacter, value: float|null, ownerID: int, ownerName: string}> ordered,
     *                                                                                                   characters without a value for the sort come last
     */
    public function getCharacters(array $options): array
    {
        $sort = isset(self::getSortNames()[$options['sort'] ?? '']) ? $options['sort'] : self::SORT_ITEM_LEVEL;
        $where = ['c.region = ?'];
        $params = [$this->config->getWowApiRegion()];
        $versions = $this->config->getWowVersions();
        if (!empty($options['version'])) {
            $versions = array_intersect($versions, [(string) $options['version']]);
        }
        if ($versions === []) {
            return [];
        }
        $where[] = 'c.version IN (' . implode(',', array_fill(0, count($versions), '?')) . ')';
        array_push($params, ...array_values($versions));
        if (!empty($options['uID'])) {
            $where[] = 'c.uID = ?';
            $params[] = (int) $options['uID'];
        }
        if (!empty($options['minLevel'])) {
            $where[] = 'c.level >= ?';
            $params[] = (int) $options['minLevel'];
        }

        // Only linked accounts, the binding is the source of truth for the owner
        $rows = $this->db->fetchAllAssociative(
            'SELECT c.id, m.user_id, u.uName FROM BattlenetConnectWowCharacters c'
            . ' INNER JOIN OauthUserMap m ON m.namespace = ? AND m.binding = c.battlenetId'
            . ' INNER JOIN Users u ON u.uID = m.user_id'
            . ' WHERE ' . implode(' AND ', $where),
            array_merge([BattlenetAccounts::BINDING_NAMESPACE], $params)
        );
        if ($rows === []) {
            return [];
        }

        $characters = [];
        foreach ($this->entityManager->getRepository(WowCharacter::class)->findBy(['id' => array_column($rows, 'id')]) as $character) {
            $characters[$character->getID()] = $character;
        }
        $progress = $sort === self::SORT_PROGRESS ? $this->getProgress(array_keys($characters), max(1, (int) ($options['progressDays'] ?? 30))) : [];

        $result = [];
        foreach ($rows as $row) {
            $character = $characters[(int) $row['id']] ?? null;
            if ($character === null) {
                continue;
            }
            $result[] = [
                'character' => $character,
                'value' => $sort === self::SORT_PROGRESS ? ($progress[$character->getID()] ?? null) : $this->getValue($character, $sort),
                'ownerID' => (int) $row['user_id'],
                'ownerName' => (string) $row['uName'],
            ];
        }

        usort($result, static function (array $a, array $b) {
            if ($a['value'] === null || $b['value'] === null) {
                return ($a['value'] === null) <=> ($b['value'] === null);
            }

            return [$b['value'], $b['character']->getLevel(), $a['character']->getName()] <=> [$a['value'], $a['character']->getLevel(), $b['character']->getName()];
        });
        if ($sort === self::SORT_PROGRESS || !empty($options['withValueOnly'])) {
            $result = array_values(array_filter($result, static function (array $row) use ($sort) {
                return $sort === self::SORT_PROGRESS ? $row['value'] > 0 : $row['value'] !== null;
            }));
        }
        if (!empty($options['onePerUser'])) {
            $seen = [];
            $result = array_values(array_filter($result, static function (array $row) use (&$seen) {
                if (isset($seen[$row['ownerID']])) {
                    return false;
                }

                return $seen[$row['ownerID']] = true;
            }));
        }

        return empty($options['limit']) ? $result : array_slice($result, 0, (int) $options['limit']);
    }

    /**
     * Achievement points gained in the last days: current value minus the last snapshot before the period (or the first
     * snapshot, for characters that were imported during the period).
     *
     * @param int[] $characterIDs
     *
     * @return array<int, float> by character ID
     */
    public function getProgress(array $characterIDs, int $days): array
    {
        if ($characterIDs === []) {
            return [];
        }
        $ids = implode(',', array_map('intval', $characterIDs));
        $start = (new DateTime('-' . $days . ' days'))->format('Y-m-d H:i:s');
        $baseline = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT s.characterRef, s.achievementPoints FROM BattlenetConnectWowSnapshots s INNER JOIN ('
            . ' SELECT characterRef, COALESCE(MAX(CASE WHEN recordedAt < ? THEN recordedAt END), MIN(recordedAt)) AS recordedAt'
            . ' FROM BattlenetConnectWowSnapshots WHERE characterRef IN (' . $ids . ') AND achievementPoints IS NOT NULL GROUP BY characterRef'
            . ') b ON b.characterRef = s.characterRef AND b.recordedAt = s.recordedAt',
            [$start]
        ) as $row) {
            $baseline[(int) $row['characterRef']] = (int) $row['achievementPoints'];
        }
        $progress = [];
        foreach ($this->db->fetchAllKeyValue('SELECT id, achievementPoints FROM BattlenetConnectWowCharacters WHERE id IN (' . $ids . ') AND achievementPoints IS NOT NULL') as $id => $points) {
            if (isset($baseline[(int) $id])) {
                $progress[(int) $id] = (float) max(0, (int) $points - $baseline[(int) $id]);
            }
        }

        return $progress;
    }

    /**
     * @return float|null
     */
    protected function getValue(WowCharacter $character, string $sort)
    {
        switch ($sort) {
            case self::SORT_MYTHIC_RATING:
                $value = $character->getMythicRating();

                return $value ? $value : null;
            case self::SORT_ACHIEVEMENT_POINTS:
                $value = $character->getAchievementPoints();
                break;
            case self::SORT_LEVEL:
                $value = $character->getLevel();
                break;
            case self::SORT_LAST_PLAYED:
                $value = $character->getLastPlayedAt() ? $character->getLastPlayedAt()->getTimestamp() : null;
                break;
            default:
                $value = $character->getItemLevel();
                break;
        }

        return $value === null ? null : (float) $value;
    }
}
