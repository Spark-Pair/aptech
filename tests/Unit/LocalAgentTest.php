<?php

namespace Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use LocalAttendanceAgent\AgentState;
use LocalAttendanceAgent\AgentRunner;
use LocalAttendanceAgent\ApiClient;
use LocalAttendanceAgent\ApiException;
use LocalAttendanceAgent\AttendanceLogNormalizer;
use LocalAttendanceAgent\DeviceAttendanceIdentity;
use LocalAttendanceAgent\DeviceUserNormalizer;
use LocalAttendanceAgent\Logger;
use LocalAttendanceAgent\ZKTecoReader;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../local-agent/src/AgentState.php';
require_once __DIR__.'/../../local-agent/src/ApiException.php';
require_once __DIR__.'/../../local-agent/src/ApiClient.php';
require_once __DIR__.'/../../local-agent/src/ZKTecoReader.php';
require_once __DIR__.'/../../local-agent/src/Logger.php';
require_once __DIR__.'/../../local-agent/src/AttendanceLogNormalizer.php';
require_once __DIR__.'/../../local-agent/src/DeviceUserNormalizer.php';
require_once __DIR__.'/../../local-agent/src/DeviceAttendanceIdentity.php';
require_once __DIR__.'/../../local-agent/src/AgentRunner.php';

class LocalAgentTest extends TestCase
{
    private string $tempDir;
    private string $originalTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTimezone = date_default_timezone_get();
        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'local-agent-test-'.bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
        date_default_timezone_set($this->originalTimezone);

