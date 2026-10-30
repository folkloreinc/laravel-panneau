<?php

namespace Panneau\Rector;

use Panneau\Upgrade\V1;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt;
use Rector\Rector\AbstractRector;

/**
 * Converts the 1.x fields that a chain makes, `TextLocalized::make('title')->isRequired()
 * ->withTransLabel('fields.title')`, to 5.x: `Text::make('title')->localized()->required()
 * ->label(__('fields.title'))`. What it can't convert is left out of the chain, with a
 * `// TODO(panneau-5)` comment above it.
 *
 * @internal
 */
final class FieldCallsRector extends AbstractRector
{
    private const FIELDS = 'Panneau\\Fields\\';

    private const FOLKLORE = 'Folklore\\Panneau\\Fields\\';

    /**
     * 1.x field classes: the 5.x class, the calls it adds, and what's left to do.
     *
     * @var array<string, array{0: string, 1: list<string>, 2?: string}>
     */
    private const CLASSES = [
        self::FIELDS.'Text' => [self::FIELDS.'Text', []],
        self::FIELDS.'TextLocalized' => [self::FIELDS.'Text', ['localized']],
        self::FIELDS.'Html' => [self::FIELDS.'RichText', []],
        self::FIELDS.'HtmlLocalized' => [self::FIELDS.'RichText', ['localized']],
        self::FIELDS.'Email' => [self::FIELDS.'Email', []],
        self::FIELDS.'Url' => [self::FIELDS.'Url', []],
        self::FIELDS.'UrlLocalized' => [self::FIELDS.'Url', ['localized']],
        self::FIELDS.'Telephone' => [self::FIELDS.'Tel', []],
        self::FIELDS.'Password' => [self::FIELDS.'Password', [], 'Password: 5.x has no password field'],
        self::FIELDS.'Number' => [self::FIELDS.'Number', []],
        self::FIELDS.'Color' => [self::FIELDS.'Color', []],
        self::FIELDS.'Date' => [self::FIELDS.'Date', []],
        self::FIELDS.'DateTime' => [self::FIELDS.'DateTime', []],
        self::FIELDS.'Select' => [self::FIELDS.'Select', []],
        self::FIELDS.'Radios' => [self::FIELDS.'Select', []],
        self::FIELDS.'Toggle' => [self::FIELDS.'Boolean', []],
        self::FIELDS.'Embed' => [self::FIELDS.'Embed', []],
        self::FIELDS.'EmbedLocalized' => [self::FIELDS.'Embed', ['localized']],
        self::FIELDS.'Upload' => [self::FIELDS.'Media', []],
        self::FIELDS.'Uploads' => [self::FIELDS.'Media', ['multiple']],
        self::FIELDS.'UploadLocalized' => [self::FIELDS.'Media', ['localized']],
        self::FIELDS.'Media' => [self::FIELDS.'Media', []],
        self::FIELDS.'Medias' => [self::FIELDS.'Media', ['multiple']],
        self::FIELDS.'MediaLocalized' => [self::FIELDS.'Media', ['localized']],
        self::FIELDS.'Image' => [self::FIELDS.'Image', []],
        self::FIELDS.'ImageUpload' => [self::FIELDS.'Image', []],
        self::FIELDS.'Images' => [self::FIELDS.'Image', ['multiple']],
        self::FIELDS.'ImageLocalized' => [self::FIELDS.'Image', ['localized']],
        self::FIELDS.'Video' => [self::FIELDS.'Media', ['accept:video/*']],
        self::FIELDS.'VideoUpload' => [self::FIELDS.'Media', ['accept:video/*']],
        self::FIELDS.'Videos' => [self::FIELDS.'Media', ['accept:video/*', 'multiple']],
        self::FIELDS.'VideoLocalized' => [self::FIELDS.'Media', ['accept:video/*', 'localized']],
        self::FIELDS.'Audio' => [self::FIELDS.'Media', ['accept:audio/*']],
        self::FIELDS.'AudioUpload' => [self::FIELDS.'Media', ['accept:audio/*']],
        self::FIELDS.'Audios' => [self::FIELDS.'Media', ['accept:audio/*', 'multiple']],
        self::FIELDS.'AudioLocalized' => [self::FIELDS.'Media', ['accept:audio/*', 'localized']],
        self::FIELDS.'Document' => [self::FIELDS.'Media', [], 'a document: name a library of documents with resource(), or accept()'],
        self::FIELDS.'DocumentUpload' => [self::FIELDS.'Media', [], 'a document: name a library of documents with resource(), or accept()'],
        self::FIELDS.'Documents' => [self::FIELDS.'Media', ['multiple'], 'documents: name a library of documents with resource(), or accept()'],
        self::FIELDS.'DocumentLocalized' => [self::FIELDS.'Media', ['localized'], 'a document: name a library of documents with resource(), or accept()'],
        self::FIELDS.'Items' => [self::FIELDS.'Repeater', [], 'a repeater: give it its fields() or types()'],
        self::FIELDS.'Fields' => [self::FIELDS.'Group', []],
        self::FIELDS.'Item' => [self::FIELDS.'Relation', [], 'a relation: name the related resource with resource()'],
        self::FIELDS.'ResourceItem' => [self::FIELDS.'Relation', []],
        self::FIELDS.'ResourceItems' => [self::FIELDS.'Relation', ['multiple']],
        self::FOLKLORE.'PageSlugLocalized' => [self::FOLKLORE.'PageSlug', []],
    ];

