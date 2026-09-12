<?php
namespace LocalAttendanceAgent;
class Logger {
 public function __construct(private string $directory,private int $maxBytes=1048576){if(!is_dir($directory))@mkdir($directory,0775,true);}
 public function info(string $message):void{$this->write('INFO',$message);}
 public function error(string $message):void{$this->write('ERROR',$message);}
 private function write(string $level,string $message):void{$file=$this->directory.'/agent.log';if(is_file($file)&&filesize($file)>$this->maxBytes){@rename($file,$this->directory.'/agent-previous.log');}$safe=preg_replace('/Bearer\s+\S+/i','Bearer [REDACTED]',$message);file_put_contents($file,'['.date('c').'] '.$level.' '.$safe.PHP_EOL,FILE_APPEND|LOCK_EX);}
}
