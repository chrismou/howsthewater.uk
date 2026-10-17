<?php

namespace App\Services\ShareImages;

use App\Data\ShareCard;
use Closure;
use GdImage;
use RuntimeException;

/**
 * Draws a 1200 by 630 share card as a PNG with GD, in the site's colours and fonts (TTF copies in resources/fonts,
 * since GD cannot read the woff2 the pages use). Everything is drawn at twice the size and scaled down, as GD's thick
 * lines are not antialiased. Spot and river cards draw the river from its centreline with a small Britain in the
 * emptiest corner; beach and lake cards draw Britain (Natural Earth, public domain) with a pin.
 */
class ShareCardRenderer
{
    /** Bump when the drawing changes, so every card is redrawn on the next run. */
    public const VERSION = '1';

    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** Drawn at this multiple, then scaled down. */
    private const SCALE = 2;

    private const COLOURS = [
        'ground' => '#f6f3ec',
        'surface' => '#fffdf8',
        'ink' => '#1b2a33',
        'ink2' => '#3a464f',
        'muted' => '#5b6670',
        'accent' => '#1f5f8b',
        'accentLight' => '#7fb6dd',
        'water' => '#e9f0f3',
        'riverHalo' => '#c3dae8',
        'land' => '#fffdf8',
        'landEdge' => '#c9c2b3',
        'otherLand' => '#f3efe6',
        'otherLandEdge' => '#ded8cb',
    ];

    private GdImage $image;

    public function render(ShareCard $card): string
    {
        $this->image = imagecreatetruecolor(self::WIDTH * self::SCALE, self::HEIGHT * self::SCALE);
        imagefill($this->image, 0, 0, $this->colour('ground'));

        $this->drawText($card);

        $panel = [$this->px(748), $this->px(72), $this->px(1128), $this->px(558)];
        $this->roundedRectangle(...[...$panel, $this->px(28), $this->colour('water')]);

        if ($card->riverLine !== null && count($card->riverLine) > 1) {
            $this->drawRiver($card, ...$panel);
        } else {
            $this->drawBritain($panel[0] + $this->px(70), $panel[1] + $this->px(36), $panel[2] - $this->px(70), $panel[3] - $this->px(36), $card->pin, $this->px(24), true);
        }

        $output = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled($output, $this->image, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, self::WIDTH * self::SCALE, self::HEIGHT * self::SCALE);

        ob_start();
        imagepng($output, null, 9);

        return (string) ob_get_clean();
    }

    private function drawText(ShareCard $card): void
    {
        $left = $this->px(80);
        $width = $this->px(600);

        $this->drawLogo($left, $this->px(72), $this->px(56));
        $this->text("HOW'S THE WATER?", 17, $left + $this->px(72), $this->px(108), 'accent', 'InstrumentSans-SemiBold');

        $y = $this->px(210);
        $this->text(mb_strtoupper($card->eyebrow), 16, $left, $y, 'muted', 'InstrumentSans-SemiBold');

        // The title shrinks to fit one line, then wraps onto two, then is cut short.
        $size = 76;
        while ($size > 52 && count($this->wrap($card->title, 'BricolageGrotesque-Bold', $size, $width)) > 1) {
            $size -= 4;
        }
        $lines = $this->wrap($card->title, 'BricolageGrotesque-Bold', $size, $width);
        if (count($lines) > 2) {
            $lines = [$lines[0], rtrim($lines[1], ',').'...'];
        }
        $y += $this->px($size + 26);
        foreach ($lines as $line) {
            $this->text($line, $size, $left - $this->px(4), $y, 'ink', 'BricolageGrotesque-Bold');
            $y += $this->px($size * 1.12);
        }

        $y += $this->px(6 - $size * 0.12);
        foreach (array_slice($this->wrap($card->subtitle, 'InstrumentSans-SemiBold', 28, $width), 0, 2) as $line) {
            $this->text($line, 28, $left, $y, 'ink2', 'InstrumentSans-SemiBold');
            $y += $this->px(40);
        }

        imagefilledrectangle($this->image, $left, $this->px(500), $left + $this->px(56), $this->px(505), $this->colour('accent'));
        $this->text($card->tagline, 20, $left, $this->px(548), 'muted', 'InstrumentSans-Regular');
    }

