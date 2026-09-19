<?php

namespace LocalAttendanceAgent;

use Throwable;

class AgentRunner
{
    private bool $stop = false;
    public function __construct(private array $config, private AgentState $state, private ApiClient $api, private ZKTecoReader $reader, private Logger $log, private AttendanceLogNormalizer $attendanceNormalizer, private DeviceUserNormalizer $userNormalizer, private DeviceAttendanceIdentity $identity, private $sleep = null) { $this->sleep ??= fn (int $seconds) => sleep($seconds); }

    public function runOnce(): int
    {
        $started = microtime(true);
        $retried = $this->replayDueBatches();
        $heartbeat = $this->api->heartbeat();
        $devices = isset($heartbeat['devices']) && is_array($heartbeat['devices']) ? $heartbeat['devices'] : [];
        if ($devices === [] && isset($heartbeat['device']) && is_array($heartbeat['device'])) $devices = [$heartbeat['device']];

        $sent = 0;
        $processed = 0;
        foreach ($devices as $device) {
            if (! is_array($device) || empty($device['device_identifier'])) continue;
            try {
                $sent += $this->processDevice($device);
                $processed++;
            } catch (Throwable $e) {
                $this->log->error('Device '.$device['device_identifier'].' cycle failed: '.$e->getMessage());
            }
        }

        $this->state->pruneAckedHistory((int)($this->config['local_ack_history_days'] ?? 14) * 86400, (int)($this->config['local_ack_history_keep'] ?? 5000));
        $duration = (int) round((microtime(true) - $started) * 1000); $counts = $this->state->counts();
        $this->log->info('Cycle complete. Devices='.count($devices).', connected='.$processed.', new rows='.$sent.', replayed batches='.$retried.', pending batches='.$counts['pending'].', cycle duration ms='.$duration.'.');
        return $sent;
    }

    private function processDevice(array $device): int
    {
        $identifier = (string) $device['device_identifier'];
        $this->reader->applyRemoteConfig($device);
        $snapshot = $this->reader->readSnapshot();
        $users = $this->userNormalizer->normalize($snapshot['users']);
        $this->syncUsersIfNeeded($identifier, $users);
        $rawRows = $snapshot['attendance'];
        $fingerprints = array_map(fn ($row) => $this->fingerprint($identifier, $row), $rawRows);
        $known = $this->state->knownFingerprints($fingerprints);
        $newRows = array_values(array_filter($rawRows, fn ($row) => ! isset($known[$this->fingerprint($identifier, $row)])));
        $records = [];
        $timezone = (string)($device['device_timezone'] ?? $this->config['device_timezone'] ?? 'Asia/Karachi');
        $normalizer = $this->attendanceNormalizer->withTimezone($timezone);
        foreach ($newRows as $row) {
            $normalized = $normalizer->normalize([$row], null);
            if ($normalized !== []) $records[] = ['log' => $normalized[0], 'fingerprint' => $this->fingerprint($identifier, $row)];
        }
        usort($records, fn ($a, $b) => strcmp($a['log']['timestamp'], $b['log']['timestamp']));
        $sent = $this->sendNewAttendance($identifier, $records);
        $this->log->info('Device '.$identifier.' complete. Device rows='.count($rawRows).', new rows='.$sent.'.');
        return $sent;
    }

    public function runContinuously(): void { $interval=$this->pollInterval(); $this->installSignalHandlers(); while(!$this->stop){ try{$this->runOnce();}catch(ApiException $e){$this->log->error(($e->isRetryable()?'Retryable':'API').' cycle failure: '.$e->getMessage());}catch(Throwable $e){$this->log->error('Cycle failed: '.$e->getMessage());} if(!$this->stop)($this->sleep)($interval); } }
    public function cleanupDryRun(): array { $rawRows=$this->reader->readAttendance(); $fingerprints=array_map(fn($row)=>$this->identity->fingerprint($row),$rawRows); return $this->state->cleanupDiagnostics($fingerprints)+['capability'=>$this->reader->cleanupCapability()]; }
    public function pollInterval(): int { return max(1,(int)($this->config['poll_interval_seconds']??5)); }
    public function stop(): void { $this->stop=true; }

