<?php

namespace Folklore\Panneau\Composers;

use Illuminate\View\View;

class IndexComposer
{
    public function compose(View $view)
    {
        $view->definition = app('panneau')->definition();
        $view->locale = app()->getLocale();
        $view->messages = $this->getMessages();
    }

    protected function getMessages()
    {
        return [];
    }
}
