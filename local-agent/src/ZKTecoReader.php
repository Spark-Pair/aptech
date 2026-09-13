<?php
namespace LocalAttendanceAgent;
use Rats\Zkteco\Lib\ZKTeco;
use RuntimeException;
class ZKTecoReader {
 public function __construct(private array $config) {}
 public function readAttendance():array { if(!extension_loaded('sockets')) throw new RuntimeException('PHP sockets extension is required on the local agent machine.'); $zk=new ZKTeco($this->config['device_ip'],(int)$this->config['device_port']); socket_set_option($zk->_zkclient,SOL_SOCKET,SO_RCVTIMEO,['sec'=>max(1,(int)($this->config['device_timeout']??5)),'usec'=>0]); if(!$zk->connect()) throw new RuntimeException('Unable to connect to ZKTeco device.'); try{return $zk->getAttendance()?:[];}finally{$zk->disconnect();} }
 public function readUsers():array { if(!extension_loaded('sockets')) throw new RuntimeException('PHP sockets extension is required on the local agent machine.'); $zk=new ZKTeco($this->config['device_ip'],(int)$this->config['device_port']); socket_set_option($zk->_zkclient,SOL_SOCKET,SO_RCVTIMEO,['sec'=>max(1,(int)($this->config['device_timeout']??5)),'usec'=>0]); if(!$zk->connect()) throw new RuntimeException('Unable to connect to ZKTeco device.'); try{return $zk->getUser()?:[];}finally{$zk->disconnect();} }
 public function readSnapshot():array { if(!extension_loaded('sockets')) throw new RuntimeException('PHP sockets extension is required on the local agent machine.'); $zk=new ZKTeco($this->config['device_ip'],(int)$this->config['device_port']); socket_set_option($zk->_zkclient,SOL_SOCKET,SO_RCVTIMEO,['sec'=>max(1,(int)($this->config['device_timeout']??5)),'usec'=>0]); if(!$zk->connect()) throw new RuntimeException('Unable to connect to ZKTeco device.'); try{return ['users'=>$zk->getUser()?:[],'attendance'=>$zk->getAttendance()?:[]];}finally{$zk->disconnect();} }
 public function cleanupCapability():array { return ['mode'=>'bulk_clear_all_only','supports_per_record_delete'=>false,'supports_clear_all'=>true,'destructive_enabled'=>false]; }
}
