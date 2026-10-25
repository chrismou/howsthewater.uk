<?php

namespace App\Data;

use App\Enums\FloodSeverity;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use RuntimeException;

final readonly class FloodWarningData
{
    /**
     * The EA publishes these times in UK local time without an offset, and
     * NRW's are read the same way unless they carry one.
     */
    private const SOURCE_TIMEZONE = 'Europe/London';

    public function __construct(
        public string $floodAreaCode,
        public ?FloodSeverity $severity,
        public ?string $message,
        public ?Carbon $raisedAt,
        public ?Carbon $messageChangedAt,
        public ?Carbon $severityChangedAt = null,
        public ?string $areaLabel = null,
        public ?string $riverOrSea = null,
        public ?string $county = null,
        public ?Carbon $publishedAt = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $item  A flood item from the EA flood monitoring API
     */
    public static function fromEnvironmentAgency(array $item): self
    {
        $message = trim((string) ($item['message'] ?? ''));

        return new self(
            floodAreaCode: (string) ($item['floodAreaID'] ?? $item['floodArea']['notation']),
            severity: FloodSeverity::tryFrom((int) ($item['severityLevel'] ?? 0)),
            message: $message === '' ? null : $message,
            raisedAt: self::time($item['timeRaised'] ?? null),
            messageChangedAt: self::time($item['timeMessageChanged'] ?? null),
            severityChangedAt: self::time($item['timeSeverityChanged'] ?? null),
            areaLabel: self::text($item['description'] ?? null),
            riverOrSea: self::text($item['floodArea']['riverOrSea'] ?? null),
            county: self::text($item['floodArea']['county'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $properties  A feature's properties from NRW's live flood warnings API
     */
    public static function fromNaturalResourcesWales(array $properties): self
    {
        $message = trim((string) ($properties['RIM_ENGLISH'] ?? ''));

        return new self(
            floodAreaCode: (string) $properties['FWACODE'],
            severity: FloodSeverity::tryFrom((int) ($properties['SEVERITYVALUE'] ?? 0)),
            message: $message === '' ? null : $message,
            raisedAt: self::time($properties['TIMERAISED'] ?? null),
            messageChangedAt: self::time($properties['RIM_CHANGED'] ?? null),
            severityChangedAt: self::time($properties['SEVERITY_CHANGED'] ?? null),
            areaLabel: self::text($properties['DESCRIPTION'] ?? null),
        );
    }

    /**
     * SEPA's severities are words ("Alert", "Warning", "NLIF" for no longer in force), and
     * its startTime is when the current message was issued, which Floodline shows as the
     * area's last update. updatedTime is when SEPA last rewrote the layer, so it says how
     * fresh the whole feed is rather than anything about the one warning.
     *
     * @param  array<string, mixed>  $attributes  A feature's attributes from one of the Floodline map's layers
     */
    public static function fromSepa(array $attributes, bool $isAlertArea): self
    {
        $label = self::text($attributes[$isAlertArea ? 'name' : 'fwa_name'] ?? null) ?? 'an unnamed area';
        $message = $attributes['message'] ?? null;

        if ($message !== null && ! is_string($message)) {
            throw new RuntimeException("SEPA flood warning for {$label} has a message that is not text");
        }

        $issuedAt = self::sepaTime($attributes['startTime'] ?? null, 'startTime', $label);

        return new self(
            floodAreaCode: FloodAreaData::sepaCode($attributes, $isAlertArea),
            severity: self::sepaSeverity($attributes['severity'] ?? null, $label),
            message: self::text($message),
            raisedAt: $issuedAt,
            messageChangedAt: $issuedAt,
            severityChangedAt: $issuedAt,
            areaLabel: $label,
            riverOrSea: $isAlertArea ? null : self::text($attributes['scheme'] ?? null),
            county: self::text($attributes['local_authority'] ?? null),
            publishedAt: self::sepaTime($attributes['updatedTime'] ?? null, 'updatedTime', $label),
        );
    }

    /**
     * Whether the warning is still in force; "no longer in force" items
     * linger in the feed for a day after a warning is lifted.
     */
    public function isInForce(): bool
    {
        return $this->severity !== null;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function time(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value, self::SOURCE_TIMEZONE)->utc() : null;
    }

    /**
     * A SEPA severity word as a level, or null for "NLIF", which SEPA writes once an
     * area is no longer in force. Only the words seen so far are accepted: anything
     * else (a number, a blank, a new wording for severe) stops the read, so a change
     * on SEPA's side is never shown as nothing or as a lifted warning.
     */
    private static function sepaSeverity(mixed $value, string $area): ?FloodSeverity
    {
        $word = is_string($value) ? strtolower(trim($value)) : null;

        return match ($word) {
            'nlif' => null,
            'alert' => FloodSeverity::FloodAlert,
            'warning' => FloodSeverity::FloodWarning,
            'severe', 'severe warning', 'severe flood warning' => FloodSeverity::SevereFloodWarning,
            default => throw new RuntimeException(sprintf(
                'SEPA flood warning for %s has an unrecognised severity %s',
                $area,
                is_scalar($value) ? '"'.$value.'"' : 'of type '.gettype($value),
            )),
        };
    }

    /**
     * SEPA's times are strings without an offset ("2026-10-25T12:27:25.113"; the layers
     * type them as strings, not ArcGIS dates) and match the UK times Floodline shows.
     * Every row with a status carries both, so a missing or unreadable one stops the
     * read rather than reading as no time, which would hide a frozen layer.
     */
    private static function sepaTime(mixed $value, string $field, string $area): Carbon
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value) !== 1) {
            throw new RuntimeException(sprintf(
                'SEPA flood warning for %s has an unreadable %s %s',
                $area,
                $field,
                is_scalar($value) ? '"'.$value.'"' : 'of type '.gettype($value),
            ));
        }

        try {
            return Carbon::parse($value, self::SOURCE_TIMEZONE)->utc();
        } catch (InvalidFormatException $exception) {
            throw new RuntimeException(sprintf('SEPA flood warning for %s has an impossible %s "%s"', $area, $field, $value), previous: $exception);
        }
    }
}
