<?php

namespace App\Panneau\Composers;

use Illuminate\Contracts\View\View;
use App\Panneau\PanneauDefinition;

class AppComposer
{
    public function compose(View $view)
    {
        $view->props = array_merge($view->props ?? [], [
            'user' => request()->user(),
            'isPanneau' => request()->isPanneau(),
            'definition' => new PanneauDefinition($view->definition),
        ]);
    }
}
