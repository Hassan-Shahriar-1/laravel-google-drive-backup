# Contributing

Thank you for considering contributing to `hassan-shahriar-1/laravel-google-drive-backup`!

## Process

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/my-feature`.
3. Write your changes with tests.
4. Ensure all tests pass: `vendor/bin/phpunit`.
5. Ensure static analysis passes: `vendor/bin/phpstan analyse --level=8 src/`.
6. Open a pull request against `main`.

## Code Style

- Follow PSR-12.
- Use `declare(strict_types=1)` in all PHP files.
- Write tests for every new feature or bug fix.

## Security

Do **not** include credentials, tokens, or secrets in commits or PRs.
Please see [SECURITY.md](SECURITY.md) for reporting security vulnerabilities.
