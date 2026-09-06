# Google Drive Setup

Follow these steps to configure Google Drive access for your Laravel backup package.

## 1. Create or Select a Google Cloud Project

1. Go to [console.cloud.google.com](https://console.cloud.google.com/).
2. Create a new project or select an existing one.

## 2. Enable the Google Drive API

1. In the left menu, go to **APIs & Services → Library**.
2. Search for **Google Drive API** and click **Enable**.

## 3. Configure the OAuth Consent Screen

1. Go to **APIs & Services → OAuth consent screen**.
2. Select **External** (or **Internal** for G Suite organisations).
3. Fill in the required fields (App name, support email).
4. Add the scope `https://www.googleapis.com/auth/drive`.
5. Add your email address as a test user.
6. Save and continue.

## 4. Create OAuth 2.0 Credentials

1. Go to **APIs & Services → Credentials → Create Credentials → OAuth client ID**.
2. Choose **Desktop app** as the application type.
3. Note the **Client ID** and **Client Secret**.

## 5. Obtain a Refresh Token

Run the following script locally (replace placeholders with your values):

```php
<?php
require 'vendor/autoload.php';

$client = new Google\Client();
$client->setClientId('YOUR_CLIENT_ID');
$client->setClientSecret('YOUR_CLIENT_SECRET');
$client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
$client->addScope(Google\Service\Drive::DRIVE);
$client->setAccessType('offline');
$client->setPrompt('consent');

echo "Visit this URL:\n" . $client->createAuthUrl() . "\n";
echo "Enter the authorization code: ";
$code = trim(fgets(STDIN));

$token = $client->fetchAccessTokenWithAuthCode($code);
echo "\nRefresh Token: " . $token['refresh_token'] . "\n";
```

Copy the printed **refresh token** — this is what you'll store in `.env`.

## 6. Configure Your `.env`

```env
GOOGLE_DRIVE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_DRIVE_CLIENT_SECRET=your-client-secret
GOOGLE_DRIVE_REFRESH_TOKEN=your-refresh-token
GOOGLE_DRIVE_BACKUP_FOLDER_ID=        # Leave blank to auto-create "Laravel Backups"
GOOGLE_DRIVE_BACKUP_FOLDER_NAME="Laravel Backups"
```

> **Never commit credentials to version control.**

## 7. Test Connectivity

```bash
php artisan backup:google-drive:test
```

You should see:
```
✓ OAuth credentials found in configuration.
✓ Authenticated successfully as [your@email.com].
✓ Target backup folder resolved [ID: xxxxxxxxxx].
All connectivity and permission checks passed successfully!
```

## Troubleshooting

| Problem | Cause | Fix |
|---------|-------|-----|
| `invalid_grant` | Refresh token expired or revoked | Re-run the token generation script |
| `403 Forbidden` | Drive API not enabled | Enable Google Drive API in Cloud Console |
| Folder not found | Wrong folder ID | Check `GOOGLE_DRIVE_BACKUP_FOLDER_ID` or leave blank to auto-create |
| `insufficient_scope` | Missing Drive scope | Re-generate token with correct scope |

