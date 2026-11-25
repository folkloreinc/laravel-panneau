<?php

namespace Panneau\Query;

/**
 * How a search is read: the words of `q`, and the accented letters a search ignores.
 *
 * @internal
 */
final class SearchTerms
{
    /**
     * Most words searched: the others are ignored, so a long search stays a short query.
     */
    public const MAX_WORDS = 8;

    /**
     * The accented Latin letters a search reads as their base letter, in lower case. Only
     * letters that are a base letter with a diacritic: "ø", "đ", "ł" or "ß" are letters of their
     * own, and ligatures ("œ", "æ") are two letters, which a letter can't stand for.
     */
    private const ACCENTS = [
        'a' => 'àáâãäåāăą',
        'c' => 'çćĉċč',
        'd' => 'ď',
        'e' => 'èéêëēĕėęě',
        'g' => 'ĝğġģ',
        'h' => 'ĥ',
        'i' => 'ìíîïĩīĭį',
        'j' => 'ĵ',
        'k' => 'ķ',
        'l' => 'ĺļľ',
        'n' => 'ñńņň',
        'o' => 'òóôõöōŏő',
        'r' => 'ŕŗř',
        's' => 'śŝşšș',
        't' => 'ţťț',
        'u' => 'ùúûüũūŭůűų',
        'w' => 'ŵ',
        'y' => 'ýÿŷ',
        'z' => 'źżž',
    ];

    /**
     * The words of a search, split on whitespace (Unicode spaces too, such as a no-break space),
     * without duplicates: at most `MAX_WORDS`.
     *
     * @return list<string>
     */
    public static function words(string $search): array
    {
        $words = preg_split('/[\s\p{Z}]+/u', $search, -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false) {
            // Not valid UTF-8
            $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return array_slice(array_values(array_unique($words)), 0, self::MAX_WORDS);
    }

    /**
     * Every accented letter, in both cases, with the unaccented lower-case letter it is read as.
     *
     * @return array<string, string>
     */
    public static function letters(): array
    {
        $letters = [];
        foreach (self::ACCENTS as $base => $accented) {
            foreach (mb_str_split($accented) as $letter) {
                $letters[$letter] = $base;
                $letters[mb_strtoupper($letter)] = $base;
            }
        }

        return $letters;
    }

    /**
     * A word without its accents, for the databases that are given the letters to ignore. The
     * case of unaccented letters is left to the database.
     */
    public static function withoutAccents(string $word): string
    {
        return strtr($word, self::letters());
    }
}
