<?php

namespace Folklore\Panneau\Composers;

use Illuminate\View\View;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Auth;

class IndexComposer
{
    protected $locales;
    protected $locale;
    protected $translations;
    protected $translator;

    public function __construct(Application $app, Translator $translator)
    {
        $locales = panneau()->locales();
        $locale = $app['config']->get('panneau.localization.locale', null);
        if (is_null($locale)) {
            $locale = $app->getLocale();
        }
        $this->locales = $locales;
        $this->locale = in_array($locale, $locales) ? $locale : $locales[0];
        $this->translations = $app['config']->get('panneau.localization.translations', [
            'panneau::forms',
            'panneau::list',
            'panneau::core',
            'panneau::fields',
            'panneau::layouts',
        ]);
        $this->translator = $translator;
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
        foreach ($this->locales as $locale) {
            $messages[$locale] = [];
            foreach ($this->translations as $file) {
                $texts = $this->translator->has($file, $locale) ? $this->translator->trans($file, [], $locale) : null;
                if (is_null($texts)) {
                    continue;
                }
                $fileKey = preg_replace('/^panneau\:\:/', '', $file);
                $texts = is_string($texts) ? [$file => $texts] : array_dot($texts);
                foreach ($texts as $key => $value) {
                    $key = $fileKey.'.'.$key;
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
