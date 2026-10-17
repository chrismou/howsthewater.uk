<?php

namespace App\Services\Geo;

/**
 * Tidies the site names water companies give their overflows, which the annual returns and Scottish Water's feed print
 * in capitals ("ELLERTON CSO", "CROSSHOUSE, 9 KILMAURS ROAD CSO"): title case, keeping the asset acronyms. A name
 * already in mixed case ("Tintern Main Road Storm Overflow") is left as given.
 */
final class OverflowSiteName
{
    /**
     * Asset types written as acronyms: combined, sewer and storm overflows, pumping stations, treatment works and
     * recycling centres, emergency and tank overflows.
     */
    private const ACRONYMS = [
        'CSO', 'SSO', 'SO', 'EO', 'CEO', 'SPS', 'TPS', 'PS', 'STW', 'WTW', 'WRC', 'WRP', 'STC', 'SST', 'CSOS', 'UK',
    ];

    public static function tidy(?string $raw): ?string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $raw));

        if ($name === '' || $name === '#N/A') {
            return null;
        }

        if ($name !== mb_strtoupper($name)) {
            return $name;
        }

        $titled = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
        // Title case capitalises after an apostrophe ("St John'S").
        $titled = (string) preg_replace_callback("/'(\\p{Lu})\\b/u", fn (array $match) => "'".mb_strtolower($match[1]), $titled);

        return (string) preg_replace_callback('/\b(\p{L}+)\b/u', function (array $match) {
            $upper = mb_strtoupper($match[1]);

            return match (true) {
                $upper === 'WWTW' => 'WwTW',
                in_array($upper, self::ACRONYMS, true) => $upper,
                default => $match[1],
            };
        }, $titled);
    }
}
