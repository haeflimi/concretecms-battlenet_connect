<?php

namespace BattlenetConnect\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Battle.net account of a user who linked it. The binding itself (user ID <-> Battle.net account ID) is the core
 * OauthUserMap (namespace "battlenet"), this is the data we know about it. Written on login/attach
 * (BattlenetConnect\BattlenetAccounts), read-only everywhere else.
 *
 * @ORM\Entity()
 * @ORM\Table(name="BattlenetConnectProfiles", indexes={@ORM\Index(name="uID", columns={"uID"})})
 */
class BattlenetProfile
{
    /**
     * Battle.net account ID.
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=20)
     */
    protected $battlenetId;

    /**
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected $uID;

    /**
     * Name#1234, null if the account has none yet.
     *
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $battleTag;

    /**
     * Last time the user logged in with or attached their Battle.net account.
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $connectedAt;

    public function getBattlenetId(): string
    {
        return $this->battlenetId;
    }

    public function getUserID(): int
    {
        return (int) $this->uID;
    }

    public function getBattleTag(): ?string
    {
        return $this->battleTag;
    }

    /**
     * The name part of the BattleTag (without the #1234).
     */
    public function getName(): ?string
    {
        return $this->battleTag === null ? null : explode('#', $this->battleTag, 2)[0];
    }

    public function getDisplayName(): string
    {
        return $this->battleTag ?? $this->battlenetId;
    }

    public function getConnectedAt(): ?DateTime
    {
        return $this->connectedAt;
    }
}
