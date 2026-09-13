<?php

namespace LocalAttendanceAgent;

class DeviceAttendanceIdentity
{
    public function fingerprint(array $row): string
    {
        $parts = [
            'uid' => (string) ($row['uid'] ?? ''),
            'id' => (string) ($row['id'] ?? ''),
            'state' => (string) ($row['state'] ?? ''),
            'timestamp' => (string) ($row['timestamp'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
        ];

        return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }
}
