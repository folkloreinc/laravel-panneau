<?php

namespace Panneau\Events;

use Illuminate\Http\Request;
use Illuminate\Foundation\Events\Dispatchable;

class HandlingRequest
{
    use Dispatchable;

    public $request;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}
