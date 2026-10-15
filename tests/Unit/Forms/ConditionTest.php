<?php

namespace Panneau\Tests\Unit\Forms;

use Panneau\Forms\Condition;
use PHPUnit\Framework\TestCase;

class ConditionTest extends TestCase
{
    public function test_comparisons(): void
    {
        $values = ['type' => 'link', 'count' => 2, 'tags' => [], 'seo' => ['title' => 'T']];

        $this->assertTrue(Condition::field('type')->equals('link')->evaluate($values));
        $this->assertFalse(Condition::field('type')->equals('text')->evaluate($values));
        $this->assertTrue(Condition::field('count')->in([1, 2.0])->evaluate($values));
        $this->assertFalse(Condition::field('count')->notIn([2])->evaluate($values));
        $this->assertTrue(Condition::field('tags')->filled(false)->evaluate($values));
        $this->assertTrue(Condition::field('seo.title')->filled()->evaluate($values));
        $this->assertFalse(Condition::field('missing')->filled()->evaluate($values));
        $this->assertTrue(Condition::field('missing')->equals(null)->evaluate($values));
    }

    public function test_combinations(): void
    {
        $values = ['type' => 'link', 'count' => 2];
        $isLink = Condition::field('type')->equals('link');
        $isText = Condition::field('type')->equals('text');

        $this->assertTrue(Condition::all([$isLink, Condition::field('count')->in([2])])->evaluate($values));
        $this->assertFalse(Condition::all([$isLink, $isText])->evaluate($values));
        $this->assertTrue(Condition::any([$isText, $isLink])->evaluate($values));
        $this->assertFalse(Condition::any([$isText])->evaluate($values));
        $this->assertTrue(Condition::all([])->evaluate($values));
    }

    public function test_array_form(): void
    {
        $condition = ['any' => [['field' => 'type', 'equals' => 'link'], ['field' => 'count', 'notIn' => [2], 'filled' => true]]];

        $this->assertSame($condition, Condition::fromArray($condition)->toArray());
        $this->assertTrue(Condition::fromArray($condition)->evaluate(['type' => 'link']));
        $this->assertFalse(Condition::fromArray($condition)->evaluate(['type' => 'text', 'count' => 2]));
    }
}
