<?php

namespace BattlenetConnect\Command;

use Concrete\Core\Foundation\Command\Command;

/**
 * Update the data of a chunk of WoW characters, see BattlenetConnect\Wow\WowCharacterSync::syncCharacter().
 */
class SyncWowCharactersCommand extends Command
{
    /** @var int[] */
    protected $characterIDs;

    /**
     * @param int[] $characterIDs IDs of WowCharacter entities
     */
    public function __construct(array $characterIDs)
    {
        $this->characterIDs = $characterIDs;
    }

    /**
     * @return int[]
     */
    public function getCharacterIDs(): array
    {
        return $this->characterIDs;
    }
}
