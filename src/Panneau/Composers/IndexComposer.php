<?php

namespace Panneau\Composers;

use Illuminate\View\View;
use Illuminate\Contracts\Foundation\Application;
use Panneau\Contracts\Panneau;
use Panneau\Contracts\Definition;
use Auth;

class IndexComposer
{
    protected $locales;
    protected $locale;
    protected $translations;
    protected $translator;

    public function __construct(Panneau $panneau, Definition $definition)
    {
        $locale = $panneau->locale();
        $this->definition = $definition;
        $this->auth = $panneau->guard();
        $this->locale = in_array($locale, $this->locales) ? $locale : $this->locales[0];
        $this->locales = $panneau->locales();
    }

    public function compose(View $view)
    {
        $view->definition = $this->definition;
        $view->locale = $this->locale;
        $view->user = $this->auth->check() ? $this->auth->user() : null;
    }
}
