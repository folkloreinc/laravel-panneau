<?php

namespace Folklore\Panneau\Composers;

use Illuminate\View\View;
use Illuminate\Contracts\Foundation\Application;
use Auth;

class IndexComposer
{
    protected $messagesFiles = [
        'forms',
        'list',
        'core',
        'fields',
        'layouts',
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
        $view->user = Auth::check() ? Auth::user() : null;
    }

    protected function getMessages()
    {
        $messages = [];
        $locales = panneau()->locales();
        foreach ($locales as $locale) {
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
        }
        return $messages;
    }
}
