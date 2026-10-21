<?php

namespace Panneau\Tests\Unit\Actions;

use Illuminate\Support\Collection;
use LogicException;
use Panneau\Actions\Action;
use Panneau\Actions\ActionRefresh;
use Panneau\Actions\ActionResult;
use Panneau\Actions\ActionScope;
use Panneau\Actions\ActionValues;
use Panneau\Actions\ClientAction;
use Panneau\Actions\LinkAction;
use Panneau\Uploads\Upload;
use PHPUnit\Framework\TestCase;
use TestApp\Panneau\Actions\PublishPosts;

class ActionTest extends TestCase
{
    public function test_ids_and_labels_come_from_the_class_name(): void
    {
        $this->assertSame('publish-posts', (new PublishPosts)->id());
        $this->assertSame('Publish posts', (new PublishPosts)->label());
        $this->assertSame('send-newsletter', (new SendNewsletterAction)->id());
    }

    public function test_defaults(): void
    {
        $definition = (new SendNewsletterAction)->toDefinition();

        $this->assertSame([
            'id' => 'send-newsletter',
            'label' => 'Send newsletter',
            'kind' => 'server',
            'scope' => ['item', 'selection'],
        ], $definition);
    }

    public function test_link_and_client_actions_need_a_target(): void
    {
        $this->expectException(LogicException::class);

        LinkAction::make('preview')->toDefinition();
    }

    public function test_client_actions_need_a_component(): void
    {
        $this->expectException(LogicException::class);

        ClientAction::make('copy')->toDefinition();
    }

    public function test_link_actions_can_use_a_route(): void
    {
        $this->assertSame(
            ['id' => 'site', 'label' => 'Site', 'kind' => 'link', 'scope' => ['resource'], 'destructive' => true, 'confirm' => true, 'route' => 'site'],
            LinkAction::make('site')->route('site')->scope([ActionScope::Resource])->destructive()->confirm()->toDefinition(),
        );
    }

    public function test_results(): void
    {
        $result = new ActionResult(fn (object $item): string => (string) $item->id);
        $result
            ->failed((object) ['id' => 2], 'Broken')
            ->skipped('3', 'forbidden')
            ->succeeded((object) ['id' => 4])
            ->failed('4')
            ->succeeded('5')
            ->message('Done')
            ->refresh(ActionRefresh::List)
            ->redirect('/posts')
            ->download('https://example.com/export.csv', 'export.csv', new \DateTimeImmutable('2026-10-15T18:00:00Z'));

        $this->assertTrue($result->reported('2'));
        $this->assertFalse($result->reported('1'));
        $this->assertSame([
            'message' => 'Done',
            'summary' => ['succeeded' => 2, 'failed' => 2, 'skipped' => 1],
            'results' => [
                ['id' => '2', 'status' => 'failed', 'message' => 'Broken'],
                ['id' => '3', 'status' => 'skipped', 'reason' => 'forbidden'],
                ['id' => '4', 'status' => 'failed'],
            ],
            'refresh' => 'list',
            'redirect' => '/posts',
            'download' => ['url' => 'https://example.com/export.csv', 'filename' => 'export.csv', 'expiresAt' => '2026-10-15T18:00:00+00:00'],
        ], $result->toArray(['1', '2', '3']));
    }

    public function test_redirect_to_a_route(): void
    {
        $result = (new ActionResult(fn (object $item): string => ''))->redirect('', 'posts.show', ['id' => '1']);

        $this->assertSame(['route' => 'posts.show', 'params' => ['id' => '1']], $result->toArray()['redirect']);
    }

    public function test_values(): void
    {
        $values = new ActionValues(['notify' => '1', 'note' => 'Hi', 'nested' => ['a' => 1]]);

        $this->assertTrue($values->boolean('notify'));
        $this->assertFalse($values->boolean('missing'));
        $this->assertSame('Hi', $values->string('note'));
        $this->assertSame(1, $values->get('nested.a'));
        $this->assertTrue($values->has('nested.a'));
    }

    public function test_uploads_in_values(): void
    {
        $upload = new Upload;
        $values = new ActionValues(
            ['file' => ['upload' => 'upl_1'], 'note' => 'Hi'],
            fn (mixed $value): ?Upload => $value === ['upload' => 'upl_1'] ? $upload : null,
        );

        $this->assertSame($upload, $values->upload('file'));
        $this->assertNull($values->upload('note'));
        $this->assertNull((new ActionValues(['file' => ['upload' => 'upl_1']]))->upload('file'));
    }
}

class SendNewsletterAction extends Action
{
    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        return $result;
    }
}