    private function replayDueBatches(): int { $retried=0; foreach($this->state->dueBatches() as $batch){ try{$stored=json_decode($batch['payload'],true,512,JSON_THROW_ON_ERROR);$max=$stored['_max_timestamp']??null;unset($stored['_max_timestamp']);$identifier=(string)($stored['device_identifier']??'');$this->api->sync($stored);$this->state->markBatchAcked($batch['batch_id']);if($max&&$identifier!=='')$this->state->set('last_acknowledged_timestamp'.$this->stateSuffix($identifier),$max);$retried++;}catch(ApiException $e){if($e->isRetryable()){$this->state->retry($batch['batch_id'],(int)($this->config['retry_base_seconds']??30),(int)($this->config['retry_max_seconds']??1800));$this->log->error('Retry batch '.$batch['batch_id'].' failed: '.$e->getMessage());}else{$this->state->deadLetter($batch['batch_id'],$e->getMessage(),$e->statusCode());$this->log->error('Retry batch '.$batch['batch_id'].' moved to dead letter: '.$e->getMessage());}}catch(Throwable $e){$this->state->retry($batch['batch_id'],(int)($this->config['retry_base_seconds']??30),(int)($this->config['retry_max_seconds']??1800));$this->log->error('Retry batch '.$batch['batch_id'].' failed: '.$e->getMessage());}} return $retried; }

    private function syncUsersIfNeeded(string $identifier, array $users): void
    {
        $fingerprint=hash('sha256',json_encode($users,JSON_THROW_ON_ERROR));
        $suffix=$this->stateSuffix($identifier);
        $hashKey='last_user_roster_hash'.$suffix;
        $timeKey='last_user_sync_at'.$suffix;
        $lastSync=(int)($this->state->get($timeKey)??0);
        $interval=max(1,(int)($this->config['user_sync_interval_seconds']??60));
        if($fingerprint===$this->state->get($hashKey)&&(time()-$lastSync)<$interval)return;
        $this->api->syncUsers(['device_identifier'=>$identifier,'users'=>$users]);
        $this->state->set($hashKey,$fingerprint);
        $this->state->set($timeKey,(string)time());
    }

    private function sendNewAttendance(string $identifier, array $records): int
    {
        $size=max(1,min(1000,(int)($this->config['batch_size']??500)));$sent=0;
        foreach(array_chunk($records,$size) as $chunk){$id=$this->uuid();$logs=array_map(fn($record)=>$record['log'],$chunk);$max=$logs[count($logs)-1]['timestamp'];$http=['batch_id'=>$id,'device_identifier'=>$identifier,'logs'=>$logs];$stored=$http;$stored['_max_timestamp']=$max;$fingerprints=array_map(fn($record)=>$record['fingerprint'],$chunk);$this->state->queueAttendanceBatch($id,$stored,$fingerprints);try{$this->api->sync($http);$this->state->markBatchAcked($id);$this->state->set('last_acknowledged_timestamp'.$this->stateSuffix($identifier),$max);$sent+=count($logs);}catch(ApiException $e){if($e->isRetryable()){$this->state->retry($id,(int)($this->config['retry_base_seconds']??30),(int)($this->config['retry_max_seconds']??1800));$this->log->error('New batch '.$id.' for '.$identifier.' queued for retry: '.$e->getMessage());}else{$this->state->deadLetter($id,$e->getMessage(),$e->statusCode());$this->log->error('New batch '.$id.' for '.$identifier.' moved to dead letter: '.$e->getMessage());}break;}catch(Throwable $e){$this->state->retry($id,(int)($this->config['retry_base_seconds']??30),(int)($this->config['retry_max_seconds']??1800));$this->log->error('New batch '.$id.' for '.$identifier.' queued for retry: '.$e->getMessage());break;}}
        return $sent;
    }

    private function fingerprint(string $identifier, array $row): string
    {
        $base = $this->identity->fingerprint($row);
        if ($identifier === (string)($this->config['device_identifier'] ?? '')) return $base;
        return hash('sha256', $identifier.'|'.$base);
    }

    private function stateSuffix(string $identifier): string
    {
        return $identifier === (string)($this->config['device_identifier'] ?? '') ? '' : ':'.hash('sha256',$identifier);
    }

    private function installSignalHandlers(): void { if(!function_exists('pcntl_signal'))return;pcntl_async_signals(true);pcntl_signal(SIGTERM,fn()=>$this->stop());pcntl_signal(SIGINT,fn()=>$this->stop()); }
    private function uuid(): string { $d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4)); }
}
