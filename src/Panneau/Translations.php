<?php

namespace Panneau;

use Panneau\Contracts\Panneau as PanneauContract;
use Illuminate\Contracts\Translation\Translator;

class Translations
{
    protected $app;
    protected $translator;
    protected $panneau;
    protected $translations;
    protected $translationsNamespace = 'panneau';

    public function __construct(
        $app,
        Translator $translator,
        PanneauContract $panneau
    ) {
        $this->app = $app;
        $this->translator = $translator;
        $this->panneau = $panneau;
    }

    public function getTranslations()
    {
        return $this->translations ?: $this->getDefaultTranslations();
    }

    public function setTranslations($translations)
    {
        $this->translations = $translations;
        return $this;
    }

    public function getDefaultTranslations()
    {
        return $this->app['config']->get('panneau.localization.translations');
    }

    public function getTranslationsNamespace()
    {
        return $this->translationsNamespace;
    }

    public function setTranslationsNamespace($namespace)
    {
        $this->translationsNamespace = $namespace;
        return $this;
    }

    public function getMessages()
    {
        $translations = $this->getTranslations();
        $namespace = $this->getTranslationsNamespace();
        $messages = [];
        foreach ($this->panneau->locales() as $locale) {
            $messages[$locale] = [];
            foreach ($translations as $file) {
                $texts = $this->translator->has($file, $locale)
                    ? $this->translator->trans($file, [], $locale)
                    : null;
                if (is_null($texts)) {
                    continue;
                }
                $fileKey = !empty($namespace)
                    ? preg_replace(
                        '/^' . preg_quote($namespace, '/') . '\:\:/',
                        '',
                        $file
                    )
                    : $file;
                $texts = is_string($texts)
                    ? [$file => $texts]
                    : array_dot($texts);
                foreach ($texts as $key => $value) {
                    $key = $fileKey . '.' . $key;
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
