<?php

namespace Panneau\Fields;

use Illuminate\Support\Str;

/**
 * The codes of the regions of Canada and the United States, from their names in English and in
 * French, in any case, with or without accents, and from their codes: `Québec`, `Quebec`, `qc`
 * and `Qué.` are all `QC`. For `Address` values and `panneau:convert-addresses`.
 *
 * @internal
 */
final class Regions
{
    /**
     * The codes of the regions, by country, with their names and other spellings.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const NAMES = [
        'CA' => [
            'AB' => ['Alberta', 'Alta'],
            'BC' => ['British Columbia', 'Colombie-Britannique', 'C.-B.'],
            'MB' => ['Manitoba', 'Man'],
            'NB' => ['New Brunswick', 'Nouveau-Brunswick', 'N.-B.'],
            'NL' => ['Newfoundland and Labrador', 'Terre-Neuve-et-Labrador', 'Newfoundland', 'Terre-Neuve', 'Nfld', 'T.-N.-L.'],
            'NS' => ['Nova Scotia', 'Nouvelle-Écosse', 'N.-É.'],
            'NT' => ['Northwest Territories', 'Territoires du Nord-Ouest', 'T.N.-O.'],
            'NU' => ['Nunavut'],
            'ON' => ['Ontario', 'Ont'],
            'PE' => ['Prince Edward Island', 'Île-du-Prince-Édouard', 'PEI', 'Î.-P.-É.'],
            'QC' => ['Quebec', 'Québec', 'Province de Québec', 'Province of Quebec', 'Qué', 'PQ'],
            'SK' => ['Saskatchewan', 'Sask'],
            'YT' => ['Yukon', 'Yukon Territory', 'Territoire du Yukon', 'YK'],
        ],
        'US' => [
            'AL' => ['Alabama'],
            'AK' => ['Alaska'],
            'AZ' => ['Arizona'],
            'AR' => ['Arkansas'],
            'CA' => ['California', 'Californie'],
            'CO' => ['Colorado'],
            'CT' => ['Connecticut'],
            'DE' => ['Delaware'],
            'DC' => ['District of Columbia', 'District de Columbia', 'Washington D.C.'],
            'FL' => ['Florida', 'Floride'],
            'GA' => ['Georgia', 'Géorgie'],
            'HI' => ['Hawaii', 'Hawaï'],
            'ID' => ['Idaho'],
            'IL' => ['Illinois'],
            'IN' => ['Indiana'],
            'IA' => ['Iowa'],
            'KS' => ['Kansas'],
            'KY' => ['Kentucky'],
            'LA' => ['Louisiana', 'Louisiane'],
            'ME' => ['Maine'],
            'MD' => ['Maryland'],
            'MA' => ['Massachusetts'],
            'MI' => ['Michigan'],
            'MN' => ['Minnesota'],
            'MS' => ['Mississippi'],
            'MO' => ['Missouri'],
            'MT' => ['Montana'],
            'NE' => ['Nebraska'],
            'NV' => ['Nevada'],
            'NH' => ['New Hampshire'],
            'NJ' => ['New Jersey'],
            'NM' => ['New Mexico', 'Nouveau-Mexique'],
            'NY' => ['New York', 'État de New York'],
            'NC' => ['North Carolina', 'Caroline du Nord'],
            'ND' => ['North Dakota', 'Dakota du Nord'],
            'OH' => ['Ohio'],
            'OK' => ['Oklahoma'],
            'OR' => ['Oregon'],
            'PA' => ['Pennsylvania', 'Pennsylvanie'],
            'RI' => ['Rhode Island'],
            'SC' => ['South Carolina', 'Caroline du Sud'],
            'SD' => ['South Dakota', 'Dakota du Sud'],
            'TN' => ['Tennessee'],
            'TX' => ['Texas'],
            'UT' => ['Utah'],
            'VT' => ['Vermont'],
            'VA' => ['Virginia', 'Virginie'],
            'WA' => ['Washington', 'État de Washington'],
            'WV' => ['West Virginia', 'Virginie-Occidentale'],
            'WI' => ['Wisconsin'],
            'WY' => ['Wyoming'],
            'AS' => ['American Samoa', 'Samoa américaines'],
            'GU' => ['Guam'],
            'MP' => ['Northern Mariana Islands', 'Îles Mariannes du Nord'],
            'PR' => ['Puerto Rico', 'Porto Rico'],
            'UM' => ['United States Minor Outlying Islands', 'Îles mineures éloignées des États-Unis'],
            'VI' => ['U.S. Virgin Islands', 'United States Virgin Islands', 'Îles Vierges des États-Unis'],
        ],
    ];

    /**
     * The regions of each country, by the key of their codes, names and other spellings.
     *
     * @var array<string, array<string, string>>|null
     */
    private static ?array $keys = null;

    /**
     * The countries whose regions are known here.
     *
     * @return list<string>
     */
    public static function countries(): array
    {
        return array_keys(self::NAMES);
    }

    /**
     * The code of a region of a country, from its code or one of its names. Null for a country
     * whose regions aren't known here, or a region it doesn't know.
     */
    public static function code(string $region, ?string $country): ?string
    {
        $keys = self::keys()[$country !== null ? strtoupper($country) : ''] ?? null;

        return $keys !== null ? ($keys[self::key($region)] ?? null) : null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function keys(): array
    {
        if (self::$keys === null) {
            $keys = [];
            foreach (self::NAMES as $country => $regions) {
                foreach ($regions as $code => $names) {
                    foreach ([$code, ...$names] as $name) {
                        $keys[$country][self::key($name)] = $code;
                    }
                }
            }
            self::$keys = $keys;
        }

        return self::$keys;
    }

    /**
     * A name in lower case letters only, without accents: `Île-du-Prince-Édouard` is
     * `ileduprinceedouard`, `Qué.` is `que`.
     */
    private static function key(string $name): string
    {
        return (string) preg_replace('/[^a-z]/', '', strtolower(Str::ascii($name)));
    }
}
