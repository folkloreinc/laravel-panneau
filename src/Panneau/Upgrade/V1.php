<?php

namespace Panneau\Upgrade;

/**
 * What the upgrade knows of laravel-panneau 1.3 and 1.4, and of the Panneau fields of
 * laravel-folklore 1.1: their classes, and what each becomes in 5.x.
 *
 * @internal
 */
final class V1
{
    public const RESOURCE = 'Panneau\\Support\\Resource';

    public const RESOURCE_TYPE = 'Panneau\\Support\\ResourceType';

    public const RESOURCE_TYPE_CONTRACT = 'Panneau\\Contracts\\ResourceType';

    public const FIELD = 'Panneau\\Support\\Field';

    public const LOCALIZED_FIELD = 'Panneau\\Support\\LocalizedField';

    public const RESOURCE_CONTROLLER = 'Panneau\\Http\\Controllers\\ResourceController';

    public const CONTROLLER = 'Panneau\\Http\\Controllers\\Controller';

    public const DEFINITION = 'Panneau\\Definition';

    public const DEFINITION_CONTRACT = 'Panneau\\Contracts\\Definition';

    public const COMPOSERS = ['Panneau\\Composers\\AppComposer', 'Panneau\\Composers\\PanneauComposer'];

    public const GROUP_BUILT_IN = 'built-in';

    public const GROUP_ITEMS = 'Items';

    public const GROUP_FIELDS = 'Fields';

    public const GROUP_RELATION = 'ResourceItem and ResourceItems';

    public const GROUP_LOCALIZED = 'Localized';

    public const GROUP_FIELD = 'Field';

    public const GROUP_FOLKLORE = 'laravel-folklore';

