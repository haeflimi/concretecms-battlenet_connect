<?php

namespace BattlenetConnect;

use BattlenetConnect\Entity\BattlenetProfile;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\User\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The linked Battle.net accounts: the Battle.net account IDs of our users and what we know about them.
 *
 * Example: $battleTag = app(BattlenetAccounts::class)->getProfile($user)->getBattleTag();
 */
class BattlenetAccounts
{
    const BINDING_NAMESPACE = 'battlenet';

    protected const DATE_FORMAT = 'Y-m-d H:i:s';

    /** @var Connection */
    protected $db;

    /** @var EntityManagerInterface */
    protected $entityManager;

    public function __construct(Connection $db, EntityManagerInterface $entityManager)
    {
        $this->db = $db;
        $this->entityManager = $entityManager;
    }

    /**
     * Battle.net account IDs are positive integers.
     */
    public static function isValidId(string $value): bool
    {
        return (bool) preg_match('/^[1-9]\d{0,19}$/', $value);
    }

    /**
     * @return array<string, int> user IDs by Battle.net account ID of all users who linked their Battle.net account
     */
    public function getLinkedAccounts(): array
    {
        $accounts = [];
        foreach ($this->db->fetchAllAssociative('SELECT binding, user_id FROM OauthUserMap WHERE namespace = ? ORDER BY user_id', [self::BINDING_NAMESPACE]) as $row) {
            $accounts[(string) $row['binding']] = (int) $row['user_id'];
        }

        return $accounts;
    }

    /**
     * @param User|\Concrete\Core\User\UserInfo|int $user
     */
    public function getBattlenetId($user): ?string
    {
        $binding = $this->db->fetchOne(
            'SELECT binding FROM OauthUserMap WHERE namespace = ? AND user_id = ?',
            [self::BINDING_NAMESPACE, is_object($user) ? (int) $user->getUserID() : (int) $user]
        );

        return $binding === false ? null : (string) $binding;
    }

    public function getUserID(string $battlenetId): ?int
    {
        $userID = $this->db->fetchOne('SELECT user_id FROM OauthUserMap WHERE namespace = ? AND binding = ?', [self::BINDING_NAMESPACE, $battlenetId]);

        return $userID === false ? null : (int) $userID;
    }

    /**
     * @param User|\Concrete\Core\User\UserInfo|int $user
     */
    public function getProfile($user): ?BattlenetProfile
    {
        $battlenetId = $this->getBattlenetId($user);

        return $battlenetId === null ? null : $this->entityManager->find(BattlenetProfile::class, $battlenetId);
    }

    /**
     * @return BattlenetProfile|null the profile of the user with that BattleTag (Name#1234, case-insensitive)
     */
    public function findByBattleTag(string $battleTag): ?BattlenetProfile
    {
        $battlenetId = $this->db->fetchOne('SELECT battlenetId FROM BattlenetConnectProfiles WHERE battleTag = ?', [$battleTag]);

        return $battlenetId === false ? null : $this->entityManager->find(BattlenetProfile::class, (string) $battlenetId);
    }

    /**
     * Store the Battle.net user info (id, battletag), called on every login/attach.
     */
    public function saveUser(array $battlenetUser, int $uID): void
    {
        $battlenetId = (string) $battlenetUser['id'];
        $values = [
            'uID' => $uID,
            'battleTag' => isset($battlenetUser['battletag']) ? mb_substr((string) $battlenetUser['battletag'], 0, 64) : null,
            'connectedAt' => (new DateTime())->format(self::DATE_FORMAT),
        ];
        if ($this->db->fetchOne('SELECT 1 FROM BattlenetConnectProfiles WHERE battlenetId = ?', [$battlenetId])) {
            $this->db->update('BattlenetConnectProfiles', $values, ['battlenetId' => $battlenetId]);
        } else {
            $this->db->insert('BattlenetConnectProfiles', ['battlenetId' => $battlenetId] + $values);
        }
    }

    public function deleteProfile(string $battlenetId): void
    {
        $this->db->delete('BattlenetConnectProfiles', ['battlenetId' => $battlenetId]);
    }

    /**
     * Remove the data of Battle.net accounts that are not linked anymore (e.g. deleted users).
     *
     * @return int number of removed profiles
     */
    public function pruneUnlinkedAccounts(): int
    {
        return (int) $this->db->executeStatement(
            'DELETE p FROM BattlenetConnectProfiles p LEFT JOIN OauthUserMap m ON m.namespace = ? AND m.binding = p.battlenetId WHERE m.user_id IS NULL',
            [self::BINDING_NAMESPACE]
        );
    }

    /**
     * Remove bindings that are not Battle.net account IDs (versions before 1.0 stored the access token).
     *
     * @return int number of removed bindings
     */
    public function removeInvalidBindings(): int
    {
        $removed = 0;
        foreach ($this->db->fetchAllAssociative('SELECT user_id, binding FROM OauthUserMap WHERE namespace = ?', [self::BINDING_NAMESPACE]) as $row) {
            if (!self::isValidId((string) $row['binding'])) {
                $removed += $this->db->delete('OauthUserMap', ['namespace' => self::BINDING_NAMESPACE, 'user_id' => $row['user_id'], 'binding' => $row['binding']]);
            }
        }

        return $removed;
    }
}
