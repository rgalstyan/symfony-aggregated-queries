# Contributing to Symfony Aggregated Queries

Thank you for considering contributing! 🎉

## Development Setup
```bash
git clone https://github.com/rgalstyan/symfony-aggregated-queries.git
cd symfony-aggregated-queries
composer install
```

## Running Tests
```bash
# All tests
composer test

# With coverage
composer test:coverage

# Only unit tests
vendor/bin/phpunit tests/Unit

# Only functional tests
vendor/bin/phpunit tests/Functional
```

## Code Quality
```bash
# Static analysis (PHPStan level 9)
composer phpstan

# Code style check
composer cs:check

# Auto-fix code style
composer cs:fix
```

## Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`composer test`)
5. Ensure PHPStan passes (`composer phpstan`)
6. Ensure code style is correct (`composer cs:check`)
7. Commit your changes with clear message
8. Push to your fork
9. Open a Pull Request

## Coding Standards

- PSR-12 code style
- `declare(strict_types=1)` in every file
- Full type hints (params + returns)
- PHPStan level 9 compliant
- No `mixed` types
- Classes `final` by default
- Constructor property promotion

## Testing Standards

- Write unit tests for new features
- Write functional tests for integration
- Maintain >80% code coverage
- Test both MySQL and PostgreSQL if applicable

## Questions?

Open an issue or discussion on GitHub!