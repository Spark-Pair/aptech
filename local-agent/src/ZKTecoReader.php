<?php
namespace LocalAttendanceAgent;
use Rats\Zkteco\Lib\ZKTeco;
use RuntimeException;
class ZKTecoReader {
 public function __construct(private array $config) {}
 public function readAttendance():array { if(!extension_loaded('sockets')) throw new RuntimeException('PHP sockets extension is required on the local agent machine.'); $zk=new ZKTeco($this->config['device_ip'],(int)$this->config['device_port']); socket_set_option($zk->_zkclient,SOL_SOCKET,SO_RCVTIMEO,['sec'=>max(1,(int)($this->config['device_timeout']??5)),'usec'=>0]); if(!$zk->connect()) throw new RuntimeException('Unable to connect to ZKTeco device.'); try{return $zk->getAttendance()?:[];}finally{$zk->disconnect();} }
}
