<?php

namespace App\Panneau\Resources;

use App\Panneau\Fields\Customer;
use App\Panneau\Fields\Fields;
use Panneau\Fields\Date;
use Panneau\Fields\Email;
use Panneau\Fields\Number;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Orders extends Resource
{
    public static $repository = \App\Repositories\Orders::class;

    public static $controller = \App\Panneau\Http\Controllers\OrdersController::class;

    public static $jsonResource = \App\Http\Resources\OrderResource::class;

    public static $jsonCollection = \App\Http\Resources\OrdersCollection::class;

    public static $settings = [
        'canCreate' => false,
        'indexIsPaginated' => true,
    ];

    public function name(): string
    {
        return trans('panneau.orders.name');
    }

    public function translationsNamespace(): ?string
    {
        return 'panneau.' . $this->id();
    }

    public function settings(): ?array
    {
        return array_merge(parent::settings(), ['canExport' => true]);
    }

    public function index(): ?array
    {
        return [
            'actions' => [
                ['id' => 'export', 'label' => 'Export', 'href' => route('admin.orders.export'), 'external' => true],
            ],
            'columns' => [
                'id',
                ['label' => 'Email', 'component' => 'text', 'path' => 'email'],
                ['id' => 'status', 'component' => 'label', 'path' => 'status'],
                ['id' => 'actions', 'actions' => ['show', 'edit']],
            ],
            'filters' => [
                ['name' => 'search', 'component' => 'search'],
            ],
        ];
    }

    public function fields(): array
    {
        return [
            Text::make('number')->isDisabled()->withTransLabel('panneau.fields.number'),
            Email::make('email')->isRequired(),
            Number::make('total')->isDisabled(),
            Date::make('paid_at'),
            Select::make('status')->withOptions(['pending', 'paid'])->withDefaultValue('pending'),
            Customer::make('customer'),
            Fields::make('address')->withFields([
                Text::make('street'),
                Text::make('city'),
            ]),
        ];
    }
}
