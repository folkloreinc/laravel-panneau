<?php

namespace Panneau\Fields;

use Illuminate\Validation\Rule as LaravelRule;
use InvalidArgumentException;
use Panneau\Forms\ValidationContext;

/**
 * A country: its ISO 3166-1 alpha-2 code, in capitals (`CA`), or `XK` for Kosovo. The server
 * refuses any other code, and the codes outside `countries()` when the field has them.
 */
class Country extends Field
{
    /**
     * The ISO 3166-1 alpha-2 codes of the countries and territories, and `XK` (Kosovo), which is
     * widely used for it though not assigned.
     */
    public const CODES = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ',
        'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BV', 'BW', 'BY', 'BZ',
        'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ',
        'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ',
        'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET',
        'FI', 'FJ', 'FK', 'FM', 'FO', 'FR',
        'GA', 'GB', 'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY',
        'HK', 'HM', 'HN', 'HR', 'HT', 'HU',
        'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT',
        'JE', 'JM', 'JO', 'JP',
        'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ',
        'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY',
        'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ',
        'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ',
        'OM',
        'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY',
        'QA',
        'RE', 'RO', 'RS', 'RU', 'RW',
        'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SX', 'SY', 'SZ',
        'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ',
        'UA', 'UG', 'UM', 'US', 'UY', 'UZ',
        'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU',
        'WF', 'WS',
        'XK',
        'YE', 'YT',
        'ZA', 'ZM', 'ZW',
    ];

    public function type(): string
    {
        return 'country';
    }

    /**
     * The countries the field allows, as codes (`['CA', 'US']`): the server refuses the others.
     * All of them without it.
     *
     * @param  list<string>  $codes
     */
    public function countries(array $codes): static
    {
        return $this->settings(['countries' => self::codes($codes)]);
    }

    /**
     * The countries the field shows first, above the others, as codes (`['CA', 'US']`).
     *
     * @param  list<string>  $codes
     */
    public function preferred(array $codes): static
    {
        return $this->settings(['preferred' => self::codes($codes)]);
    }

    /**
     * Whether a value is an ISO 3166-1 alpha-2 code, in capitals, or `XK`.
     */
    public static function isCode(mixed $code): bool
    {
        return is_string($code) && in_array($code, self::CODES, true);
    }

    /**
     * Checks a list of codes given to a setter.
     *
     * @param  array<array-key, mixed>  $codes
     * @return list<string>
     *
     * @throws InvalidArgumentException When one isn't an ISO 3166-1 alpha-2 code in capitals.
     */
    public static function codes(array $codes): array
    {
        $checked = [];
        foreach ($codes as $code) {
            $checked[] = self::code($code);
        }

        return $checked;
    }

    /**
     * Checks a code given to a setter.
     *
     * @throws InvalidArgumentException When it isn't an ISO 3166-1 alpha-2 code in capitals.
     */
    public static function code(mixed $code): string
    {
        if (! is_string($code) || ! self::isCode($code)) {
            throw new InvalidArgumentException(sprintf('%s is not an ISO 3166-1 alpha-2 country code, in capitals.', var_export($code, true)));
        }

        return $code;
    }

    /**
     * The codes the field allows: those of `countries()`, or all of them.
     *
     * @return list<string>
     */
    public function allowed(): array
    {
        $countries = $this->settings['countries'] ?? null;

        return is_array($countries) ? array_values(array_filter($countries, self::isCode(...))) : self::CODES;
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['string', LaravelRule::in($this->allowed())];
    }
}
