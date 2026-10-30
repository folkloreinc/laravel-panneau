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
-----
<?php

namespace App\Panneau\Resources;

use Panneau\Resources\Resource;
use App\Http\Resources\InvoiceResource;
use Panneau\Lists\ResourceList;
use App\Panneau\Fields\Customer;
use App\Panneau\Fields\Fields;
use Panneau\Fields\Email;
use Panneau\Fields\Select;
use Panneau\Fields\Text;

class Invoices extends Resource
{
    public function repository(): ?string
    {
        return \App\Repositories\Invoices::class;
    }

    public function toItem(object $item): array
    {
        return InvoiceResource::make($item)->resolve();
    }

    // TODO(panneau-5): canCreate is false: the create() of the policy denies it


    // TODO(panneau-5): 5.x never calls translationsNamespace() ('panneau.' . $this->id()): its name, plural and singular keys go to pluralLabel() and singularLabel(), with __()


    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(['number', 'email']);
    }

    public function fields(): array
    {
        return [
            Text::make('number')->readOnly()->label(__('panneau.fields.number')),
            Email::make('email')->required(),
            Select::make('status')->options(['pending', 'paid'])->default('pending'),
            Customer::make('customer')->label(__('panneau.fields.customer')),
            Fields::make('address')->withFields([
                Text::make('street'),
            ]),
        ];
    }
}

?>
