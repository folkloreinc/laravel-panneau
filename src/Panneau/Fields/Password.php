<?php

namespace Panneau\Fields;

use Panneau\Support\Field;
use Illuminate\Http\Request;

class Password extends Text
{
    protected $withoutConfirmation = false;

    public function component(): string
    {
        return 'password';
    }

    public function sibblingFields(): ?array
    {
        return !$this->withoutConfirmation
            ? [
                Password::make($this->name() . '_confirmation')
                    ->withTransLabel('panneau.fields.password_confirmation_label')
                    ->withoutConfirmation(),
            ]
            : null;
    }

    public function rules(Request $request): ?array
    {
        $routeName = $request->route()->getName();
        return array_merge(
            preg_match('/\.store$/', $routeName) === 1 ? ['required'] : [],
            ['string', 'min:8'],
            !$this->withoutConfirmation ? ['confirmed'] : []
        );
    }

    public function withoutConfirmation()
    {
        $this->withoutConfirmation = true;
        return $this;
    }

    public function attributes(): ?array
    {
        $sibblingFields = $this->sibblingFields();
        if (!is_null($sibblingFields)) {
            return array_merge(parent::attributes(), [
                'sibblingFields' => collect($sibblingFields)->toArray(),
            ]);
        }
        return parent::attributes();
    }
}
