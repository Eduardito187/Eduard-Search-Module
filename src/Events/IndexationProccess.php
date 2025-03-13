<?php

namespace Eduard\Search\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IndexationProccess
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var int
     */
    public $countItems;

    /**
     * @var int
     */
    public $idClient;

    /**
     * @var int
     */
    public $idIndex;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($countItems, $idClient, $idIndex)
    {
        $this->countItems = $countItems;
        $this->idClient = $idClient;
        $this->idIndex = $idIndex;
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
