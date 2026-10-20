<?php

namespace App\Services\ShareImages;

use App\Data\ShareCard;
use Closure;
use GdImage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Draws a 1200 by 630 share card as a PNG with GD, in the site's colours and fonts (TTF copies in resources/fonts,
 * since GD cannot read the woff2 the pages use). Everything is drawn at twice the size and scaled down, as GD's thick
 * lines are not antialiased. The words sit on the left with what the page covers as chips; on the right a map card,
 * like the site's own, draws the river from its centreline (with a small Britain below) or Britain (Natural Earth,
 * public domain), with the place pinned under a popup naming it.
 */
class ShareCardRenderer
{
    /** Bump when the drawing changes, so every card is redrawn on the next run. */
    public const VERSION = '2';

    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** Drawn at this multiple, then scaled down. */
    private const SCALE = 2;

    private const COLOURS = [
        'ground' => '#f6f3ec',
        'surface' => '#fffdf8',
        'white' => '#ffffff',
        'ink' => '#1b2a33',
        'ink2' => '#3a464f',
        'muted' => '#5b6670',
        'line' => '#ded8cb',
        'accent' => '#1f5f8b',
        'water' => '#e9f0f3',
        'riverHalo' => '#c3dae8',
        'land' => '#fffdf8',
        'landEdge' => '#c9c2b3',
        'otherLand' => '#f3efe6',
        'otherLandEdge' => '#ded8cb',
    ];

    /** The map card, in output pixels: left, top, right, bottom, and where the map gives way to its footer. */
    private const CARD = [676, 64, 1136, 566];

    private const MAP_BOTTOM = 456;

    private GdImage $image;

    /** @var list<array{0: int, 1: int}> The river as drawn, so the popup can keep off it. */
    private array $riverPixels = [];

    public function render(ShareCard $card): string
    {
        $this->image = imagecreatetruecolor(self::WIDTH * self::SCALE, self::HEIGHT * self::SCALE);
        imagefill($this->image, 0, 0, $this->colour('ground'));
        $this->riverPixels = [];

        $this->drawWaves();
        $this->drawText($card);
        $this->drawCard($card);

        $output = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled($output, $this->image, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, self::WIDTH * self::SCALE, self::HEIGHT * self::SCALE);

        ob_start();
        imagepng($output, null, 9);

        return (string) ob_get_clean();
    }

    /** Faint water lines across the bottom of the card, as on the site's home page. */
    private function drawWaves(): void
    {
        foreach ([470, 520, 570, 620] as $index => $y) {
            $points = [];
            for ($x = -20; $x <= self::WIDTH + 20; $x += 6) {
                $points[] = [$this->px($x), $this->px($y + 34 * sin(($x + $index * 40) / 190) - 18 * sin(($x + 60) / 83))];
            }
            $this->polyline($points, $this->colourAlpha('accent', 0.1), $this->px(1.6), false);
        }
    }

    private function drawText(ShareCard $card): void
    {
        $left = $this->px(72);
        $width = $this->px(560);

        $this->roundedRectangle($left, $this->px(68), $left + $this->px(56), $this->px(74), $this->px(3), $this->colour('accent'));
        $this->spacedText("HOW'S THE WATER?", 17, $left, $this->px(112), 'accent', 'InstrumentSans-SemiBold', 2.4);

        $y = $this->px(192);
        $this->spacedText(mb_strtoupper($card->eyebrow), 15, $left, $y, 'muted', 'InstrumentSans-SemiBold', 1.8);

        // The title shrinks to fit one line, then wraps onto two, then is cut short.
        $size = 72;
        while ($size > 50 && count($this->wrap($card->title, 'BricolageGrotesque-Bold', $size, $width)) > 1) {
            $size -= 4;
        }
        $lines = $this->wrap($card->title, 'BricolageGrotesque-Bold', $size, $width);
        if (count($lines) > 2) {
            $lines = [$lines[0], rtrim($lines[1], ',').'...'];
        }
        $y += $this->px($size + 18);
        foreach ($lines as $line) {
            $this->text($line, $size, $left - $this->px(3), $y, 'ink', 'BricolageGrotesque-Bold');
            $y += $this->px($size * 1.14);
        }

        $y += $this->px(8 - $size * 0.14);
        foreach (array_slice($this->wrap($card->subtitle, 'InstrumentSans-Regular', 26, $width), 0, 2) as $line) {
            $this->text($line, 26, $left, $y, 'ink2', 'InstrumentSans-Regular');
            $y += $this->px(38);
        }

        $this->drawChips($card->topics, $left, $y + $this->px(4), $width);
        $this->text('howsthewater.uk', 20, $left, $this->px(566), 'accent', 'InstrumentSans-SemiBold');
    }

