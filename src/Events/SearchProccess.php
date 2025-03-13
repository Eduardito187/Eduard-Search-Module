<?php

namespace Eduard\Search\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SearchProccess
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var int
     */
    public $idClient;

    /**
     * @var int
     */
    public $idIndex;

    /**
     * @var string
     */
    public $customerUuid;

    /**
     * @var string
     */
    public $query;

    /**
     * @var int
     */
    public $countItems;

    /**
     * @var float
     */
    public $timeExecution;

    /**
     * @var string
     */
    public $code;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($idClient, $idIndex, $customerUuid, $query, $countItems, $timeExecution, $code)
    {
        $this->idClient = $idClient;
        $this->idIndex = $idIndex;
        $this->customerUuid = $customerUuid;
        $this->query = $query;
        $this->countItems = $countItems;
        $this->timeExecution = $timeExecution;
        $this->code = $code;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        //return new PrivateChannel('channel-name');
        return [];
    }
}