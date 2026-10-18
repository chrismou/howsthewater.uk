<?php

namespace App\Services\Geo;

/**
 * Normalises the receiving watercourse names used by water company feeds,
 * which carry qualifiers like "(Culverted)" or "Trib Of River Aire". Anglian
 * Water writes them many ways: "THE RIVER WENSUM", "Tidal River Great Ouse",
 * "Trib River Nene", "Unknown trib. River Nene", "River Wid NT" (non-tidal).
 * South West Water shortens them: "TRIB O RIV.TEIGN", "R. MOLE", "R CULM".
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
        $name = trim((string) preg_replace('/\s*\([^)]*\)/', '', $name));
        $name = trim((string) preg_replace('/\s+via(?:\s|sws).*$/', '', $name));
        // "NT" is Anglian Water's "non-tidal"; "the" and "tidal" never change which river it is.
        $name = trim((string) preg_replace(['/^the\s+/', '/^tidal\s+/', '/\s+nt$/'], '', $name));

        // "Trib of River X", or Anglian Water's "Trib River X" and "Unknown trib. River X" without the "of".
        $isTributary = (bool) preg_match('/^(?:a\s+)?(?:culverted\s+)?(?:unnamed\s+|unknown\s+)?trib(?:utary)?\.?\s+(?:(?:of|to|o)\s+)?(?:the\s+)?(.+)$/', $name, $matches);

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
