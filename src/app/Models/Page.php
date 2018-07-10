<?php

namespace App\Models;

use Folklore\Panneau\Models\Page as BasePage;
use Folklore\Panneau\Contracts\Page as PageContract;

class Page extends BasePage
{
    protected $hidden = [
        'slug_fr',
        'slug_en',
        'blocks',
        'pages',
        'pictures',
        'audios',
        'videos',
        'order',
        'deleted_at'
    ];

    protected $appends = [
        'url',
        'urls',
    ];

    protected $jsonSchemasReducers = [
        \Folklore\Panneau\Reducers\BlocksReducer::class,
        \Folklore\Panneau\Reducers\PagesReducer::class,
        \Folklore\Panneau\Reducers\MediasReducer::class,
        \Folklore\Panneau\Reducers\SlugReducer::class,
        \App\Reducers\PageExtractReducer::class,
    ];

    protected function getUrlAttribute()
    {
        return $this->getUrl();
    }

    protected function getUrlsAttribute()
    {
        $urls = [];
        $locales = config('locale.locales', []);
        foreach ($locales as $locale) {
            $urls[$locale] = $this->getUrl($locale);
        }
        return $urls;
    }
    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        $locale = app()->getLocale();
        return 'slug_'.$locale;
    }
}
