<?php
declare(strict_types=1);
$root=dirname(__DIR__); require $root.'/vendor/autoload.php'; require __DIR__.'/src/AgentState.php'; require __DIR__.'/src/ApiClient.php'; require __DIR__.'/src/ZKTecoReader.php';
use LocalAttendanceAgent\AgentState; use LocalAttendanceAgent\ApiClient; use LocalAttendanceAgent\ZKTecoReader;
$configPath=__DIR__.'/config.json'; if(!is_file($configPath)){fwrite(STDERR,"Missing local-agent/config.json. Copy config.example.json first.\n");exit(1);} $config=json_decode(file_get_contents($configPath),true,512,JSON_THROW_ON_ERROR); foreach(['api_base_url','api_token','device_identifier','device_ip','device_port'] as $required){if(empty($config[$required])) throw new RuntimeException("Missing config: {$required}");}
$state=new AgentState(__DIR__.'/state.sqlite'); $api=new ApiClient($config); $reader=new ZKTecoReader($config);
function agentUuid():string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
function agentNormalize(array $logs,?string $last):array{$out=[];foreach($logs as $log){$ts=(string)($log['timestamp']??'');if($ts===''||($last!==null&&$ts<=$last))continue;$out[]=['id'=>(int)($log['id']??0),'timestamp'=>$ts,'type'=>(int)($log['type']??0)];}usort($out,fn($a,$b)=>strcmp($a['timestamp'],$b['timestamp']));return $out;}
try{
 foreach($state->dueBatches() as $batch){try{$stored=json_decode($batch['payload'],true,512,JSON_THROW_ON_ERROR);$max=$stored['_max_timestamp']??null;unset($stored['_max_timestamp']);$api->sync($stored);$state->acknowledge($batch['batch_id']);if($max)$state->set('last_acknowledged_timestamp',$max);}catch(Throwable $e){$state->retry($batch['batch_id'],(int)($config['retry_base_seconds']??30),(int)($config['retry_max_seconds']??1800));}}
 $api->heartbeat(); $logs=agentNormalize($reader->readAttendance(),$state->get('last_acknowledged_timestamp')); $size=max(1,min(1000,(int)($config['batch_size']??500)));
 foreach(array_chunk($logs,$size) as $chunk){$id=agentUuid();$max=$chunk[count($chunk)-1]['timestamp'];$http=['batch_id'=>$id,'device_identifier'=>$config['device_identifier'],'logs'=>$chunk];$stored=$http;$stored['_max_timestamp']=$max;$state->queue($id,$stored);try{$api->sync($http);$state->acknowledge($id);$state->set('last_acknowledged_timestamp',$max);}catch(Throwable $e){$state->retry($id,(int)($config['retry_base_seconds']??30),(int)($config['retry_max_seconds']??1800));break;}}
 exit(0);
}catch(Throwable $e){fwrite(STDERR,'['.date('c').'] '.$e->getMessage().PHP_EOL);exit(1);}
