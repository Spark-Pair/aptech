<?php

namespace App\Services;

use Rats\Zkteco\Lib\ZKTeco;
use RuntimeException;

class ZKTecoService
{
    protected ?ZKTeco $zk = null;

    public function connect(): bool
    {
        if (! extension_loaded('sockets')) {
            throw new RuntimeException('Enable the PHP sockets extension to connect to the attendance device.');
        }
        $this->zk = new ZKTeco(config('attendance.device_ip'), config('attendance.device_port'));
        socket_set_option($this->zk->_zkclient, SOL_SOCKET, SO_RCVTIMEO, ['sec' => max(1, config('attendance.device_timeout')), 'usec' => 0]);

        return (bool) $this->zk->connect();
    }

    public function getAttendanceLogs(): array
    {
        return $this->zk?->getAttendance() ?: [];
    }

    public function getUsers(): array
    {
        return $this->zk?->getUser() ?: [];
    }

    public function disconnect(): void
    {
        $this->zk?->disconnect();
    }
}