    /**
     * The river's line, source and mouth labelled, its spots as rings and this card's spot pinned, with a small Britain
     * in whichever corner of the panel is furthest from the river.
     */
    private function drawRiver(ShareCard $card, int $x1, int $y1, int $x2, int $y2): void
    {
        /** @var list<array{0: float, 1: float}> $line */
        $line = $card->riverLine;
        $project = $this->projector($line, $x1, $y1, $x2, $y2, $this->px(60));
        $pixels = $this->simplify(array_map(fn (array $point) => $project($point[0], $point[1]), $line), $this->px(0.6));

        $this->polyline($pixels, 'riverHalo', $this->px(15));
        $this->polyline($pixels, 'accent', $this->px(5));

        [$sourceX, $sourceY] = $pixels[0];
        [$mouthX, $mouthY] = $pixels[count($pixels) - 1];
        $this->label('Source', $sourceX, $sourceY + $this->px($sourceY > $mouthY ? 36 : -20), $x1, $x2);
        $this->label('Mouth', $mouthX, $mouthY + $this->px($mouthY > $sourceY ? 36 : -20), $x1, $x2);

        foreach ($card->riverSpots as [$latitude, $longitude]) {
            [$x, $y] = $project($latitude, $longitude);
            imagefilledellipse($this->image, $x, $y, $this->px(16), $this->px(16), $this->colour('accent'));
            imagefilledellipse($this->image, $x, $y, $this->px(8), $this->px(8), $this->colour('surface'));
        }

        if ($card->pin !== null) {
            [$x, $y] = $project(...$card->pin);
            $this->pin($x, $y, $this->px(28));
        }

        [$width, $height, $margin] = [$this->px(86), $this->px(140), $this->px(22)];
        $corners = [
            [$x1 + $margin, $y1 + $margin],
            [$x2 - $margin - $width, $y1 + $margin],
            [$x1 + $margin, $y2 - $margin - $height],
            [$x2 - $margin - $width, $y2 - $margin - $height],
        ];
        // How far the river comes from each corner box, as the larger of its horizontal and vertical gaps.
        $space = fn (array $corner) => min(array_map(fn (array $point) => max(
            abs($point[0] - ($corner[0] + $width / 2)) - $width / 2,
            abs($point[1] - ($corner[1] + $height / 2)) - $height / 2,
        ), $pixels));
        usort($corners, fn (array $a, array $b) => $space($b) <=> $space($a));
        [$insetX, $insetY] = $corners[0];

        $this->drawBritain($insetX, $insetY, $insetX + $width, $insetY + $height, $card->pin ?? $line[intdiv(count($line), 2)], $this->px(10), false);
    }

    /**
     * Great Britain filling the box (Ireland drawn fainter beside it), with the place, if known, as a dot or a pin.
     *
     * @param  array{0: float, 1: float}|null  $place
     */
    private function drawBritain(int $x1, int $y1, int $x2, int $y2, ?array $place, int $markerSize, bool $asPin): void
    {
        /** @var array{britain: list<list<array{0: float, 1: float}>>, ireland: list<list<array{0: float, 1: float}>>} $outlines */
        $outlines = json_decode((string) file_get_contents(resource_path('geo/britain-and-ireland.json')), true);
        $project = $this->projector(array_merge(...$outlines['britain']), $x1, $y1, $x2, $y2, 0);

        foreach ([[$outlines['ireland'], 'otherLand', 'otherLandEdge'], [$outlines['britain'], 'land', 'landEdge']] as [$rings, $fill, $edge]) {
            foreach ($rings as $ring) {
                // GD takes polygons as one flat list of x, y, x, y...
                $points = [];
                foreach ($ring as [$latitude, $longitude]) {
                    array_push($points, ...$project($latitude, $longitude));
                }
                if (count($points) >= 6) {
                    imagefilledpolygon($this->image, $points, $this->colour($fill));
                    imagesetthickness($this->image, max(1, (int) round($this->px(1.2))));
                    imagepolygon($this->image, $points, $this->colour($edge));
                }
            }
        }

        if ($place === null) {
            return;
        }

        [$x, $y] = $project(...$place);
        if ($asPin) {
            $this->pin($x, $y, $markerSize);
        } else {
            imagefilledellipse($this->image, $x, $y, $markerSize, $markerSize, $this->colour('accent'));
        }
    }

