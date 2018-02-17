<?php

namespace Folklore\Panneau\Composers;

use Illuminate\View\View;

class IndexComposer
{
    protected $messagesFiles = [
        'forms',
        'list',
        'core'
    ];

    public function compose(View $view)
    {
        $view->definition = app('panneau')->definition();
        $view->locale = app()->getLocale();
        $view->messages = $this->getMessages();
    }

    protected function getMessages()
    {
        $messages = [];
        $messages[$locale] = [];
        foreach ($this->messagesFiles as $file) {
            $texts = trans('panneau::'.$file, [], $locale);
            if (is_null($texts)) {
                continue;
            }
            $texts = is_string($texts) ? [$texts] : array_dot($texts);
            foreach ($texts as $key => $value) {
                $key = sizeof($texts) === 1 && $key === 0 ? $file : ($file.'.'.$key);
                $messages[$locale][$key] = preg_replace(
                    '/\:([a-z][a-z0-9\_\-]+)/',
                    '%{$1}',
                    $value
                );
            }
        }
        return $messages;
    }
}