    /**
     * What the page covers as pills, in the site's water blue, wrapping onto a second row at most.
     *
     * @param  list<string>  $topics
     */
    private function drawChips(array $topics, int $left, int $top, int $width): void
    {
        [$height, $padding, $gap, $size] = [$this->px(36), $this->px(15), $this->px(9), 15];
        [$x, $y, $row] = [$left, $top, 1];

        foreach ($topics as $topic) {
            $chipWidth = $this->textWidth($topic, 'InstrumentSans-SemiBold', $size) + 2 * $padding;
            if ($x > $left && $x + $chipWidth > $left + $width) {
                if (++$row > 2) {
                    return;
                }
                [$x, $y] = [$left, $y + $height + $gap];
            }
            $this->roundedRectangle($x, $y, $x + $chipWidth, $y + $height, intdiv($height, 2), $this->colour('riverHalo'));
            $this->roundedRectangle($x + $this->px(1.5), $y + $this->px(1.5), $x + $chipWidth - $this->px(1.5), $y + $height - $this->px(1.5), intdiv($height, 2) - $this->px(1.5), $this->colour('water'));
            $this->text($topic, $size, $x + $padding, $y + $this->px(24), 'accent', 'InstrumentSans-SemiBold');
            $x += $chipWidth + $gap;
        }
    }

    /**
     * The map card: a soft shadow, a bordered panel, the map in water blue with faint contours, and a footer saying
     * how often the data updates, with a small Britain on river cards.
     */
    private function drawCard(ShareCard $card): void
    {
        [$x1, $y1, $x2, $y2] = array_map(fn (int $value) => $this->px($value), self::CARD);
        $mapBottom = $this->px(self::MAP_BOTTOM);
        $radius = $this->px(18);

        $this->shadow($x1, $y1, $x2, $y2, $radius, 10, 0.22);
        $this->roundedRectangle($x1, $y1, $x2, $y2, $radius, $this->colour('line'));
        $inset = $this->px(1.5);
        $this->roundedRectangle($x1 + $inset, $y1 + $inset, $x2 - $inset, $y2 - $inset, $radius - $inset, $this->colour('surface'));

        // The map fills the top, square along its bottom edge, with a line between it and the footer.
        $this->roundedRectangle($x1 + $inset, $y1 + $inset, $x2 - $inset, $mapBottom, $radius - $inset, $this->colour('water'));
        imagefilledrectangle($this->image, $x1 + $inset, $mapBottom - $radius, $x2 - $inset, $mapBottom, $this->colour('water'));
        imagefilledrectangle($this->image, $x1 + $inset, $mapBottom, $x2 - $inset, $mapBottom + $inset, $this->colour('line'));

        imagesetclip($this->image, $x1 + $inset, $y1 + $inset, $x2 - $inset, $mapBottom - 1);
        for ($y = $y1 + $this->px(30); $y < $mapBottom; $y += $this->px(46)) {
            $points = [];
            for ($x = $x1 - $this->px(20); $x <= $x2 + $this->px(20); $x += $this->px(6)) {
                $points[] = [$x, (int) round($y + $this->px(16) * sin(($x - $x1) / $this->px(70)) - $this->px(10) * sin(($x - $x1 + $y) / $this->px(41)))];
            }
            $this->polyline($points, $this->colourAlpha('accent', 0.07), $this->px(1.4), false);
        }

        $map = [$x1 + $inset, $y1 + $inset, $x2 - $inset, $mapBottom];
        $pinAt = $card->riverLine !== null && count($card->riverLine) > 1
            ? $this->drawRiver($card, ...$map)
            : $this->drawBritain($map[0] + $this->px(40), $map[1] + $this->px(24), $map[2] - $this->px(40), $map[3] - $this->px(24), $card->pin);
        if ($pinAt !== null) {
            $this->marker($pinAt[0], $pinAt[1], $card->pinIsOverflow);
            $this->popup($card->title, $pinAt[0], $pinAt[1], $this->riverPixels, ...$map);
        }
        imagesetclip($this->image, 0, 0, self::WIDTH * self::SCALE - 1, self::HEIGHT * self::SCALE - 1);

        $this->spacedText('UPDATED', 13, $x1 + $this->px(24), $mapBottom + $this->px(40), 'muted', 'InstrumentSans-SemiBold', 1.6);
        $this->text('Every 15 minutes', 30, $x1 + $this->px(23), $mapBottom + $this->px(82), 'ink', 'BricolageGrotesque-Bold');

        if ($card->riverLine !== null && count($card->riverLine) > 1) {
            $place = $card->pin ?? $card->riverLine[intdiv(count($card->riverLine), 2)];
            $this->drawBritain($x2 - $this->px(84), $mapBottom + $this->px(12), $x2 - $this->px(24), $y2 - $this->px(12), $place, small: true);
        }
    }

