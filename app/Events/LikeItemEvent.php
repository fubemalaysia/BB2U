<?php

namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class LikeItemEvent extends Event implements ShouldBroadcast {

  use SerializesModels;

  public $itemId;
  public $item;
  public $status;

  /**
   * Create a new event instance.
   *
   * @return void
   */
  public function __construct($like) {
    //
    $this->itemId = $like->item_id;
    $this->item = $like->item;
    $this->status = $like->status;
  }

  /**
   * Get the channels the event should be broadcast on.
   *
   * @return array
   */
  public function broadcastOn() {
    return ['LikeItemAction'];
  }

}
