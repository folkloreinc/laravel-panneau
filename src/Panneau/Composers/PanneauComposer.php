<?php

namespace Panneau\Composers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Panneau\Contracts\Panneau as PanneauContract;

class PanneauComposer
{
    protected $panneau;

    /**
     * Create a new profile composer.
     *
     * @param  Request  $request
     * @return void
     */
    public function __construct(PanneauContract $panneau)
    {
        $this->panneau = $panneau;
    }

    /**
     * Bind data to the view.
     *
     * @return void
     */
    public function compose(View $view)
    {
        $view->definition = $this->panneau->definition();
    }
}
