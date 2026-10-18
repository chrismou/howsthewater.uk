<?php

namespace App\Services\Geo;

/**
 * Normalises the receiving watercourse names used by water company feeds,
 * which carry qualifiers like "(Culverted)" or "Trib Of River Aire". Anglian
 * Water writes them many ways: "THE RIVER WENSUM", "Tidal River Great Ouse",
 * "Trib River Nene", "Unknown trib. River Nene", "River Wid NT" (non-tidal).
 * South West Water shortens them: "TRIB O RIV.TEIGN", "R. MOLE", "R CULM".
 * Wessex Water adds square brackets, "SWS" (surface water sewer) and
 * brackets it never closes: "RIVER AVON [BLACK ROCKS] SWS", "Bristol Avon (By Bk".
 */
final readonly class WatercourseName
{
    private function __construct(
        public string $name,
        public bool $isUnnamedTributary,
    ) {
    }

    public static function parse(?string $raw): ?self
    {
        if ($raw === null) {
            return null;
        }

        $name = mb_strtolower(trim($raw));
        $name = trim((string) preg_replace(['/\s*\([^)]*\)/', '/\s*\[[^\]]*\]/', '/\s*[(\[][^)\]]*$/'], '', $name));
        $name = trim((string) preg_replace('/\s+via(?:\s|sws).*$/', '', $name));
        // "NT" is Anglian Water's "non-tidal" and "SWS" Wessex Water's surface water sewer; "the" and "tidal" never change which river it is.
        $name = trim((string) preg_replace(['/^the\s+/', '/^tidal\s+/', '/\s+nt$/', '/\s+sws$/'], '', $name));

        // "Trib of River X", or Anglian Water's "Trib River X" and "Unknown trib. River X" without the "of", or Southern
        // Water's "An un-named tributary of the River X" and "Unamed tributary - River X".
        $isTributary = (bool) preg_match('/^(?:an?\s+)?(?:culverted\s+)?(?:un-?n?amed\s+|unknown\s+)?trib(?:utary)?\.?\s+(?:-\s+)?(?:(?:of|to|o)\s+)?(?:the\s+)?(.+)$/', $name, $matches);

        if ($isTributary) {
            $name = trim($matches[1]);
        }

        $name = (string) preg_replace('/^(?:r\.|riv\.|r\s)\s*/', 'river ', $name);

        $name = trim((string) preg_replace('/\s+/', ' ', $name));

        return $name === '' || $name === '#n/a' ? null : new self($name, $isTributary);
    }

    public function matches(string $alias): bool
    {
        return $this->name === mb_strtolower(trim($alias));
    }
}