    /**
     * 1.x builder methods: the 5.x method (null: removed), and what's left to do.
     *
     * @var array<string, array{0: string|null, 1?: string}>
     */
    private const METHODS = [
        'withTransLabel' => ['label'],
        'withLabel' => ['label'],
        'withTransHelpText' => ['help'],
        'withHelpText' => ['help'],
        'isRequired' => ['required'],
        'isNotRequired' => [null],
        'isDisabled' => ['readOnly'],
        'isEnabled' => [null],
        'isText' => [null],
        'isNullable' => [null],
        'isNotNullable' => [null],
        'showInForm' => [null],
        'hideInForm' => [null, '%s was hidden from the form (hideInForm()): 5.x has no field that shows in lists only'],
        'withFields' => ['fields'],
        'onlyForTypes' => [null, '%s was only for some types (onlyForTypes()): resource types are not built yet in 5.x'],
        'exceptForTypes' => [null, '%s was not for some types (exceptForTypes()): resource types are not built yet in 5.x'],
        'onlyTypes' => [null, '%s was only for some types (onlyTypes()): resource types are not built yet in 5.x'],
        'exceptTypes' => [null, '%s was not for some types (exceptTypes()): resource types are not built yet in 5.x'],
        'withDefaultValue' => ['default'],
        'withOptions' => ['options'],
        'withRules' => ['rules'],
        'withSettings' => ['settings'],
        'withResource' => ['resource'],
        'withButton' => [null],
        'withoutReset' => [null],
        'uploadOnly' => [null],
        'withEndpoint' => [null],
        'withAttributes' => [null, '%s had attributes for the JS (withAttributes()): settings(), or a JS plugin'],
        'withComponents' => [null, '%s had its own components (withComponents()): a JS plugin'],
        'withComponent' => [null, '%s had its own component (withComponent()): a JS plugin'],
        'withSiblingFields' => [null, '%s had sibling fields (withSiblingFields()): the layout()'],
        'withQuery' => [null, '%s had a query (withQuery()): the scope() of the related resource'],
        'withTypes' => [null, '%s had types (withTypes()): a resource whose scope() has the types'],
        'withoutType' => [null, "%s left out a type (withoutType()): exceptTypes('id'), by id"],
    ];

    /**
     * Methods that a 5.x class keeps, by class.
     *
     * @var array<string, list<string>>
     */
    private const KEPT = [
        self::FOLKLORE.'Blocks' => ['onlyTypes', 'exceptTypes'],
    ];

    public function __construct(private readonly ProjectClasses $classes) {}

    public function getNodeTypes(): array
    {
        return [ArrayItem::class, Stmt\Expression::class, Stmt\Return_::class];
    }

    /**
     * @param  ArrayItem|Stmt\Expression|Stmt\Return_  $node
     */
    public function refactor(Node $node): ?Node
    {
        $expr = $node instanceof ArrayItem ? $node->value : $node->expr;
        $assign = $expr instanceof Expr\Assign ? $expr : null;
        $chain = $assign !== null ? $assign->expr : $expr;
        if ($chain === null) {
            return null;
        }
        $todos = [];
        $converted = $this->convert($chain, $todos);
        if ($converted === null) {
            // A 1.x class that keeps its name, with something left to do
            return Todo::add($node, $todos) ? $node : null;
        }
        if ($assign !== null) {
            $assign->expr = $converted;
        } elseif ($node instanceof ArrayItem) {
            $node->value = $converted;
        } else {
            $node->expr = $converted;
        }
        Todo::add($node, $todos);

        return $node;
    }

