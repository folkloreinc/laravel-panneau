<?php

namespace App\Panneau\Resources;

use App\Panneau\Fields\Customer;
use App\Panneau\Fields\Fields;
use Panneau\Fields\Email;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Invoices extends Resource
{
    public static $repository = \App\Repositories\Invoices::class;

    public static $jsonResource = \App\Http\Resources\InvoiceResource::class;

    public static $settings = [
        'canCreate' => false,
    ];

    public function translationsNamespace(): ?string
    {
        return 'panneau.' . $this->id();
    }

    public function index(): ?array
    {
        return [
            'columns' => [
                'number',
                ['label' => 'Email', 'component' => 'text', 'path' => 'email'],
                ['id' => 'actions', 'actions' => ['show', 'edit']],
            ],
        ];
    }

    public function fields(): array
    {
        return [
            Text::make('number')->isDisabled()->withTransLabel('panneau.fields.number'),
            Email::make('email')->isRequired(),
            Select::make('status')->withOptions(['pending', 'paid'])->withDefaultValue('pending'),
            Customer::make('customer')->withTransLabel('panneau.fields.customer'),
            Fields::make('address')->withFields([
                Text::make('street'),
            ]),
        ];
    }
}

?>
