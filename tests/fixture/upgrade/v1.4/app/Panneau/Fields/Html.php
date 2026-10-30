<?php

namespace App\Panneau\Fields;

use Panneau\Fields\Html as BaseHtml;

class Html extends BaseHtml
{
    public function attributes(): ?array
    {
        return ['toolbar' => ['bold', 'italic', 'link']];
    }
}