    /**
     * The 5.x chain, or null when it isn't a chain that makes a 1.x field.
     *
     * @param  list<string>  $todos
     */
    private function convert(Expr $chain, array &$todos): ?Expr
    {
        $calls = [];
        $root = $chain;
        while ($root instanceof Expr\MethodCall) {
            array_unshift($calls, $root);
            $root = $root->var;
        }
        $isMake = $root instanceof Expr\StaticCall && $root->class instanceof Name && $this->isName($root->name, 'make');
        if ((! $isMake && ! ($root instanceof Expr\New_ && $root->class instanceof Name))) {
            return null;
        }
        /** @var Expr\StaticCall|Expr\New_ $root */
        $class = (string) $this->getName($root->class);
        $base = $this->classes->nearest($class, array_keys(V1::FIELDS));
        if ($base === null) {
            return null;
        }
        $name = $this->fieldName($root);
        $isOwnClass = ! isset(self::CLASSES[$class]) && ! str_starts_with($class, self::FOLKLORE);
        [$target, $extras] = self::CLASSES[$class] ?? [$class, []];
        if (isset(self::CLASSES[$class][2])) {
            $todos[] = $name.' is '.self::CLASSES[$class][2];
        }

        $args = $root->getArgs();
        $label = $args[1] ?? null;
        $changed = $root instanceof Expr\New_ || $target !== $class || $extras !== [] || $label !== null;
        // The calls are changed in place, so that the chain keeps its layout
        $kept = [];
        $hasResource = false;
        foreach ($calls as $call) {
            $method = $this->getName($call->name);
            $hasResource = $hasResource || in_array($method, ['resource', 'withResource'], true);
            if ($method === null || in_array($method, self::KEPT[$target] ?? [], true)
                || ($isOwnClass && $this->classes->declares($class, $method))) {
                $kept[] = $call;

                continue;
            }
            if (! isset(self::METHODS[$method]) && $method !== 'isTextarea') {
                // A 1.x method that the 5.x class doesn't have would throw: it's left out
                if (! $isOwnClass && $this->lacks($target, $method)) {
                    $changed = true;
                    $todos[] = sprintf('%s called %s(), which %s doesn\'t have in 5.x: settings(), or a JS plugin', $name, $method, class_basename($target));

                    continue;
                }
                $kept[] = $call;

                continue;
            }
            $changed = true;
            if ($method === 'isTextarea') {
                if ($target === self::FIELDS.'Text') {
                    $target = self::FIELDS.'Textarea';
                } else {
                    $todos[] = "{$name} was a textarea (isTextarea()): Textarea, or a JS plugin";
                }

                continue;
            }
            [$new] = self::METHODS[$method];
            if (isset(self::METHODS[$method][1])) {
                $todos[] = sprintf(self::METHODS[$method][1], $name);
            }
            if ($new === null) {
                continue;
            }
            $call->name = new Node\Identifier($new);
            if (in_array($method, ['withTransLabel', 'withTransHelpText'], true)) {
                $call->args = [new Arg($this->nodeFactory->createFuncCall('__', $call->getArgs()))];
            } elseif ($method === 'isRequired' && $call->args !== []) {
                $todos[] = "{$name} was required in some locales (isRequired([...])): 5.x requires every locale";
                $call->args = [];
            }
            $kept[] = $call;
        }
        if (! $changed) {
            return null;
        }
        if ($target === self::FIELDS.'Relation' && $class === self::FIELDS.'ResourceItem' && ! $hasResource) {
            $todos[] = "{$name} is a relation: name the related resource with resource()";
        }

        if ($root instanceof Expr\StaticCall) {
            $root->class = new FullyQualified($target);
            $root->args = array_slice($root->args, 0, 1);
            $field = $root;
        } else {
            $field = new Expr\StaticCall(new FullyQualified($target), 'make', array_slice($args, 0, 1));
        }
        foreach ($extras as $extra) {
            $field = str_starts_with($extra, 'accept:')
                ? new Expr\MethodCall($field, 'accept', [new Arg($this->nodeFactory->createArray([substr($extra, 7)]))])
                : new Expr\MethodCall($field, $extra);
        }
        if ($label !== null) {
            $field = new Expr\MethodCall($field, 'label', [$label]);
        }
        foreach ($kept as $call) {
            $call->var = $field;
            $field = $call;
        }

        return $field;
    }

    /**
     * Whether a 5.x field class of the package lacks a method. Classes it can't load, such as
     * the fields of other packages, are trusted.
     */
    private function lacks(string $class, string $method): bool
    {
        return str_starts_with($class, self::FIELDS) && class_exists($class) && ! method_exists($class, $method);
    }

    /**
     * The name of the field, for the comments: its first argument, as written.
     */
    private function fieldName(Expr\StaticCall|Expr\New_ $root): string
    {
        $name = $root->getArgs()[0]->value ?? null;

        return $name instanceof Node\Scalar\String_ ? "'{$name->value}'" : 'The field';
    }
}
