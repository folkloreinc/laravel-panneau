<?php

namespace Panneau\Events;

use Illuminate\Http\Request;

class HandlingRequest
{
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