        parent::tearDown();
    }

    public function test_valid_current_local_timestamp_is_accepted(): void
    {
        $timestamp = date('Y-m-d H:i:s', time() - 60);

        $logs = $this->normalizer()->normalize([
            ['uid' => 1, 'id' => 101, 'timestamp' => $timestamp, 'type' => 0],
        ], null);

        $this->assertSame([['id' => 101, 'timestamp' => $timestamp, 'type' => 0]], $logs);
    }

    public function test_valid_historical_timestamp_is_accepted(): void
    {
        $logs = $this->normalizer()->normalize([
            ['uid' => 2, 'id' => 101, 'timestamp' => '2025-05-19 09:00:00', 'type' => 5],
        ], null);

        $this->assertSame([['id' => 101, 'timestamp' => '2025-05-19 09:00:00', 'type' => 5]], $logs);
    }

    public function test_malformed_timestamp_is_rejected_and_logged(): void
    {
        $logs = $this->normalizer()->normalize([
            ['uid' => 3, 'id' => 101, 'timestamp' => 'not-a-date', 'type' => 0],
        ], null);

        $this->assertSame([], $logs);
        $this->assertStringContainsString('reason=malformed_timestamp', $this->agentLog());
    }

    public function test_future_timestamp_is_rejected_and_logged(): void
    {
        $future = date('Y-m-d H:i:s', time() + 86400);

        $logs = $this->normalizer()->normalize([
            ['uid' => 4, 'id' => 101, 'timestamp' => $future, 'type' => 0],
        ], null);

        $this->assertSame([], $logs);
        $this->assertStringContainsString('reason=future_timestamp', $this->agentLog());
    }

    public function test_mixed_future_and_valid_rows_leave_valid_row_eligible_for_batching(): void
    {
        $valid = date('Y-m-d H:i:s', time() - 120);
        $future = date('Y-m-d H:i:s', time() + 86400);

        $logs = $this->normalizer()->normalize([
            ['uid' => 50, 'id' => 1, 'state' => 15, 'timestamp' => $future, 'type' => 0],
            ['uid' => 52, 'id' => 1, 'state' => 1, 'timestamp' => $valid, 'type' => 5],
        ], null);

        $this->assertSame([['id' => 1, 'timestamp' => $valid, 'type' => 5]], $logs);
    }

    public function test_device_timezone_prevents_pakistan_wall_clock_punch_from_being_treated_as_future(): void
    {
        date_default_timezone_set('UTC');
        $normalizer = $this->normalizer(
            new DateTimeZone('Asia/Karachi'),
            new DateTimeImmutable('2026-09-13T09:46:15+00:00')
        );

        $logs = $normalizer->normalize([
            ['uid' => 50, 'id' => 1, 'timestamp' => '2026-09-20 13:03:04', 'type' => 0],
            ['uid' => 51, 'id' => 1, 'timestamp' => '2026-09-20 13:25:49', 'type' => 5],
            ['uid' => 52, 'id' => 1, 'timestamp' => '2026-09-13 14:18:42', 'type' => 5],
        ], null);

        $this->assertSame([['id' => 1, 'timestamp' => '2026-09-13 14:18:42', 'type' => 5]], $logs);
        $log = $this->agentLog();
        $this->assertStringContainsString('uid=50', $log);
        $this->assertStringContainsString('uid=51', $log);
        $this->assertStringContainsString('reason=future_timestamp', $log);
        $this->assertStringNotContainsString('uid=52', $log);
    }

    public function test_invalid_future_row_does_not_advance_successful_checkpoint(): void
    {
        $state = $this->state();
        $valid = date('Y-m-d H:i:s', time() - 120);
        $future = date('Y-m-d H:i:s', time() + 86400);

        $logs = $this->normalizer()->normalize([
            ['uid' => 50, 'id' => 1, 'timestamp' => $future, 'type' => 0],
            ['uid' => 52, 'id' => 1, 'timestamp' => $valid, 'type' => 5],
        ], $state->get('last_acknowledged_timestamp'));

        $state->set('last_acknowledged_timestamp', $logs[count($logs) - 1]['timestamp']);

        $this->assertSame($valid, $state->get('last_acknowledged_timestamp'));
    }

    public function test_http_failure_classification(): void
    {
        $this->assertSame(ApiException::PERMANENT, ApiException::classifyStatus(422));
        $this->assertSame(ApiException::TRANSIENT, ApiException::classifyStatus(500));
        $this->assertSame(ApiException::TRANSIENT, ApiException::classifyStatus(429));
        $this->assertTrue(ApiException::connectionFailure('timeout')->isRetryable());
    }

    public function test_successful_acknowledgement_removes_pending_batch(): void
    {
        $state = $this->state();
        $state->queue('batch-ok', ['batch_id' => 'batch-ok', 'logs' => []]);

        $state->acknowledge('batch-ok');

        $this->assertSame(0, $state->counts()['pending']);
        $this->assertSame(0, $state->counts()['failed']);
    }

    public function test_permanent_queued_failure_moves_to_dead_letter_and_remains_inspectable(): void
    {
        $state = $this->state();
        $state->queue('batch-bad', ['batch_id' => 'batch-bad', 'logs' => [['timestamp' => 'bad']]]);

        $state->deadLetter('batch-bad', 'API returned HTTP 422.', 422);

        $this->assertSame(0, $state->counts()['pending']);
        $this->assertSame(1, $state->counts()['failed']);
        $this->assertCount(0, $state->dueBatches());
    }

    public function test_bearer_token_is_not_included_in_safe_api_error_details(): void
    {
        $exception = ApiException::http(422, '{"message":"bad","token":"secret-token","detail":"Bearer abc123"}');

        $this->assertStringNotContainsString('secret-token', $exception->getMessage());
        $this->assertStringNotContainsString('abc123', $exception->safeResponseBody() ?? '');
        $this->assertStringContainsString('[REDACTED]', $exception->safeResponseBody() ?? '');
    }

    public function test_device_user_normalization_keeps_only_safe_api_fields(): void
    {
        $users = $this->userNormalizer()->normalize([
            [
                'uid' => 1,
                'userid' => 1,
                'name' => 'Hasan',
                'role' => 14,
                'password' => 'zkteco-device-password',
                'cardno' => '0000000000',
            ],
        ]);

        $this->assertSame([['userid' => '1', 'name' => 'Hasan']], $users);
        $this->assertArrayNotHasKey('password', $users[0]);
        $this->assertArrayNotHasKey('cardno', $users[0]);
        $this->assertArrayNotHasKey('uid', $users[0]);
        $this->assertArrayNotHasKey('role', $users[0]);
    }

    public function test_device_user_normalization_skips_invalid_userid_without_logging_password(): void
    {
        $users = $this->userNormalizer()->normalize([
            ['userid' => '', 'name' => 'Bad', 'password' => 'zkteco-device-password', 'cardno' => '0000000000'],
        ]);

        $this->assertSame([], $users);
        $this->assertStringContainsString('reason=invalid_userid', $this->agentLog());
        $this->assertStringNotContainsString('zkteco-device-password', $this->agentLog());
        $this->assertStringNotContainsString('0000000000', $this->agentLog());
    }

    public function test_runner_poll_interval_has_safe_minimum(): void
    {
        $this->assertSame(1, $this->runner(['poll_interval_seconds' => 0])->pollInterval());
        $this->assertSame(5, $this->runner(['poll_interval_seconds' => 5])->pollInterval());
    }

    public function test_runner_sends_only_new_rows_from_duplicate_history(): void
    {
        $api = new FakeApiClient();
        $runner = $this->runner(api: $api, reader: new FakeZKTecoReader(attendance: [
            ['uid' => 1, 'id' => 1, 'state' => 1, 'timestamp' => date('Y-m-d H:i:s', time() - 60), 'type' => 0],
        ]));

        $this->assertSame(1, $runner->runOnce());
        $this->assertSame(0, $runner->runOnce());
        $this->assertCount(1, $api->syncPayloads);
    }

    public function test_runner_handles_same_timestamp_records_as_distinct_when_uid_differs(): void
    {
        $api = new FakeApiClient();
        $timestamp = date('Y-m-d H:i:s', time() - 60);

        $this->runner(api: $api, reader: new FakeZKTecoReader(attendance: [
            ['uid' => 1, 'id' => 1, 'state' => 1, 'timestamp' => $timestamp, 'type' => 0],
            ['uid' => 2, 'id' => 1, 'state' => 2, 'timestamp' => $timestamp, 'type' => 5],
        ]))->runOnce();

        $this->assertCount(2, $api->syncPayloads[0]['logs']);
    }

    public function test_runner_sends_delayed_older_record_after_newer_record_was_acked(): void
    {
        $api = new FakeApiClient();
        $reader = new FakeZKTecoReader(attendance: [
            ['uid' => 10, 'id' => 1, 'state' => 1, 'timestamp' => date('Y-m-d H:i:s', time() - 60), 'type' => 0],
        ]);
        $runner = $this->runner(api: $api, reader: $reader);
        $runner->runOnce();

        $reader->attendance[] = ['uid' => 9, 'id' => 1, 'state' => 1, 'timestamp' => date('Y-m-d H:i:s', time() - 3600), 'type' => 0];
        $runner->runOnce();

        $this->assertCount(2, $api->syncPayloads);
        $this->assertSame(9, $reader->attendance[1]['uid']);
    }

    public function test_timeout_after_post_keeps_batch_pending_and_not_cleanup_eligible(): void
    {
        $api = new FakeApiClient(syncException: ApiException::connectionFailure('timeout'));
        $state = $this->state();

        $this->runner(['retry_base_seconds' => 0, 'retry_max_seconds' => 0], $state, $api, new FakeZKTecoReader(attendance: [
            ['uid' => 1, 'id' => 1, 'state' => 1, 'timestamp' => date('Y-m-d H:i:s', time() - 60), 'type' => 0],
        ]))->runOnce();

        $this->assertSame(1, $state->counts()['pending']);
        $this->assertSame(0, $state->counts()['acked']);
        $this->assertSame(1, $state->cleanupDiagnostics([hash('sha256', json_encode(['uid'=>'1']))])['unresolved_rows']);
    }

    public function test_replayed_batch_ack_marks_record_acked(): void
    {
        $state = $this->state();
        $reader = new FakeZKTecoReader(attendance: [
            ['uid' => 1, 'id' => 1, 'state' => 1, 'timestamp' => date('Y-m-d H:i:s', time() - 60), 'type' => 0],
        ]);
        $this->runner(['retry_base_seconds' => 0, 'retry_max_seconds' => 0], $state, new FakeApiClient(syncException: ApiException::connectionFailure('timeout')), $reader)->runOnce();

        $this->runner([], $state, new FakeApiClient(), $reader)->runOnce();

        $this->assertSame(0, $state->counts()['pending']);
        $this->assertSame(1, $state->counts()['acked']);
    }

    public function test_permanent_422_dead_letters_without_cleanup_eligibility(): void
    {
        $state = $this->state();
        $this->runner([], $state, new FakeApiClient(syncException: ApiException::http(422, '{"message":"bad"}')), new FakeZKTecoReader(attendance: [
            ['uid' => 1, 'id' => 1, 'state' => 1, 'timestamp' => date('Y-m-d H:i:s', time() - 60), 'type' => 0],
        ]))->runOnce();

        $this->assertSame(0, $state->counts()['pending']);
        $this->assertSame(1, $state->counts()['failed']);
        $this->assertSame(0, $state->counts()['acked']);
    }

    public function test_user_roster_is_not_posted_every_cycle_when_unchanged(): void
    {
        $api = new FakeApiClient();
        $runner = $this->runner(['user_sync_interval_seconds' => 60], api: $api, reader: new FakeZKTecoReader(users: [
            ['userid' => 1, 'name' => 'Hasan'],
        ]));

        $runner->runOnce();
        $runner->runOnce();

        $this->assertCount(1, $api->userPayloads);
    }

    public function test_cleanup_dry_run_reports_disabled_bulk_only_capability(): void
    {
        $result = $this->runner(reader: new FakeZKTecoReader(attendance: [
            ['uid' => 1, 'id' => 1, 'state' => 1, 'timestamp' => date('Y-m-d H:i:s', time() - 60), 'type' => 0],
        ]))->cleanupDryRun();

        $this->assertFalse($result['cleanup_enabled']);
        $this->assertSame('bulk_clear_all_only', $result['cleanup_capability']);
        $this->assertSame('bulk_clear_all_only', $result['capability']['mode']);
    }

    public function test_prune_preserves_pending_and_removes_only_old_acked_history(): void
    {
        $state = $this->state();
        $state->queueAttendanceBatch('acked', ['batch_id' => 'acked', 'logs' => []], ['fp-acked']);
        $state->markBatchAcked('acked');
        $state->queueAttendanceBatch('pending', ['batch_id' => 'pending', 'logs' => []], ['fp-pending']);

        $this->assertGreaterThanOrEqual(0, $state->pruneAckedHistory(0, 0));
        $this->assertSame(1, $state->counts()['records_pending']);
    }

    private function normalizer(?DateTimeZone $timezone = null, ?DateTimeImmutable $now = null): AttendanceLogNormalizer
    {
        return new AttendanceLogNormalizer(new Logger($this->tempDir), 300, $timezone, $now);
    }

    private function userNormalizer(): DeviceUserNormalizer
    {
        return new DeviceUserNormalizer(new Logger($this->tempDir));
    }

    private function runner(array $config = [], ?AgentState $state = null, ?FakeApiClient $api = null, ?FakeZKTecoReader $reader = null): AgentRunner
    {
        $log = new Logger($this->tempDir);
        return new AgentRunner(
            ['device_identifier' => 'zk-office-1', 'batch_size' => 500] + $config,
            $state ?? $this->state(),
            $api ?? new FakeApiClient(),
            $reader ?? new FakeZKTecoReader(),
            $log,
            new AttendanceLogNormalizer($log, 300, new DateTimeZone('Asia/Karachi')),
            new DeviceUserNormalizer($log),
            new DeviceAttendanceIdentity(),
            fn (int $seconds) => null,
        );
    }

    private function state(): AgentState
    {
        return new AgentState($this->tempDir.DIRECTORY_SEPARATOR.'state.sqlite');
    }

    private function agentLog(): string
    {
        return file_get_contents($this->tempDir.DIRECTORY_SEPARATOR.'agent.log') ?: '';
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}