    /**
     * The river's line with its source and mouth labelled and its spots as dots. Returns where this card's place
     * sits, for its marker.
     *
     * @return array{0: int, 1: int}|null
     */
    private function drawRiver(ShareCard $card, int $x1, int $y1, int $x2, int $y2): ?array
    {
        /** @var list<array{0: float, 1: float}> $line */
        $line = $card->riverLine;
        $project = $this->projector($line, $x1, $y1, $x2, $y2, $this->px(48));
        $pixels = $this->simplify(array_map(fn (array $point) => $project($point[0], $point[1]), $line), $this->px(0.6));

        $this->polyline($pixels, $this->colour('accent'), $this->px(3.6));
        $this->riverPixels = $pixels;

        $this->endLabel('Source', $pixels, $x1, $x2);
        $this->endLabel('Mouth', array_reverse($pixels), $x1, $x2);

        foreach ($card->riverSpots as [$latitude, $longitude]) {
            [$x, $y] = $project($latitude, $longitude);
            imagefilledellipse($this->image, $x, $y, $this->px(19), $this->px(19), $this->colour('white'));
            imagefilledellipse($this->image, $x, $y, $this->px(14), $this->px(14), $this->colour('accent'));
        }

        return $card->pin === null ? null : $project(...$card->pin);
    }

