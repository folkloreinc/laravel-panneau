<?php

namespace Panneau\Media;

/**
 * The types of media, as the protocol names them (`MediaInfo.type`, `filter[type]`), and how
 * MIME types map to them.
 */
final class MediaTypes
{
    public const IMAGE = 'image';

    public const VIDEO = 'video';

    public const AUDIO = 'audio';

    public const DOCUMENT = 'document';

    public const FONT = 'font';

    public const OTHER = 'other';

    /**
     * The types a library can hold, in the order of the `type` filter.
     */
    public const ALL = [self::IMAGE, self::VIDEO, self::AUDIO, self::DOCUMENT, self::FONT, self::OTHER];

    /**
     * MIME types of documents, besides `text/*`.
     */
    private const DOCUMENTS = [
        'application/pdf',
        'application/rtf',
        'application/msword',
        'application/vnd.ms-excel',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
    ];

    /**
     * MIME types of fonts, besides `font/*`.
     */
    private const FONTS = [
        'application/font-woff',
        'application/font-sfnt',
        'application/x-font-ttf',
        'application/x-font-otf',
        'application/x-font-woff',
        'application/vnd.ms-fontobject',
        'application/vnd.ms-opentype',
    ];

    /**
     * The type of a MIME type: `image/png` is an `image`, `application/pdf` a `document`.
     * Anything else, and an unknown MIME type, is `other`.
     */
    public static function fromMime(?string $mime): string
    {
        $mime = $mime !== null ? strtolower(trim(explode(';', $mime)[0])) : '';

        return match (true) {
            $mime === '' => self::OTHER,
            str_starts_with($mime, 'image/') => self::IMAGE,
            str_starts_with($mime, 'video/') => self::VIDEO,
            str_starts_with($mime, 'audio/') => self::AUDIO,
            str_starts_with($mime, 'font/'), in_array($mime, self::FONTS, true) => self::FONT,
            str_starts_with($mime, 'text/'), in_array($mime, self::DOCUMENTS, true) => self::DOCUMENT,
            default => self::OTHER,
        };
    }

    /**
     * The MIME types of a type, as `accept` patterns (`image/*`). Null when they can't be
     * listed: `other`, or a type this class doesn't know.
     *
     * @return list<string>|null
     */
    public static function mimePatterns(string $type): ?array
    {
        return match ($type) {
            self::IMAGE => ['image/*'],
            self::VIDEO => ['video/*'],
            self::AUDIO => ['audio/*'],
            self::FONT => ['font/*', ...self::FONTS],
            self::DOCUMENT => ['text/*', ...self::DOCUMENTS],
            default => null,
        };
    }

    /**
     * The `accept` patterns of several types, or null when one of them can't be listed.
     *
     * @param  list<string>  $types
     * @param  (callable(string): (list<string>|null))|null  $patterns  The patterns of a type.
     *                                                                  Defaults to `mimePatterns()`.
     * @return list<string>|null
     */
    public static function accept(array $types, ?callable $patterns = null): ?array
    {
        $accept = [];
        foreach ($types as $type) {
            $typePatterns = $patterns !== null ? $patterns($type) : self::mimePatterns($type);
            if ($typePatterns === null) {
                return null;
            }
            array_push($accept, ...$typePatterns);
        }

        return count($accept) > 0 ? array_values(array_unique($accept)) : null;
    }

    /**
     * A label for a type, in the `type` filter.
     */
    public static function label(string $type): string
    {
        return match ($type) {
            self::IMAGE => __('Images'),
            self::VIDEO => __('Videos'),
            self::AUDIO => __('Audio'),
            self::DOCUMENT => __('Documents'),
            self::FONT => __('Fonts'),
            self::OTHER => __('Other'),
            default => __(ucfirst($type)),
        };
    }
}
