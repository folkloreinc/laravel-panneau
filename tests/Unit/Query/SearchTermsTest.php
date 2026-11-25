<?php

namespace Panneau\Tests\Unit\Query;

use Panneau\Query\SearchTerms;
use PHPUnit\Framework\TestCase;

class SearchTermsTest extends TestCase
{
    public function test_words_are_split_on_whitespace(): void
    {
        $this->assertSame(['black', 'dog'], SearchTerms::words('black dog'));
        $this->assertSame(['black', 'dog'], SearchTerms::words("  black \t\n dog\u{a0}\u{2003}"));
        $this->assertSame(['l\'été', 'd-o-g', '100%'], SearchTerms::words('l\'été d-o-g 100%'));
        $this->assertSame([], SearchTerms::words(''));
        $this->assertSame([], SearchTerms::words("  \u{a0} "));
    }

    public function test_words_are_unique_and_bounded(): void
    {
        $this->assertSame(['a', 'b', 'A'], SearchTerms::words('a b a A b'));
        $this->assertSame(['1', '2', '3', '4', '5', '6', '7', '8'], SearchTerms::words('1 2 3 4 5 6 7 8 9 10'));
        $this->assertSame(['1', '2', '3', '4', '5', '6', '7', '8'], SearchTerms::words('1 1 2 2 3 4 5 6 7 8 9'));
    }

    public function test_words_of_a_string_that_is_not_utf8(): void
    {
        $this->assertSame(["caf\xe9", 'noir'], SearchTerms::words("caf\xe9 noir"));
    }

    public function test_letters_are_single_accented_letters_in_both_cases(): void
    {
        $letters = SearchTerms::letters();

        $this->assertSame('e', $letters['é']);
        $this->assertSame('e', $letters['É']);
        $this->assertSame('c', $letters['Ç']);
        $this->assertSame('y', $letters['Ÿ']);
        foreach (['ø', 'ł', 'đ', 'ß', 'œ', 'æ', 'e', 'E'] as $letter) {
            $this->assertArrayNotHasKey($letter, $letters);
        }
        foreach ($letters as $letter => $base) {
            $this->assertSame(1, mb_strlen((string) $letter));
            $this->assertMatchesRegularExpression('/^[a-z]$/', $base);
            // The SQL of a search has them between quotes
            $this->assertDoesNotMatchRegularExpression('/[\'"\\\\?%_!]/', (string) $letter);
        }
    }

    public function test_a_word_loses_its_accents_and_keeps_its_case(): void
    {
        $this->assertSame('eTUDE', SearchTerms::withoutAccents('ÉTUDE'));
        $this->assertSame('Francais', SearchTerms::withoutAccents('Français'));
        $this->assertSame('cœur', SearchTerms::withoutAccents('cœur'));
        $this->assertSame('100%', SearchTerms::withoutAccents('100%'));
    }
}
