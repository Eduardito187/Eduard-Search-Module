<?php

namespace Eduard\Search\Listeners;

use Eduard\Search\Events\IndexationProccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Eduard\Search\Helpers\History\HistoryIndexation;

class AfterIndexationProccess implements ShouldQueue
{
    /**
     * The name of the connection the job should be sent to.
     *
     * @var string|null
     */
    public string $connection = 'database';

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public string $queue = 'indexation_proccess';

    /**
     * @var HistoryIndexation
     */
    protected $historyIndexation;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(HistoryIndexation $historyIndexation)
    {
        $this->historyIndexation = $historyIndexation;
    }

    /**
     * Handle the event.
     *
     * @param IndexationProccess $event
     * @return void
     */
    public function handle(IndexationProccess $event)
    {
        $this->historyIndexation->saveIndexationHistory(
            $event->countItems,
            $event->idClient,
            $event->idIndex
        );
    }
}
