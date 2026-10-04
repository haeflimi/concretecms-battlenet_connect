<?php

namespace BattlenetConnect\Command;

use BattlenetConnect\Api\BattlenetApiException;
use BattlenetConnect\Wow\WowCharacterSync;
use Concrete\Core\Command\Task\Output\OutputAwareInterface;
use Concrete\Core\Command\Task\Output\OutputAwareTrait;

class SyncWowCharactersCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;

    /** @var WowCharacterSync */
    protected $sync;

    public function __construct(WowCharacterSync $sync)
    {
        $this->sync = $sync;
    }

    public function __invoke(SyncWowCharactersCommand $command)
    {
        $synced = 0;
        $notFound = 0;
        foreach ($command->getCharacterIDs() as $id) {
            try {
                if ($this->sync->syncCharacter($id)) {
                    ++$synced;
                } else {
                    ++$notFound;
                }
            } catch (BattlenetApiException $e) {
                if ($e->isFatal()) {
                    // Wrong credentials or rate limit: the other characters would fail too
                    throw $e;
                }
                $this->output->write(t('Character #%s: %s', $id, $e->getMessage()));
            }
        }
        $this->output->write(t('Updated %s WoW characters, %s not found.', $synced, $notFound));
    }
}
