<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\GoogleDrive;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveAuthenticationException;
use Throwable;

class GoogleDriveClient
{
    protected ?GoogleClient $client = null;
    protected ?GoogleDriveService $driveService = null;

    public function __construct(
        protected array $config = []
    ) {
    }

    /**
     * Get or initialize the configured Google API Client.
     *
     * @throws GoogleDriveAuthenticationException
     */
    public function getClient(): GoogleClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $clientId = (string) ($this->config['client_id'] ?? '');
        $clientSecret = (string) ($this->config['client_secret'] ?? '');
        $refreshToken = (string) ($this->config['refresh_token'] ?? '');

        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            throw new GoogleDriveAuthenticationException(
                'Missing Google Drive OAuth credentials. Please configure GOOGLE_DRIVE_CLIENT_ID, GOOGLE_DRIVE_CLIENT_SECRET, and GOOGLE_DRIVE_REFRESH_TOKEN.'
            );
        }

        try {
            $client = new GoogleClient();
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->addScope(GoogleDriveService::DRIVE);
            $client->setAccessType('offline');

            // Refresh access token using the refresh token
            $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($token['error'])) {
                $errorMsg = is_string($token['error_description'] ?? null)
                    ? $token['error_description']
                    : (string) $token['error'];

                throw new GoogleDriveAuthenticationException('Failed to authenticate with Google Drive: ' . $errorMsg);
            }

            $this->client = $client;
            return $this->client;
        } catch (GoogleDriveAuthenticationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GoogleDriveAuthenticationException('Google OAuth authentication error: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Set a custom Google client instance (useful for testing and mocking).
     */
    public function setClient(GoogleClient $client): self
    {
        $this->client = $client;
        $this->driveService = new GoogleDriveService($client);
        return $this;
    }

    /**
     * Get or initialize the Google Drive service instance.
     */
    public function getDriveService(): GoogleDriveService
    {
        if ($this->driveService !== null) {
            return $this->driveService;
        }

        $this->driveService = new GoogleDriveService($this->getClient());
        return $this->driveService;
    }

    /**
     * Test Google Drive connection and permissions.
     *
     * @return array{
     *     authenticated: bool,
     *     email: ?string,
     *     quota_usage: ?int,
     *     quota_limit: ?int,
     *     error: ?string
     * }
     */
    public function testConnection(): array
    {
        try {
            $service = $this->getDriveService();
            $about = $service->about->get(['fields' => 'user,storageQuota']);

            return [
                'authenticated' => true,
                'email' => $about->getUser()?->getEmailAddress(),
                'quota_usage' => (int) $about->getStorageQuota()?->getUsage(),
                'quota_limit' => (int) $about->getStorageQuota()?->getLimit(),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'authenticated' => false,
                'email' => null,
                'quota_usage' => null,
                'quota_limit' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
