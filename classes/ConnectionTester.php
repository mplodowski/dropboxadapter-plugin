<?php

namespace Renatio\DropboxAdapter\Classes;

use GuzzleHttp\Client as GuzzleClient;
use InvalidArgumentException;
use Spatie\Dropbox\Client;
use Spatie\Dropbox\Exceptions\BadRequest;
use Throwable;

/**
 * Verifies that the configured credentials can actually reach Dropbox.
 *
 * Credentials are checked against the account endpoint rather than a disk write, so a
 * successful test says the account is reachable without leaving files behind.
 */
class ConnectionTester
{
    /**
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string}
     */
    public function test(array $config): array
    {
        try {
            $account = $this->client($config)->getAccountInfo();
        } catch (InvalidArgumentException $e) {
            return $this->failure($e->getMessage());
        } catch (BadRequest $e) {
            return $this->failure($this->describeBadRequest($e));
        } catch (Throwable $e) {
            return $this->failure($e->getMessage());
        }

        return [
            'success' => true,
            'message' => trans('renatio.dropboxadapter::lang.settings.connection_ok', [
                'account' => $this->accountName($account),
            ]),
        ];
    }

    /**
     * Tests the credentials stored on an existing filesystem disk.
     *
     * @return array{success: bool, message: string}
     */
    public function testDisk(string $disk): array
    {
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config)) {
            return $this->failure(trans('renatio.dropboxadapter::lang.settings.disk_missing', ['disk' => $disk]));
        }

        return $this->test($config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function client(array $config): Client
    {
        return new Client(
            (new TokenProviderFactory)->make($config),
            new GuzzleClient(['handler' => RetryHandler::stack()]),
        );
    }

    /**
     * @param  array<string, mixed>  $account
     */
    protected function accountName(array $account): string
    {
        return $account['name']['display_name']
            ?? $account['email']
            ?? trans('renatio.dropboxadapter::lang.settings.unknown_account');
    }

    protected function describeBadRequest(BadRequest $exception): string
    {
        return $exception->getMessage() !== ''
            ? $exception->getMessage()
            : trans('renatio.dropboxadapter::lang.settings.invalid_credentials');
    }

    /**
     * @return array{success: bool, message: string}
     */
    protected function failure(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }
}