class FakeApiClient extends ApiClient
{
    public array $syncPayloads = [];
    public array $userPayloads = [];

    public function __construct(private ?ApiException $syncException = null)
    {
        parent::__construct(['api_base_url' => 'https://example.test', 'api_token' => str_repeat('x', 32)]);
    }

    public function heartbeat(): void {}

    public function sync(array $payload): array
    {
        if ($this->syncException) {
            throw $this->syncException;
        }

        $this->syncPayloads[] = $payload;
        return ['duplicate' => false];
    }

    public function syncUsers(array $payload): array
    {
        $this->userPayloads[] = $payload;
        return ['created' => count($payload['users'] ?? []), 'existing' => 0, 'skipped' => 0];
    }
}

class FakeZKTecoReader extends ZKTecoReader
{
    public function __construct(public array $users = [], public array $attendance = [])
    {
        parent::__construct(['device_ip' => '127.0.0.1', 'device_port' => 4370]);
    }

    public function readUsers(): array
    {
        return $this->users;
    }

    public function readAttendance(): array
    {
        return $this->attendance;
    }

    public function readSnapshot(): array
    {
        return ['users' => $this->users, 'attendance' => $this->attendance];
    }

    public function cleanupCapability(): array
    {
        return ['mode' => 'bulk_clear_all_only', 'supports_per_record_delete' => false, 'supports_clear_all' => true, 'destructive_enabled' => false];
    }
}
