<?php

namespace BattlenetConnect\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * World of Warcraft character of a user who linked their Battle.net account.
 * Imported from the account when the user logs in, updated by the "Sync Battle.net Data" task
 * (BattlenetConnect\Wow\WowCharacterSync), read-only everywhere else.
 *
 * @ORM\Entity()
 * @ORM\Table(name="BattlenetConnectWowCharacters",
 *     uniqueConstraints={@ORM\UniqueConstraint(name="character_key", columns={"region", "version", "characterId"})},
 *     indexes={@ORM\Index(name="battlenetId", columns={"battlenetId"}), @ORM\Index(name="uID", columns={"uID"})}
 * )
 */
class WowCharacter
{
    /** Class colors, by class ID */
    const CLASS_COLORS = [
        1 => '#c69b6d', // Warrior
        2 => '#f48cba', // Paladin
        3 => '#aad372', // Hunter
        4 => '#fff468', // Rogue
        5 => '#ffffff', // Priest
        6 => '#c41e3a', // Death Knight
        7 => '#0070dd', // Shaman
        8 => '#3fc7eb', // Mage
        9 => '#8788ee', // Warlock
        10 => '#00ff98', // Monk
        11 => '#ff7c0a', // Druid
        12 => '#a330c9', // Demon Hunter
        13 => '#33937f', // Evoker
    ];

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue
     */
    protected $id;

    /**
     * Battle.net account the character belongs to.
     *
     * @ORM\Column(type="string", length=20)
     */
    protected $battlenetId;

    /**
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected $uID;

    /**
     * API region: eu, us, kr or tw.
     *
     * @ORM\Column(type="string", length=2)
     */
    protected $region;

    /**
     * WoW version: retail, classic, classic_era, anniversary, ... (see the wow_namespaces setting).
     *
     * @ORM\Column(type="string", length=32)
     */
    protected $version;

    /**
     * ID of the character in its version and region.
     *
     * @ORM\Column(type="bigint", options={"unsigned": true})
     */
    protected $characterId;

    /**
     * @ORM\Column(type="string", length=64)
     */
    protected $name;

    /**
     * @ORM\Column(type="string", length=64)
     */
    protected $realmSlug;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $realmName;

    /**
     * @ORM\Column(type="smallint", options={"unsigned": true}, nullable=true)
     */
    protected $level;

    /**
     * @ORM\Column(type="smallint", options={"unsigned": true}, nullable=true)
     */
    protected $classId;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $className;

    /**
     * @ORM\Column(type="smallint", options={"unsigned": true}, nullable=true)
     */
    protected $raceId;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $raceName;

    /**
     * ALLIANCE, HORDE or NEUTRAL.
     *
     * @ORM\Column(type="string", length=16, nullable=true)
     */
    protected $faction;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $activeSpec;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $guildName;

    /**
     * Item level of the equipped items.
     *
     * @ORM\Column(type="smallint", options={"unsigned": true}, nullable=true)
     */
    protected $itemLevel;

    /**
     * @ORM\Column(type="integer", options={"unsigned": true}, nullable=true)
     */
    protected $achievementPoints;

    /**
     * Mythic+ rating of the current season (retail only).
     *
     * @ORM\Column(type="float", nullable=true)
     */
    protected $mythicRating;

    /**
     * Color of the Mythic+ rating given by Blizzard, #rrggbb.
     *
     * @ORM\Column(type="string", length=7, nullable=true)
     */
    protected $mythicRatingColor;

    /**
     * When the character was last logged out (that's what the API calls last login).
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $lastPlayedAt;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $avatarUrl;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $insetUrl;

    /**
     * Full body render.
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $renderUrl;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $importedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $syncedAt;

    /**
     * Error of the last sync, null if it succeeded.
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $syncError;

    public function getID(): int
    {
        return (int) $this->id;
    }

    public function getBattlenetId(): string
    {
        return $this->battlenetId;
    }

    public function getUserID(): int
    {
        return (int) $this->uID;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getCharacterId(): int
    {
        return (int) $this->characterId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRealmSlug(): string
    {
        return $this->realmSlug;
    }

    public function getRealmName(): ?string
    {
        return $this->realmName;
    }

    public function getLevel(): ?int
    {
        return $this->level === null ? null : (int) $this->level;
    }

    public function getClassId(): ?int
    {
        return $this->classId === null ? null : (int) $this->classId;
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function getClassColor(): ?string
    {
        return self::CLASS_COLORS[(int) $this->classId] ?? null;
    }

    public function getRaceId(): ?int
    {
        return $this->raceId === null ? null : (int) $this->raceId;
    }

    public function getRaceName(): ?string
    {
        return $this->raceName;
    }

    public function getFaction(): ?string
    {
        return $this->faction;
    }

    public function getActiveSpec(): ?string
    {
        return $this->activeSpec;
    }

    public function getGuildName(): ?string
    {
        return $this->guildName;
    }

    public function getItemLevel(): ?int
    {
        return $this->itemLevel === null ? null : (int) $this->itemLevel;
    }

    public function getAchievementPoints(): ?int
    {
        return $this->achievementPoints === null ? null : (int) $this->achievementPoints;
    }

    public function getMythicRating(): ?float
    {
        return $this->mythicRating === null ? null : (float) $this->mythicRating;
    }

    public function getMythicRatingColor(): ?string
    {
        return $this->mythicRatingColor;
    }

    public function getLastPlayedAt(): ?DateTime
    {
        return $this->lastPlayedAt;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function getInsetUrl(): ?string
    {
        return $this->insetUrl;
    }

    public function getRenderUrl(): ?string
    {
        return $this->renderUrl;
    }

    public function getImportedAt(): DateTime
    {
        return $this->importedAt;
    }

    public function getSyncedAt(): ?DateTime
    {
        return $this->syncedAt;
    }

    public function getSyncError(): ?string
    {
        return $this->syncError;
    }

    /**
     * The character page on the official website (retail only, there is none for Classic characters).
     *
     * @param string $locale e.g. en_GB
     */
    public function getProfileUrl(string $locale = 'en_US'): ?string
    {
        if ($this->version !== 'retail') {
            return null;
        }

        return 'https://worldofwarcraft.blizzard.com/' . strtolower(str_replace('_', '-', $locale)) . '/character/'
            . $this->region . '/' . rawurlencode($this->realmSlug) . '/' . rawurlencode(mb_strtolower($this->name));
    }
}
