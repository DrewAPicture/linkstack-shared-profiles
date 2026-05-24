# Shared Profiles for LinkStack

A Laravel package that adds shared profile management to [LinkStack](https://github.com/LinkStackOrg/LinkStack) — API link submission, platform-based authentication for shared managers, and a moderation queue — without modifying any core LinkStack files.

## Requirements

- PHP ^8.2
- Laravel 10

## Installation

```bash
composer require werdswords/linkstack-shared-profiles
```

Auto-discovery registers the service provider. No changes to `config/app.php` are needed.

## Configuration

```bash
php artisan vendor:publish --tag=linkstack-shared-profiles
```

| Key | Default | Description |
|---|---|---|
| `auto_approve` | `false` | When `true`, submitted links are published immediately. Can be overridden per-profile via `users.auto_approve`. |

## Documentation

- [API Link Submission](docs/01-api-link-submission.md)
- [Platform Authentication](docs/02-platform-authentication.md)
- [Moderation Queue](docs/03-moderation-queue.md)
- [Adding a Provider](docs/04-adding-a-provider.md)

## License

MIT
