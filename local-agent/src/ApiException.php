<?php

namespace LocalAttendanceAgent;

use RuntimeException;
use Throwable;

class ApiException extends RuntimeException
{
    public const SUCCESS = 'success';
    public const TRANSIENT = 'transient';
    public const PERMANENT = 'permanent';
    public const AUTH_CONFIG = 'auth_config';

    public function __construct(
        private ?int $statusCode,
        private string $classification,
        string $message,
        private ?string $safeResponseBody = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function connectionFailure(string $detail): self
    {
        return new self(null, self::TRANSIENT, 'API connection failed: '.$detail);
    }

    public static function http(int $statusCode, string $body): self
    {
        $safeBody = self::sanitizeBody($body);
        $message = 'API returned HTTP '.$statusCode.'.';
        $summary = self::messageFromBody($safeBody);

        if ($summary !== '') {
            $message .= ' '.$summary;
        }

        return new self($statusCode, self::classifyStatus($statusCode), $message, $safeBody);
    }

    public static function classifyStatus(int $statusCode): string
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return self::SUCCESS;
        }

        if (in_array($statusCode, [401, 403], true)) {
            return self::AUTH_CONFIG;
        }

        if (in_array($statusCode, [400, 422], true)) {
            return self::PERMANENT;
        }

        if ($statusCode === 429 || $statusCode >= 500) {
            return self::TRANSIENT;
        }

        return self::PERMANENT;
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function classification(): string
    {
        return $this->classification;
    }

    public function isRetryable(): bool
    {
        return $this->classification === self::TRANSIENT;
    }

    public function isPermanentPayloadFailure(): bool
    {
        return $this->classification === self::PERMANENT;
    }

    public function isAuthOrConfigFailure(): bool
    {
        return $this->classification === self::AUTH_CONFIG;
    }

    public function safeResponseBody(): ?string
    {
        return $this->safeResponseBody;
    }

    private static function sanitizeBody(string $body): string
    {
        $body = preg_replace('/Bearer\s+\S+/i', 'Bearer [REDACTED]', $body) ?? $body;
        $body = preg_replace('/("?(?:token|api_token|authorization|password|secret)"?\s*[:=]\s*)("[^"]+"|[^\s,}]+)/i', '$1[REDACTED]', $body) ?? $body;
        $body = trim($body);

        if (strlen($body) > 1000) {
            return substr($body, 0, 1000).'... [truncated]';
        }

        return $body;
    }

    private static function messageFromBody(string $body): string
    {
        $decoded = json_decode($body, true);

        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            return self::truncate($decoded['message'], 300);
        }

        return self::truncate($body, 300);
    }

    private static function truncate(string $value, int $limit): string
    {
        $value = trim($value);

        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit).'...';
    }
}