    /** The site icon: a dark rounded square with two waves and a question mark. */
    private function drawLogo(int $x, int $y, int $size): void
    {
        $unit = $size / 140;
        $this->roundedRectangle($x, $y, $x + $size, $y + $size, (int) round(30 * $unit), $this->colour('ink'));
        $this->wave($x + (int) round(18 * $unit), $y + (int) round(84 * $unit), (int) round(117 * $unit), 'accentLight', (int) round(8 * $unit));
        $this->wave($x + (int) round(18 * $unit), $y + (int) round(106 * $unit), (int) round(117 * $unit), 'accent', (int) round(8 * $unit));
        $questionSize = 52 * $unit / self::SCALE;
        $this->text('?', $questionSize, $x + (int) round($size / 2 - $this->textWidth('?', 'BricolageGrotesque-Bold', $questionSize) / 2), $y + (int) round(66 * $unit), 'surface', 'BricolageGrotesque-Bold');
    }

    private function wave(int $x, int $y, int $width, string $colour, int $thickness): void
    {
        $points = [];
        for ($step = 0; $step <= $width; $step += 2) {
            $points[] = [$x + $step, (int) round($y + sin($step / ($width / 3) * M_PI) * ($width / 9))];
        }
        $this->polyline($points, $colour, $thickness);
    }

    private function pin(int $x, int $y, int $size): void
    {
        imagefilledellipse($this->image, $x, $y, $size + $this->px(14), $size + $this->px(14), $this->colour('surface'));
        imagefilledellipse($this->image, $x, $y, $size, $size, $this->colour('accent'));
    }

    /** A small label centred on a point, kept inside the panel. */
    private function label(string $text, int $x, int $y, int $left, int $right): void
    {
        $width = $this->textWidth($text, 'InstrumentSans-SemiBold', 14);
        $this->text($text, 14, (int) max($left + $this->px(20), min($right - $this->px(20) - $width, $x - $width / 2)), $y, 'muted', 'InstrumentSans-SemiBold');
    }

    /**
     * A line through pixel points, with round joins.
     *
     * @param  list<array{0: int, 1: int}>  $points
     */
    private function polyline(array $points, string $colour, int $thickness): void
    {
        imagesetthickness($this->image, $thickness);
        foreach ($points as $index => [$x, $y]) {
            if ($index > 0) {
                imageline($this->image, $points[$index - 1][0], $points[$index - 1][1], $x, $y, $this->colour($colour));
            }
            imagefilledellipse($this->image, $x, $y, $thickness, $thickness, $this->colour($colour));
        }
    }

    private function roundedRectangle(int $x1, int $y1, int $x2, int $y2, int $radius, int $colour): void
    {
        imagefilledrectangle($this->image, $x1 + $radius, $y1, $x2 - $radius, $y2, $colour);
        imagefilledrectangle($this->image, $x1, $y1 + $radius, $x2, $y2 - $radius, $colour);
        foreach ([[$x1 + $radius, $y1 + $radius], [$x2 - $radius, $y1 + $radius], [$x1 + $radius, $y2 - $radius], [$x2 - $radius, $y2 - $radius]] as [$x, $y]) {
            imagefilledellipse($this->image, $x, $y, $radius * 2, $radius * 2, $colour);
        }
    }

