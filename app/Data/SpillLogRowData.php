<?php

namespace App\Data;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

/**
 * One spill from a company's published log: the overflow's id as the live feeds give it, and its start and stop as
 * UTC timestamps (the logs give GMT).
 */
final readonly class SpillLogRowData
{
    public function __construct(
        public string $externalId,
        public int $startedAt,
        public int $endedAt,
    ) {
    }

    /**
     * A spreadsheet or CSV row's id, start and stop cells; null for a header, blank or unreadable row, or one that
     * stops before it starts.
     */
    public static function fromCells(mixed $id, mixed $start, mixed $end): ?self
    {
        $externalId = is_scalar($id) ? strtoupper(trim((string) $id)) : '';
        $startedAt = self::timestamp($start);
        $endedAt = self::timestamp($end);

        if ($externalId === '' || $startedAt === null || $endedAt === null || $endedAt < $startedAt) {
            return null;
        }

        return new self($externalId, $startedAt, $endedAt);
    }

    /**
     * A cell's time in GMT as a timestamp. The files mix ISO strings ("2025-01-01T00:00:00.000Z"), slashed dates
     * ("2024/01/01T20:14:32.000Z", "20/07/2025 13:51"), spreadsheet dates and Excel day numbers.
     */
    public static function timestamp(mixed $value): ?int
    {
        if ($value instanceof DateTimeInterface) {
            // Spreadsheet dates carry no zone of their own; the logs give GMT.
            return (new DateTimeImmutable($value->format('Y-m-d H:i:s'), new DateTimeZone('UTC')))->getTimestamp();
        }

        if (is_int($value) || is_float($value)) {
            return (int) round(($value - 25569) * 86400);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $text = trim($value);

        if (is_numeric($text)) {
            return (int) round(((float) $text - 25569) * 86400);
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})(.*)$#', $text, $parts) === 1) {
            $text = sprintf('%s-%02d-%02d%s', $parts[3], $parts[2], $parts[1], $parts[4]);
        }

        $text = (string) preg_replace('#^(\d{4})/(\d{2})/(\d{2})#', '$1-$2-$3', $text);

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $text) !== 1) {
            return null;
        }

        // Millions of rows pass through here, so the plain date class rather than Carbon.
        try {
            return (new DateTimeImmutable($text, new DateTimeZone('UTC')))->getTimestamp();
        } catch (Exception) {
            return null;
        }
    }
}
