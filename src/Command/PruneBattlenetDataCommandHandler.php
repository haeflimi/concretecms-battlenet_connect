<?php

namespace BattlenetConnect\Command;

use BattlenetConnect\BattlenetAccounts;
use BattlenetConnect\Wow\WowCharacterSync;
use Concrete\Core\Command\Task\Output\OutputAwareInterface;
use Concrete\Core\Command\Task\Output\OutputAwareTrait;

class PruneBattlenetDataCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;

    /** @var BattlenetAccounts */
    protected $accounts;

    /** @var WowCharacterSync */
    protected $wowSync;

    public function __construct(BattlenetAccounts $accounts, WowCharacterSync $wowSync)
    {
        $this->accounts = $accounts;
        $this->wowSync = $wowSync;
    }

    public function __invoke(PruneBattlenetDataCommand $command)
    {
        $this->output->write(t(
            'Removed the data of %s unlinked Battle.net accounts and %s of their WoW characters.',
            $this->accounts->pruneUnlinkedAccounts(),
            $this->wowSync->pruneUnlinkedCharacters()
        ));
    }
}