    /**
     * Maps [lat, lng] into the box, keeping the shape (longitude scaled by the cosine of the middle latitude).
     *
     * @param  list<array{0: float, 1: float}>  $points
     * @return Closure(float, float): array{0: int, 1: int}
     */
    private function projector(array $points, int $x1, int $y1, int $x2, int $y2, int $padding): Closure
    {
        $cosine = cos(deg2rad(array_sum(array_column($points, 0)) / count($points)));
        $xs = array_map(fn (array $point) => $point[1] * $cosine, $points);
        $ys = array_map(fn (array $point) => -$point[0], $points);
        [$minX, $minY] = [min($xs), min($ys)];
        [$spanX, $spanY] = [max(max($xs) - $minX, 1e-6), max(max($ys) - $minY, 1e-6)];
        $scale = min(($x2 - $x1 - 2 * $padding) / $spanX, ($y2 - $y1 - 2 * $padding) / $spanY);
        $offsetX = $x1 + ($x2 - $x1 - $spanX * $scale) / 2;
        $offsetY = $y1 + ($y2 - $y1 - $spanY * $scale) / 2;

        return fn (float $latitude, float $longitude) => [
            (int) round($offsetX + ($longitude * $cosine - $minX) * $scale),
            (int) round($offsetY + (-$latitude - $minY) * $scale),
        ];
    }

    /**
     * Douglas-Peucker: drops points within $tolerance pixels of the line, so a river of thousands of points draws
     * smoothly.
     *
     * @param  list<array{0: int, 1: int}>  $points
     * @return list<array{0: int, 1: int}>
     */
    private function simplify(array $points, float $tolerance): array
    {
        if (count($points) < 3) {
            return $points;
        }

        [$ax, $ay] = $points[0];
        [$bx, $by] = $points[count($points) - 1];
        $length = hypot($bx - $ax, $by - $ay) ?: 1.0;
        [$furthest, $furthestIndex] = [0.0, 0];
        foreach ($points as $index => [$x, $y]) {
            $distance = abs(($bx - $ax) * ($ay - $y) - ($ax - $x) * ($by - $ay)) / $length;
            if ($distance > $furthest) {
                [$furthest, $furthestIndex] = [$distance, $index];
            }
        }

        if ($furthest <= $tolerance) {
            return [$points[0], $points[count($points) - 1]];
        }

        return [
            ...array_slice($this->simplify(array_slice($points, 0, $furthestIndex + 1), $tolerance), 0, -1),
            ...$this->simplify(array_slice($points, $furthestIndex), $tolerance),
        ];
    }

    /**
     * Words wrapped into lines no wider than $width.
     *
     * @return list<string>
     */
    private function wrap(string $text, string $font, float $size, int $width): array
    {
        $lines = [];
        $line = '';
        foreach (explode(' ', $text) as $word) {
            $candidate = trim($line.' '.$word);
            if ($line !== '' && $this->textWidth($candidate, $font, $size) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }

        return [...$lines, $line];
    }

    /** Text at a size in output points, drawn at the doubled scale. */
    private function text(string $text, float $size, int $x, int $y, string $colour, string $font): void
    {
        imagettftext($this->image, $size * self::SCALE, 0, $x, $y, $this->colour($colour), $this->font($font), $text);
    }

    private function textWidth(string $text, string $font, float $size): int
    {
        $box = imagettfbbox($size * self::SCALE, 0, $this->font($font), $text);

        if ($box === false) {
            throw new RuntimeException("Could not measure text in {$font}");
        }

        return $box[2] - $box[0];
    }

    private function font(string $name): string
    {
        return resource_path("fonts/{$name}.ttf");
    }

    private function colour(string $name): int
    {
        [$red, $green, $blue] = sscanf(self::COLOURS[$name], '#%02x%02x%02x');

        return (int) imagecolorallocate($this->image, (int) $red, (int) $green, (int) $blue);
    }

    /** Output pixels to drawing pixels. */
    private function px(float $pixels): int
    {
        return (int) round($pixels * self::SCALE);
    }
}
