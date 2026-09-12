<?php
namespace LocalAttendanceAgent;
use PDO;
class AgentState {
 private PDO $db;
 public function __construct(string $path) { $this->db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); $this->db->exec('CREATE TABLE IF NOT EXISTS pending_batches (batch_id TEXT PRIMARY KEY,payload TEXT NOT NULL,attempts INTEGER NOT NULL DEFAULT 0,next_attempt_at INTEGER NOT NULL DEFAULT 0,created_at INTEGER NOT NULL)'); $this->db->exec('CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY,value TEXT NULL)'); }
 public function queue(string $id,array $payload):void { $s=$this->db->prepare('INSERT OR IGNORE INTO pending_batches (batch_id,payload,created_at) VALUES (?,?,?)'); $s->execute([$id,json_encode($payload,JSON_THROW_ON_ERROR),time()]); }
 public function dueBatches():array { $s=$this->db->prepare('SELECT * FROM pending_batches WHERE next_attempt_at <= ? ORDER BY created_at ASC'); $s->execute([time()]); return $s->fetchAll(PDO::FETCH_ASSOC); }
 public function acknowledge(string $id):void { $s=$this->db->prepare('DELETE FROM pending_batches WHERE batch_id=?'); $s->execute([$id]); }
 public function retry(string $id,int $base,int $max):void { $s=$this->db->prepare('SELECT attempts FROM pending_batches WHERE batch_id=?'); $s->execute([$id]); $n=(int)($s->fetchColumn()?:0)+1; $delay=min($max,$base*(2**min($n-1,10))); $u=$this->db->prepare('UPDATE pending_batches SET attempts=?,next_attempt_at=? WHERE batch_id=?'); $u->execute([$n,time()+$delay,$id]); }
 public function get(string $key):?string { $s=$this->db->prepare('SELECT value FROM meta WHERE key=?'); $s->execute([$key]); $v=$s->fetchColumn(); return $v===false?null:(string)$v; }
 public function set(string $key,?string $value):void { $s=$this->db->prepare('INSERT INTO meta (key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value'); $s->execute([$key,$value]); }
}
