<?php

namespace LocalAttendanceAgent;

class DeviceUserNormalizer
{
    public function __construct(private Logger $logger) {}

    public function normalize(array $rows): array
    {
        $users = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $this->logSkipped([], 'malformed_user');

                continue;
            }

            $userid = filter_var($row['userid'] ?? null, FILTER_VALIDATE_INT);

            if ($userid === false || $userid < 1) {
                $this->logSkipped($row, 'invalid_userid');

                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));

            $users[] = [
                'userid' => (string) $userid,
                'name' => $name !== '' ? substr($name, 0, 255) : 'Device User '.$userid,
            ];
        }

        return $users;
    }

    private function logSkipped(array $row, string $reason): void
    {
        $this->logger->error(
            'Skipped invalid device user row: '.
            'userid='.$this->safeValue($row['userid'] ?? null).', '.
            'name='.$this->safeValue($row['name'] ?? null).', '.
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
