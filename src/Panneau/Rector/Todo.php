<?php

namespace Panneau\Rector;

use PhpParser\Comment;
use PhpParser\Node;

/**
 * The `// TODO(panneau-5): …` comments that the rules leave where they can't convert, and that
 * `panneau:install --upgrade --check` lists.
 *
 * @internal
 */
final class Todo
{
    public const MARKER = 'TODO(panneau-5)';

    /**
     * Adds comments above a node, once.
     *
     * @param  list<string>  $todos
     */
    public static function add(Node $node, array $todos): bool
    {
        $comments = $node->getComments();
        $added = false;
        foreach (array_unique($todos) as $todo) {
            $text = '// '.self::MARKER.': '.$todo;
            foreach ($comments as $comment) {
                if ($comment->getText() === $text) {
                    continue 2;
                }
            }
            $comments[] = new Comment($text);
            $added = true;
        }
        if ($added) {
            $node->setAttribute('comments', $comments);
        }

        return $added;
    }

    public static function has(Node $node): bool
    {
        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), self::MARKER)) {
                return true;
            }
        }

        return false;
    }
}
