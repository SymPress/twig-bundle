# Contributing

Thanks for taking the time to improve SymPress Twig Bundle.

## Local Setup

```bash
composer install
composer qa
```

The package uses PHP 8.5, Twig, Symfony TwigBundle, Symfony DependencyInjection,
PHPUnit, PHPStan, and PHPCS with the SymPress coding standards.

## Pull Requests

- Keep pull requests focused on one behavior or documentation change.
- Add or update tests for compiler-pass, service-container, renderer, or Twig integration changes.
- Run the available checks before opening a pull request.
- Use Conventional Commits for commit messages, for example
  `feat(twig-bundle): add template renderer service`.

## Coding Guidelines

- Delegate Symfony Twig behavior to `symfony/twig-bundle` unless SymPress needs package-specific integration.
- Keep service-container behavior explicit and covered by unit tests.
- Prefer small extension points, such as tagged services or interfaces, over runtime service lookups.
- Document new Twig-facing APIs in the README when they are intended for package consumers.
