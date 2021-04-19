<?php

namespace Panneau\Composers;

use Illuminate\View\View;

class AppComposer
{
    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('props', [
            'definition' => $view->definition,
        ]);
    }
}
