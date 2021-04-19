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
        $view->props = array_merge(isset($view->props) ? $view->props : [], [
            'definition' => $view->definition,
        ]);
    }
}
