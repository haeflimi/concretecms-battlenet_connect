<?php

namespace BattlenetConnect\Command\Task;

use BattlenetConnect\Api\BattlenetApi;
use BattlenetConnect\BattlenetConfig;
use BattlenetConnect\Command\PruneBattlenetDataCommand;
use BattlenetConnect\Command\SyncWowCharactersCommand;
use BattlenetConnect\Wow\WowCharacterSync;
use Concrete\Core\Command\Batch\Batch;
use Concrete\Core\Command\Task\Controller\AbstractController;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\Runner\BatchProcessTaskRunner;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\TaskInterface;
use Concrete\Core\Error\UserMessageException;

class SyncBattlenetDataController extends AbstractController
{
    /** Characters per batch step: three requests per character */
    protected const CHUNK_SIZE = 10;

    /** @var BattlenetApi */
    protected $api;

    /** @var BattlenetConfig */
    protected $config;

    /** @var WowCharacterSync */
    protected $wowSync;

    public function __construct(BattlenetApi $api, BattlenetConfig $config, WowCharacterSync $wowSync)
    {
        $this->api = $api;
        $this->config = $config;
        $this->wowSync = $wowSync;
    }

    public function getName(): string
    {
        return t('Sync Battle.net Data');
    }

    public function getDescription(): string
    {
        return t('Updates the World of Warcraft characters of the users who linked their Battle.net account: level, item level, spec, guild, achievement points, Mythic+ rating and images.');
    }

    public function getConsoleCommandName(): string
    {
        return 'sync-battlenet-data';
    }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {
        if (!$this->api->hasCredentials()) {
            throw new UserMessageException(t('Please configure the client ID and secret in the Battle.net authentication type first.'));
        }
        if (!$this->config->isWowEnabled()) {
            throw new UserMessageException(t('Please choose the World of Warcraft versions in the Battle.net authentication type first.'));
        }

        $batch = Batch::create(t('Sync Battle.net Data'));
        $batch->add(new PruneBattlenetDataCommand());
        foreach (array_chunk($this->wowSync->getCharacterIDs(), self::CHUNK_SIZE) as $characterIDs) {
            $batch->add(new SyncWowCharactersCommand($characterIDs));
        }

        return new BatchProcessTaskRunner($task, $batch, $input, t('Syncing Battle.net data...'));
    }
}
