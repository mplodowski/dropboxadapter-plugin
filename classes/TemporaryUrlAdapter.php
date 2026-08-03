<?php

namespace Renatio\DropboxAdapter\Classes;

use DateTimeInterface;
use Spatie\Dropbox\Client;
use Spatie\FlysystemDropbox\DropboxAdapter;
use Throwable;

/**
 * Adds temporary link support to the Dropbox adapter.
 *
 * Dropbox has no permanent public URL scheme, but it can issue links that stay valid for
 * about four hours. October detects support by looking for getTemporaryUrl() on the
 * adapter itself, so the method has to live here rather than only on the disk.
 */
class TemporaryUrlAdapter extends DropboxAdapter
{
    protected string $rootPrefix;

    public function __construct(Client $client, string $prefix = '')
    {
        parent::__construct($client, $prefix);

        $this->rootPrefix = rtrim($prefix, '/');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function getTemporaryUrl(string $path, DateTimeInterface|int|null $expiration = null, array $options = []): ?string
    {
        try {
            return $this->getClient()->getTemporaryLink($this->rootPrefix . '/' . ltrim($path, '/'));
        } catch (Throwable) {
            return null;
        }
    }
}
