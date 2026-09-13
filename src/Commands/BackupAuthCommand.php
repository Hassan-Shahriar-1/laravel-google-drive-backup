<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Illuminate\Console\Command;
use Throwable;

class BackupAuthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:google-drive:refresh-token
                            {--client-id=     : Google OAuth Client ID}
                            {--client-secret= : Google OAuth Client Secret}
                            {--redirect=      : OAuth Redirect URI (default: urn:ietf:wg:oauth:2.0:oob)}';

    /**
     * Alternative aliases for the command.
     *
     * @var array<int, string>
     */
    protected $aliases = [
        'backup:google-drive:token',
    ];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Google Drive OAuth 2.0 refresh token interactively.';

    public function handle(): int
    {
        $this->newLine();
        $this->info('╔═════════════════════════════════════════════════════════════════╗');
        $this->info('║       Google Drive OAuth 2.0 Refresh Token Generator            ║');
        $this->info('╚═════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $config = (array) config('google-drive-backup.google', []);

        // 1. Resolve Client ID
        $clientId = $this->option('client-id')
            ?: ($config['client_id'] ?? null)
            ?: env('GOOGLE_DRIVE_CLIENT_ID');

        if (!empty($clientId)) {
            $masked = strlen((string) $clientId) > 12 ? substr((string) $clientId, 0, 8) . '...' . substr((string) $clientId, -4) : (string) $clientId;
            $this->line("   ✓ Found Client ID from .env / config: <comment>{$masked}</comment>");
        } else {
            $clientId = $this->ask('Enter your Google OAuth Client ID');
        }

        if (empty($clientId)) {
            $this->error('Client ID is required.');
            return Command::FAILURE;
        }

        // 2. Resolve Client Secret
        $clientSecret = $this->option('client-secret')
            ?: ($config['client_secret'] ?? null)
            ?: env('GOOGLE_DRIVE_CLIENT_SECRET');

        if (!empty($clientSecret)) {
            $this->line("   ✓ Found Client Secret from .env / config: <comment>[REDACTED]</comment>");
        } else {
            $clientSecret = $this->secret('Enter your Google OAuth Client Secret');
        }

        if (empty($clientSecret)) {
            $this->error('Client Secret is required.');
            return Command::FAILURE;
        }

        $redirectUri = (string) ($this->option('redirect') ?: 'urn:ietf:wg:oauth:2.0:oob');

        try {
            $client = new GoogleClient();
            $client->setClientId((string) $clientId);
            $client->setClientSecret((string) $clientSecret);
            $client->setRedirectUri($redirectUri);
            $client->addScope(GoogleDriveService::DRIVE);
            $client->setAccessType('offline');
            $client->setPrompt('consent');
            $client->setIncludeGrantedScopes(true);

            $authUrl = $client->createAuthUrl();

            $this->info('Step 1: Authorize the application');
            $this->line('Open the following URL in your web browser:');
            $this->newLine();
            $this->line("<comment>{$authUrl}</comment>");
            $this->newLine();

            $this->info('Step 2: Enter Authorization Code');
            $this->line('Sign in with your Google Account, allow access, and copy the code displayed on screen.');
            $authCode = $this->ask('Paste the authorization code here');

            if (empty($authCode)) {
                $this->error('Authorization code cannot be empty.');
                return Command::FAILURE;
            }

            $this->newLine();
            $this->comment('Exchanging authorization code for tokens...');

            $token = $client->fetchAccessTokenWithAuthCode(trim((string) $authCode));

            if (isset($token['error'])) {
                $errorMsg = is_string($token['error_description'] ?? null)
                    ? $token['error_description']
                    : (string) $token['error'];

                $this->error("Failed to obtain refresh token: {$errorMsg}");
                return Command::FAILURE;
            }

            $refreshToken = $token['refresh_token'] ?? null;

            if (empty($refreshToken)) {
                $this->warn('No refresh token was returned.');
                $this->line('Google only issues a refresh token the first time you authorize, or when prompt=consent is set.');
                $this->line('Try revoking access for this app in your Google Account Security settings and running this command again.');
                return Command::FAILURE;
            }

            $this->newLine();
            $this->info('✓ Successfully generated Google Drive Refresh Token!');
            $this->newLine();
            $this->line('--------------------------------------------------------------------------------');
            $this->line("<info>{$refreshToken}</info>");
            $this->line('--------------------------------------------------------------------------------');
            $this->newLine();

            // Offer to save to .env
            $this->promptToSaveEnv((string) $clientId, (string) $clientSecret, (string) $refreshToken);

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Error generating token: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Optionally write the credentials into .env
     */
    protected function promptToSaveEnv(string $clientId, string $clientSecret, string $refreshToken): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->line("Add these variables to your <comment>.env</comment> file:");
            $this->line("GOOGLE_DRIVE_CLIENT_ID={$clientId}");
            $this->line("GOOGLE_DRIVE_CLIENT_SECRET={$clientSecret}");
            $this->line("GOOGLE_DRIVE_REFRESH_TOKEN={$refreshToken}");
            return;
        }

        if ($this->confirm('Would you like to save these credentials directly to your .env file?', true)) {
            $this->updateEnvFile($envPath, [
                'GOOGLE_DRIVE_CLIENT_ID' => $clientId,
                'GOOGLE_DRIVE_CLIENT_SECRET' => $clientSecret,
                'GOOGLE_DRIVE_REFRESH_TOKEN' => $refreshToken,
            ]);

            $this->info('✓ Credentials saved to .env successfully!');
        } else {
            $this->line("Add these variables to your <comment>.env</comment> file manually:");
            $this->line("GOOGLE_DRIVE_CLIENT_ID={$clientId}");
            $this->line("GOOGLE_DRIVE_CLIENT_SECRET={$clientSecret}");
            $this->line("GOOGLE_DRIVE_REFRESH_TOKEN={$refreshToken}");
        }
    }

    /**
     * Update or append environment variables in .env file.
     *
     * @param array<string, string> $data
     */
    protected function updateEnvFile(string $envPath, array $data): void
    {
        $content = file_get_contents($envPath) ?: '';

        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*$/m";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "{$key}={$value}", $content) ?? $content;
            } else {
                $content .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $content);
    }
}
