<?php

namespace Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use LocalAttendanceAgent\AgentState;
use LocalAttendanceAgent\ApiException;
use LocalAttendanceAgent\AttendanceLogNormalizer;
use LocalAttendanceAgent\Logger;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../local-agent/src/AgentState.php';
require_once __DIR__.'/../../local-agent/src/ApiException.php';
require_once __DIR__.'/../../local-agent/src/Logger.php';
require_once __DIR__.'/../../local-agent/src/AttendanceLogNormalizer.php';

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

        $this->assertSame(['pending' => 0, 'failed' => 0], $state->counts());
    }

    public function test_permanent_queued_failure_moves_to_dead_letter_and_remains_inspectable(): void
    {
        $state = $this->state();
        $state->queue('batch-bad', ['batch_id' => 'batch-bad', 'logs' => [['timestamp' => 'bad']]]);

        $state->deadLetter('batch-bad', 'API returned HTTP 422.', 422);

        $this->assertSame(['pending' => 0, 'failed' => 1], $state->counts());
        $this->assertCount(0, $state->dueBatches());
    }

    public function test_bearer_token_is_not_included_in_safe_api_error_details(): void
    {
        $exception = ApiException::http(422, '{"message":"bad","token":"secret-token","detail":"Bearer abc123"}');

        $this->assertStringNotContainsString('secret-token', $exception->getMessage());
        $this->assertStringNotContainsString('abc123', $exception->safeResponseBody() ?? '');
        $this->assertStringContainsString('[REDACTED]', $exception->safeResponseBody() ?? '');
    }

    private function normalizer(?DateTimeZone $timezone = null, ?DateTimeImmutable $now = null): AttendanceLogNormalizer
    {
        return new AttendanceLogNormalizer(new Logger($this->tempDir), 300, $timezone, $now);
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
