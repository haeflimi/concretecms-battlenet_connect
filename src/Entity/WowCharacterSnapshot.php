<?php

namespace BattlenetConnect\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Progress of a character at a point in time, for the activity statistics. A row is only written when a value changed
 * since the last sync, so the progress within any period is the difference between the last values before its start
 * and the current values.
 *
 * @ORM\Entity()
 * @ORM\Table(name="BattlenetConnectWowSnapshots", indexes={@ORM\Index(name="character_time", columns={"characterRef", "recordedAt"})})
 */
class WowCharacterSnapshot
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue
     */
    protected $id;

    /**
     * ID of the WowCharacter (not the Blizzard character ID).
     *
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected $characterRef;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $recordedAt;

    /**
     * @ORM\Column(type="smallint", options={"unsigned": true}, nullable=true)
     */
    protected $level;

    /**
     * @ORM\Column(type="smallint", options={"unsigned": true}, nullable=true)
     */
    protected $itemLevel;

    /**
     * @ORM\Column(type="integer", options={"unsigned": true}, nullable=true)
     */
    protected $achievementPoints;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $mythicRating;

    public function getID(): int
    {
        return (int) $this->id;
    }

    public function getCharacterRef(): int
    {
        return (int) $this->characterRef;
    }

    public function getRecordedAt(): DateTime
    {
        return $this->recordedAt;
    }

    public function getLevel(): ?int
    {
        return $this->level === null ? null : (int) $this->level;
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
}
