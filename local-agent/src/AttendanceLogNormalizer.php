<?php

namespace LocalAttendanceAgent;

use DateTimeImmutable;
use DateTimeZone;

class AttendanceLogNormalizer
{
    private const VALID_TYPES = [0, 1, 4, 5];

    public function __construct(
        private Logger $logger,
        private int $futureSkewSeconds = 300,
        private ?DateTimeZone $timezone = null,
        private ?DateTimeImmutable $now = null
    ) {
        $this->timezone ??= new DateTimeZone('Asia/Karachi');
    }

    public function normalize(array $rows, ?string $lastAcknowledgedTimestamp): array
    {
        $accepted = [];

        foreach ($rows as $row) {
            $normalized = $this->normalizeRow($row);

            if ($normalized === null) {
                continue;
            }

            if ($lastAcknowledgedTimestamp !== null && $normalized['timestamp'] <= $lastAcknowledgedTimestamp) {
                continue;
            }

            $accepted[] = $normalized;
        }

        usort($accepted, fn ($a, $b) => strcmp($a['timestamp'], $b['timestamp']));

        return $accepted;
    }

    private function normalizeRow(mixed $row): ?array
    {
        if (! is_array($row)) {
            $this->logSkipped([], 'malformed_row');

            return null;
        }

        $timestamp = (string) ($row['timestamp'] ?? '');
        $time = $this->parseTimestamp($timestamp);

        if ($timestamp === '' || $time === null) {
            $this->logSkipped($row, 'malformed_timestamp');

            return null;
        }

        $now = $this->now ? $this->now->setTimezone($this->timezone) : new DateTimeImmutable('now', $this->timezone);
        $nowWithSkew = $now->modify('+'.$this->futureSkewSeconds.' seconds');

        if ($time > $nowWithSkew) {
            $this->logSkipped($row, 'future_timestamp');

            return null;
        }

        if (! $this->validInteger($row['id'] ?? null, 1)) {
            $this->logSkipped($row, 'invalid_employee_device_id');

            return null;
        }

        if (! $this->validInteger($row['type'] ?? null, 0) || ! in_array((int) $row['type'], self::VALID_TYPES, true)) {
            $this->logSkipped($row, 'unsupported_attendance_type');

            return null;
        }

        return [
            'id' => (int) $row['id'],
            'timestamp' => $timestamp,
            'type' => (int) $row['type'],
        ];
    }

    private function parseTimestamp(string $timestamp): ?DateTimeImmutable
    {
        $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $timestamp, $this->timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if (! $time || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        if ($time->format('Y-m-d H:i:s') !== $timestamp) {
            return null;
        }

        return $time;
    }

    private function validInteger(mixed $value, int $minimum): bool
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer !== false && $integer >= $minimum;
    }

    private function logSkipped(array $row, string $reason): void
    {
        $this->logger->error(
            'Skipped invalid device attendance row: '.
            'uid='.$this->safeValue($row['uid'] ?? null).', '.
            'employee/device id='.$this->safeValue($row['id'] ?? null).', '.
            'timestamp='.$this->safeValue($row['timestamp'] ?? null).', '.
            'reason='.$reason
        );
    }

    private function safeValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '[missing]';
        }

        $value = preg_replace('/[\r\n]+/', ' ', (string) $value) ?? (string) $value;

        return substr($value, 0, 80);
    }
}