    /**
     * Field classes: their component, what they become, and their group.
     *
     * @var array<string, array{string, string, string}>
     */
    public const FIELDS = [
        'Panneau\\Fields\\Text' => ['text', 'Text (Textarea with isTextarea())', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Html' => ['html', 'RichText', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Email' => ['email', 'Email', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Url' => ['url', 'Url', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Telephone' => ['telephone', 'Tel', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Password' => ['password', 'manual: 5.x has no password field', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Number' => ['number', 'Number', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Color' => ['color', 'Color', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Date' => ['date', 'Date', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\DateTime' => ['date-time', 'DateTime', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Select' => ['select', 'Select', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Radios' => ['radios', 'Select', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Toggle' => ['toggle', 'Boolean', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Embed' => ['embed', 'Embed', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Upload' => ['upload', 'Media', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Uploads' => ['items', 'Media->multiple()', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Media' => ['media', 'Media', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Medias' => ['items', 'Media->multiple()', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Image' => ['image', 'Image', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\ImageUpload' => ['image_upload', 'Image', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Images' => ['items', 'Image->multiple()', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Video' => ['video', "Media->accept(['video/*'])", self::GROUP_BUILT_IN],
        'Panneau\\Fields\\VideoUpload' => ['video_upload', "Media->accept(['video/*'])", self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Videos' => ['items', "Media->accept(['video/*'])->multiple()", self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Audio' => ['audio', "Media->accept(['audio/*'])", self::GROUP_BUILT_IN],
        'Panneau\\Fields\\AudioUpload' => ['audio_upload', "Media->accept(['audio/*'])", self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Audios' => ['items', "Media->accept(['audio/*'])->multiple()", self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Document' => ['document', 'Media, on a library of documents', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\DocumentUpload' => ['document_upload', 'Media, on a library of documents', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Documents' => ['items', 'Media->multiple(), on a library of documents', self::GROUP_BUILT_IN],
        'Panneau\\Fields\\Items' => ['items', 'Repeater', self::GROUP_ITEMS],
        'Panneau\\Fields\\Fields' => ['fields', 'Group', self::GROUP_FIELDS],
        'Panneau\\Fields\\Item' => ['item', 'Relation', self::GROUP_RELATION],
        'Panneau\\Fields\\ResourceItem' => ['resource-item', 'Relation', self::GROUP_RELATION],
        'Panneau\\Fields\\ResourceItems' => ['items', 'Relation->multiple()', self::GROUP_RELATION],
        'Panneau\\Fields\\TextLocalized' => ['localized', 'Text->localized() (Textarea with isTextarea())', self::GROUP_LOCALIZED],
        'Panneau\\Fields\\HtmlLocalized' => ['localized', 'RichText->localized()', self::GROUP_LOCALIZED],
        'Panneau\\Fields\\UrlLocalized' => ['localized', 'Url->localized()', self::GROUP_LOCALIZED],
        'Panneau\\Fields\\EmbedLocalized' => ['localized', 'Embed->localized()', self::GROUP_LOCALIZED],
        'Panneau\\Fields\\UploadLocalized' => ['localized', 'Media->localized()', self::GROUP_LOCALIZED],
        'Panneau\\Fields\\MediaLocalized' => ['localized', 'Media->localized()', self::GROUP_LOCALIZED],
        'Panneau\\Fields\\ImageLocalized' => ['localized', 'Image->localized()', self::GROUP_LOCALIZED],
        'Panneau\\Fields\\VideoLocalized' => ['localized', "Media->accept(['video/*'])->localized()", self::GROUP_LOCALIZED],
        'Panneau\\Fields\\AudioLocalized' => ['localized', "Media->accept(['audio/*'])->localized()", self::GROUP_LOCALIZED],
        'Panneau\\Fields\\DocumentLocalized' => ['localized', 'Media->localized(), on a library of documents', self::GROUP_LOCALIZED],
        self::LOCALIZED_FIELD => ['localized', 'the 5.x field of field(), ->localized()', self::GROUP_LOCALIZED],
        self::FIELD => ['', 'manual: a built-in type, or a field type with a JS plugin', self::GROUP_FIELD],
        'Folklore\\Panneau\\Fields\\Page' => ['item', 'the Page field of laravel-folklore 2 (a relation to the pages)', self::GROUP_FOLKLORE],
        'Folklore\\Panneau\\Fields\\Block' => ['resource-item', 'manual: a block type of the Blocks field of laravel-folklore 2', self::GROUP_FOLKLORE],
        'Folklore\\Panneau\\Fields\\Blocks' => ['items', 'the Blocks field of laravel-folklore 2 (a repeater of block types)', self::GROUP_FOLKLORE],
        'Folklore\\Panneau\\Fields\\PageSlug' => ['page-slug', 'the PageSlug field of laravel-folklore 2 (a slug with unique() and url())', self::GROUP_FOLKLORE],
        'Folklore\\Panneau\\Fields\\PageSlugLocalized' => ['page-slug-localized', 'the PageSlug field of laravel-folklore 2, localized by default', self::GROUP_FOLKLORE],
    ];

    /**
     * Static properties of 1.x resources, and what they become.
     *
     * @var array<string, string>
     */
    public const RESOURCE_PROPERTIES = [
        'repository' => 'repository()',
        'jsonResource' => 'jsonResource(), or toItem()',
        'jsonCollection' => 'nothing: lists serialize each item like a single item',
        'controller' => 'hooks (creating(), updating(), saved()…) and actions: see the controllers',
        'types' => 'types() (resource types are not built yet in 5.x)',
        'settings' => 'see the settings',
        'index' => 'list()',
        'components' => 'manual: a JS plugin',
        'forms' => 'layout()',
        'attributes' => 'manual: settings() or a JS plugin',
        'onlyDeclaredFields' => 'nothing: 5.x only saves declared fields',
    ];

    /**
     * Settings of 1.x resources, and what they become.
     *
     * @var array<string, string>
     */
    public const RESOURCE_SETTINGS = [
        'hideInNavbar' => 'manual: the navigation is not configurable yet in 5.x',
        'indexIsPaginated' => 'nothing: lists are paginated',
        'canCreate' => 'the create ability of the policy',
    ];

    /**
     * The defaults of the settings of 1.x resources.
     *
     * @var array<string, bool>
     */
    public const RESOURCE_DEFAULT_SETTINGS = [
        'hideInNavbar' => false,
        'indexIsPaginated' => true,
        'canCreate' => true,
    ];

    /**
     * Methods of 1.x ResourceController that subclasses override, and what they become.
     *
     * @var array<string, string>
     */
    public const CONTROLLER_METHODS = [
        'getResourceFromRequest' => 'nothing: the resource comes from the route',
        'getIndexQueryFromRequest' => 'scope(), or the filters of list()',
        'getStoreDataFromRequest' => 'creating()',
        'getUpdateDataFromRequest' => 'updating()',
        'getDeclaredFieldsData' => 'nothing: 5.x only saves declared fields',
        'getPageFromRequest' => 'nothing: the protocol\'s page',
        'getPageCountFromRequest' => 'nothing: the protocol\'s perPage',
        'isPaginated' => 'nothing: lists are paginated',
        'getCloneJsonResourceFromItem' => 'DuplicateAction (not built yet)',
        'authorizeResourceAction' => 'the policy',
        'authorizeItemAction' => 'the policy',
        'index' => 'list() and scope()',
        'show' => 'toItem()',
        'store' => 'creating() and created()',
        'update' => 'updating() and updated()',
        'destroy' => 'deleting() and deleted()',
        'delete' => 'DeleteAction',
        'duplicate' => 'DuplicateAction (not built yet)',
        'clone' => 'DuplicateAction (not built yet)',
        'create' => 'nothing: the app renders forms',
        'edit' => 'nothing: the app renders forms',
    ];

    /**
     * Static methods that configure 1.x classes at boot, and what replaces them.
     *
     * @var array<string, string>
     */
    public const RESOLVERS = [
        'Panneau\\Support\\LocalizedField::setLocalesResolver' => "'locales' in config/panneau.php",
        'Panneau\\Fields\\Upload::setEndpoint' => "nothing: uploads are 'uploads' in config/panneau.php",
        'Panneau\\Fields\\Upload::setEndpointResolver' => "nothing: uploads are 'uploads' in config/panneau.php",
        'Panneau\\Fields\\Item::setRequestUrlResolver' => 'nothing: relations read their resource',
        'Folklore\\Panneau\\Fields\\PageSlug::setRoutesResolver' => "'site.pages.routes' of laravel-folklore 2, or url() on the PageSlug field",
    ];

    /**
     * Request macros of 1.x, and what replaces them.
     *
     * @var array<string, string>
     */
    public const REQUEST_MACROS = [
        'isPanneau' => "\$request->routeIs('panneau.*')",
        'isPanneauIndex' => "\$request->routeIs('panneau.api.resources.index')",
        'isPanneauStore' => "\$request->routeIs('panneau.api.resources.store')",
        'isPanneauDelete' => "\$request->routeIs('panneau.api.resources.destroy')",
        'isInPanneau' => "\$request->routeIs('panneau.*') && \$request->user()?->can('viewPanneau')",
    ];

    /**
     * Helpers of 1.x, and what replaces them.
     *
     * @var array<string, string>
     */
    public const HELPERS = [
        'panneau_route' => "route('panneau.…'): the routes are panneau.app and panneau.api.*",
        'panneau_resource_route' => "route('panneau.api.resources.…', ['resource' => …])",
    ];

    /**
     * Files that projects generated, now provided by the package, by class name.
     *
     * @var array<string, string>
     */
    public const GENERATED_CLASSES = [
        'HomeController' => 'the page of the app (panneau.app)',
        'AccountController' => 'the account of the app (not built yet)',
        'UploadController' => "direct uploads ('uploads' in config/panneau.php)",
        'UploadRequest' => "direct uploads ('uploads' in config/panneau.php)",
        'AccountUpdateRequest' => 'the account of the app (not built yet)',
        'PanneauPolicy' => 'the viewPanneau gate, and a policy per item class',
        'AppComposer' => 'the boot config of the page (Panneau::bootConfig())',
        'PanneauComposer' => 'the boot config of the page (Panneau::bootConfig())',
    ];

    /**
     * Keys of 1.x configs that 5.x configs don't have, to tell them apart.
     */
    public const CONFIG_KEYS = ['routes', 'intl', 'only_declared_fields'];

    /**
     * The group and target of a field class of 1.x or laravel-folklore 1.1.
     *
     * @return array{string, string, string}|null
     */
    public static function field(string $class): ?array
    {
        return self::FIELDS[ltrim($class, '\\')] ?? null;
    }
}