    /**
     * Great Britain filling the box (Ireland drawn fainter beside it). Returns where the place sits, or on a small
     * Britain marks it with a dot.
     *
     * @param  array{0: float, 1: float}|null  $place
     * @return array{0: int, 1: int}|null
     */
    private function drawBritain(int $x1, int $y1, int $x2, int $y2, ?array $place, bool $small = false): ?array
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
                    imagesetthickness($this->image, max(1, (int) round($this->px($small ? 0.8 : 1.2))));
                    imagepolygon($this->image, $points, $this->colour($edge));
                }
            }
        }

        if ($place === null) {
            return null;
        }

        [$x, $y] = $project(...$place);
        if (! $small) {
            return [$x, $y];
        }

        imagefilledellipse($this->image, $x, $y, $this->px(8), $this->px(8), $this->colour('accent'));

        return null;
    }

    /** This card's place: a large dot with a white ring, or a square for an overflow, as the site's maps draw them. */
    private function marker(int $x, int $y, bool $square): void
    {
        if ($square) {
            $this->roundedRectangle($x - $this->px(13), $y - $this->px(13), $x + $this->px(13), $y + $this->px(13), $this->px(4), $this->colour('white'));
            $this->roundedRectangle($x - $this->px(9), $y - $this->px(9), $x + $this->px(9), $y + $this->px(9), $this->px(3), $this->colour('accent'));

            return;
        }

        imagefilledellipse($this->image, $x, $y, $this->px(32), $this->px(32), $this->colour('white'));
        imagefilledellipse($this->image, $x, $y, $this->px(23), $this->px(23), $this->colour('accent'));
    }

    /**
     * A popup like the site's maps open: the place's name and a link, pointing at the marker from below or above it,
     * whichever hides less of the river (or, on Britain, from below when the marker sits high).
     *
     * @param  list<array{0: int, 1: int}>  $river
     */
    private function popup(string $name, int $x, int $y, array $river, int $left, int $top, int $right, int $bottom): void
    {
        $name = Str::limit($name, 30);
        $link = "See what's happening \u{203A}";
        $width = max($this->textWidth($name, 'InstrumentSans-SemiBold', 17), $this->textWidth($link, 'InstrumentSans-SemiBold', 14)) + $this->px(34);
        $height = $this->px(70);
        $tip = $this->px(9);
        $boxLeft = (int) max($left + $this->px(14), min($right - $this->px(14) - $width, $x - $width / 2));
        $tops = ['below' => $y + $this->px(22), 'above' => $y - $this->px(22) - $height];
        $hidden = fn (int $boxTop) => count(array_filter($river, fn (array $point) => $point[0] >= $boxLeft && $point[0] <= $boxLeft + $width && $point[1] >= $boxTop - $tip && $point[1] <= $boxTop + $height + $tip));
        $fits = fn (int $boxTop) => $boxTop >= $top + $this->px(10) && $boxTop + $height <= $bottom - $this->px(10);
        $below = match (true) {
            ! $fits($tops['above']) => true,
            ! $fits($tops['below']) => false,
            $river !== [] => $hidden($tops['below']) <= $hidden($tops['above']),
            default => $y < ($top + $bottom) / 2,
        };
        $boxTop = $below ? $tops['below'] : $tops['above'];

        $this->shadow($boxLeft, $boxTop, $boxLeft + $width, $boxTop + $height, $this->px(12), 4, 0.28);
        $this->roundedRectangle($boxLeft, $boxTop, $boxLeft + $width, $boxTop + $height, $this->px(12), $this->colour('white'));
        $tipY = $below ? $boxTop : $boxTop + $height;
        $pointY = $below ? $boxTop - $tip : $boxTop + $height + $tip;
        imagefilledpolygon($this->image, [$x - $tip, $tipY, $x + $tip, $tipY, $x, $pointY], $this->colour('white'));

        $this->text($name, 17, $boxLeft + $this->px(17), $boxTop + $this->px(29), 'ink', 'InstrumentSans-SemiBold');
        $this->text($link, 14, $boxLeft + $this->px(17), $boxTop + $this->px(53), 'accent', 'InstrumentSans-SemiBold');
    }

    /**
     * A soft shadow under a rounded box. GD's blur reaches only a pixel or two and ignores transparency, so the box is
     * drawn black on white at an eighth of the size, blurred there, turned into faint ink whose opacity follows the
     * blur, then scaled up and laid down a little below the box.
     */
    private function shadow(int $x1, int $y1, int $x2, int $y2, int $radius, int $blur, float $opacity): void
    {
        $shrink = 8;
        $margin = $this->px($blur * 3);
        [$width, $height] = [$x2 - $x1 + 2 * $margin, $y2 - $y1 + 2 * $margin];
        [$smallWidth, $smallHeight] = [intdiv($width, $shrink), intdiv($height, $shrink)];

        $mask = imagecreatetruecolor($smallWidth, $smallHeight);
        imagefill($mask, 0, 0, (int) imagecolorallocate($mask, 255, 255, 255));
        $previous = $this->image;
        $this->image = $mask;
        $this->roundedRectangle(intdiv($margin, $shrink), intdiv($margin, $shrink), intdiv($margin + $x2 - $x1, $shrink), intdiv($margin + $y2 - $y1, $shrink), intdiv($radius, $shrink), (int) imagecolorallocate($mask, 0, 0, 0));
        $this->image = $previous;
        for ($pass = 0; $pass < $blur; $pass++) {
            imagefilter($mask, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $small = imagecreatetruecolor($smallWidth, $smallHeight);
        imagealphablending($small, false);
        for ($x = 0; $x < $smallWidth; $x++) {
            for ($y = 0; $y < $smallHeight; $y++) {
                $darkness = 1 - (imagecolorat($mask, $x, $y) & 0xFF) / 255;
                imagesetpixel($small, $x, $y, (int) imagecolorallocatealpha($small, 27, 42, 51, 127 - (int) round(127 * $opacity * $darkness)));
            }
        }

        $large = imagecreatetruecolor($width, $height);
        imagealphablending($large, false);
        imagesavealpha($large, true);
        imagecopyresampled($large, $small, 0, 0, 0, 0, $width, $height, $smallWidth, $smallHeight);
        imagecopy($this->image, $large, $x1 - $margin, $y1 - $margin + $this->px($blur * 1.2), 0, 0, $width, $height);
    }

    /**
     * Labels the first point of the line, set off it in the direction the line comes from, so the label never sits on
     * the river however it bends.
     *
     * @param  list<array{0: int, 1: int}>  $pixels
     */
    private function endLabel(string $text, array $pixels, int $left, int $right): void
    {
        [$x, $y] = $pixels[0];
        // A point a little way along, to tell which way the line leaves its end.
        $along = $pixels[count($pixels) - 1];
        foreach ($pixels as $point) {
            if (hypot($point[0] - $x, $point[1] - $y) > $this->px(40)) {
                $along = $point;
                break;
            }
        }
        $length = hypot($x - $along[0], $y - $along[1]) ?: 1.0;
        $centreX = $x + ($x - $along[0]) / $length * $this->px(30);
        $centreY = $y + ($y - $along[1]) / $length * $this->px(22);

        // Near the map's edge the label would be pushed back onto the line, so it goes above or below the end instead.
        $halfWidth = $this->textWidth($text, 'InstrumentSans-SemiBold', 14) / 2;
        if ($centreX - $halfWidth < $left + $this->px(18) || $centreX + $halfWidth > $right - $this->px(18)) {
            [$centreX, $centreY] = [$x, $y + $this->px($along[1] < $y ? 24 : -22)];
        }

        $this->label($text, (int) round($centreX), (int) round($centreY + $this->px(5)), $left, $right);
    }

    /** A small label centred on a point, kept inside the map. */
    private function label(string $text, int $x, int $y, int $left, int $right): void
    {
        $width = $this->textWidth($text, 'InstrumentSans-SemiBold', 14);
        $this->text($text, 14, (int) max($left + $this->px(18), min($right - $this->px(18) - $width, $x - $width / 2)), $y, 'muted', 'InstrumentSans-SemiBold');
    }

    /**
     * A line through pixel points, with round joins unless $joins is false (faint lines, where overlapping joins
     * would show darker).
     *
     * @param  list<array{0: int, 1: int}>  $points
     */
    private function polyline(array $points, int $colour, int $thickness, bool $joins = true): void
    {
        imagesetthickness($this->image, $thickness);
        foreach ($points as $index => [$x, $y]) {
            if ($index > 0) {
                imageline($this->image, $points[$index - 1][0], $points[$index - 1][1], $x, $y, $colour);
            }
            if ($joins) {
                imagefilledellipse($this->image, $x, $y, $thickness, $thickness, $colour);
            }
        }
    }

    private function roundedRectangle(int $x1, int $y1, int $x2, int $y2, int $radius, int $colour): void
    {
        $radius = max(0, min($radius, intdiv($x2 - $x1, 2), intdiv($y2 - $y1, 2)));
        imagefilledrectangle($this->image, $x1 + $radius, $y1, $x2 - $radius, $y2, $colour);
        imagefilledrectangle($this->image, $x1, $y1 + $radius, $x1 + $radius - 1, $y2 - $radius, $colour);
        imagefilledrectangle($this->image, $x2 - $radius + 1, $y1 + $radius, $x2, $y2 - $radius, $colour);
        foreach ([[$x1 + $radius, $y1 + $radius, 180, 270], [$x2 - $radius, $y1 + $radius, 270, 360], [$x1 + $radius, $y2 - $radius, 90, 180], [$x2 - $radius, $y2 - $radius, 0, 90]] as [$x, $y, $from, $to]) {
            imagefilledarc($this->image, $x, $y, $radius * 2, $radius * 2, $from, $to, $colour, IMG_ARC_PIE);
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

    /** Text with extra space between letters, in output pixels, for small capitals. */
    private function spacedText(string $text, float $size, int $x, int $y, string $colour, string $font, float $spacing): void
    {
        foreach (mb_str_split($text) as $character) {
            $this->text($character, $size, $x, $y, $colour, $font);
            $x += $this->textWidth($character, $font, $size) + $this->px($spacing);
        }
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

    /** A colour at an opacity from 0 to 1, blended over what is already drawn. */
    private function colourAlpha(string $name, float $opacity): int
    {
        [$red, $green, $blue] = sscanf(self::COLOURS[$name], '#%02x%02x%02x');

        return (int) imagecolorallocatealpha($this->image, (int) $red, (int) $green, (int) $blue, 127 - (int) round(127 * $opacity));
    }

    /** Output pixels to drawing pixels. */
    private function px(float $pixels): int
    {
        return (int) round($pixels * self::SCALE);
    }
}
