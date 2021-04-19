<?php

namespace Panneau\Composers;

use Illuminate\View\View;
use Panneau\Contracts\Panneau as PanneauContract;

class PanneauComposer
{
    protected $panneau;

    /**
     * Create a new profile composer.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(PanneauContract $panneau)
    {
        $this->panneau = $panneau;
    }

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->definition = $this->panneau->definition();
    }
}
