<?php

namespace Folklore\Panneau\Composers;

use Illuminate\View\View;
use Illuminate\Contracts\Foundation\Application;

class IndexComposer
{
    protected $messagesFiles = [
        'forms',
        'list',
        'core',
        'fields',
    ];

    public function __construct(Application $app)
    {
        $this->locale = $app->getLocale();
    }

    public function compose(View $view)
    {
        $view->definition = panneau()->definition();
        $view->locale = $this->locale;
        $view->messages = $this->getMessages();
    }

    protected function getMessages()
    {
        $messages = [];
        $messages[$this->locale] = [];
        foreach ($this->messagesFiles as $file) {
            $texts = trans('panneau::'.$file, [], $this->locale);
            if (is_null($texts)) {
                continue;
            }
            $texts = is_string($texts) ? [$texts] : array_dot($texts);
            foreach ($texts as $key => $value) {
                $key = sizeof($texts) === 1 && $key === 0 ? $file : ($file.'.'.$key);
                $messages[$this->locale][$key] = preg_replace(
                    '/\:([a-z][a-z0-9\_\-]+)/',
                    '%{$1}',
                    $value
                );
            }
        }
        return $messages;
    }
}
