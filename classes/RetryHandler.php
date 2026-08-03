<?php

namespace Renatio\DropboxAdapter\Classes;

use Closure;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RetryMiddleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds a Guzzle handler stack that retries throttled and transient Dropbox failures.
 *
 * Dropbox answers rate limited requests with 429 and a Retry-After header stating how
 * long to wait. Honouring that header avoids hammering the API during a backup, while
 * connection errors and 5xx responses fall back to exponential backoff.
 */
class RetryHandler
{
    protected const MAX_RETRIES = 3;

    /**
     * Base delay in milliseconds, doubled on each attempt.
     */
    protected const BACKOFF = 1000;

    /**
     * Upper bound in seconds for a server supplied Retry-After value.
     */
    protected const MAX_RETRY_AFTER = 60;

    public static function stack(?callable $handler = null): HandlerStack
    {
        $stack = $handler ? HandlerStack::create($handler) : HandlerStack::create();

        $stack->push(self::middleware(), 'dropbox_retry');

        return $stack;
    }

    protected static function middleware(): Closure
    {
        return static fn (callable $handler): RetryMiddleware => new RetryMiddleware(
            self::decider(),
            $handler,
            self::delay(),
        );
    }

    protected static function decider(): Closure
    {
        return static function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?TransferException $exception = null,
        ): bool {
            if ($retries >= self::MAX_RETRIES) {
                return false;
            }

            if ($exception instanceof ConnectException) {
                return true;
            }

            return $response !== null && self::isRetryable($response->getStatusCode());
        };
    }

    protected static function delay(): Closure
    {
        return static function (int $retries, ?ResponseInterface $response = null): int {
            $retryAfter = self::retryAfterMilliseconds($response);

            return $retryAfter ?? (int) pow(2, max(0, $retries - 1)) * self::BACKOFF;
        };
    }

    protected static function isRetryable(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    /**
     * Reads Retry-After, which Dropbox sends as seconds but HTTP also allows as a date.
     */
    protected static function retryAfterMilliseconds(?ResponseInterface $response): ?int
    {
        $header = $response?->getHeaderLine('Retry-After');

        if ($header === null || $header === '') {
            return null;
        }

        $seconds = is_numeric($header)
            ? (int) $header
            : self::secondsUntil($header);

        if ($seconds === null || $seconds < 0) {
            return null;
        }

        return min($seconds, self::MAX_RETRY_AFTER) * 1000;
    }

    protected static function secondsUntil(string $httpDate): ?int
    {
        $timestamp = strtotime($httpDate);

        if ($timestamp === false) {
            return null;
        }

        return $timestamp - time();
    }
}
