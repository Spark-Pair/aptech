<?php
namespace LocalAttendanceAgent;
use RuntimeException;
class ApiClient {
 public function __construct(private array $config) {}
 public function heartbeat():void { $this->post('/api/v1/attendance-agent/heartbeat',[]); }
 public function sync(array $payload):array { return $this->post('/api/v1/attendance-agent/sync',$payload); }
 private function post(string $path,array $payload):array { if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required.'); $url=rtrim($this->config['api_base_url'],'/').$path; if(stripos($url,'https://')!==0 && empty($this->config['allow_insecure_http'])) throw new RuntimeException('HTTPS is required.'); $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>(int)($this->config['http_timeout']??20),CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json','Authorization: Bearer '.$this->config['api_token']],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR)]); $body=curl_exec($ch); if($body===false){$e=curl_error($ch);curl_close($ch);throw new RuntimeException('API connection failed: '.$e);} $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$decoded=json_decode($body,true);if($status<200||$status>=300)throw new RuntimeException('API returned HTTP '.$status.'.');return is_array($decoded)?$decoded:[]; }
}
