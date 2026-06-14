# SymPress TwigBundle

`sympress/twig-bundle` integrates Twig into the SymPress kernel while delegating the
core feature set to Symfony's `symfony/twig-bundle`.

## Installation

```bash
composer require sympress/twig-bundle
```

The bundle is discovered by the SymPress kernel through the package metadata in
`composer.json`. It requires PHP 8.5, SymPress Kernel, SymPress FrameworkBundle,
Symfony TwigBundle, and Twig 3.

## Features

- Symfony TwigBundle compiler passes and service definitions
- Symfony Twig configuration under the `twig` extension alias
- Template lookup in project `templates/`, bundle `Resources/views`, and bundle `templates`
- Autoconfiguration for Twig extensions, loaders, runtimes, and Twig attributes
- Injectable `TemplateRendererInterface`
- Optional SymPress API for service-backed Twig globals

## Configuration

Twig configuration uses Symfony's standard `twig` extension alias:

```yaml
twig:
    default_path: '%kernel.project_dir%/templates'
    strict_variables: '%kernel.debug%'
```

The bundle loads its own service wiring from `Resources/config/services.yaml` and
keeps Symfony TwigBundle service definitions intact. Project services can keep using
normal Twig extension, loader, runtime, and attribute APIs.

## Twig Extensions

Services implementing Twig's extension, loader, or runtime interfaces are
autoconfigured the same way as in Symfony:

```php
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ShopTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('shop_name', fn (): string => 'SymPress'),
        ];
    }
}
```

Twig attributes from `twig/twig` are supported as well:

```php
use Twig\Attribute\AsTwigFunction;

final class ShopTwigRuntime
{
    #[AsTwigFunction('shop_name')]
    public function shopName(): string
    {
        return 'SymPress';
    }
}
```

## Twig Globals

Services can provide several globals by implementing `GlobalProviderInterface`:

```php
use SymPress\TwigBundle\Extension\GlobalProviderInterface;

final class AppGlobals implements GlobalProviderInterface
{
    public function getGlobals(): iterable
    {
        return [
            'app_name' => 'SymPress',
        ];
    }
}
```

For one service-backed global, tag a service class with `AsTwigGlobal`:

```php
use SymPress\TwigBundle\Attribute\AsTwigGlobal;

#[AsTwigGlobal('current_account')]
final class CurrentAccount
{
}
```

## Rendering

Inject `SymPress\TwigBundle\Renderer\TemplateRendererInterface` when a service
needs view rendering without extending a controller base class.

```php
use SymPress\TwigBundle\Renderer\TemplateRendererInterface;

final readonly class PageRenderer
{
    public function __construct(
        private TemplateRendererInterface $templates,
    ) {
    }

    public function render(): string
    {
        return $this->templates->render('page.html.twig', [
            'title' => 'Hello SymPress',
        ]);
    }
}
```

Controllers can use `TwigResponseTrait` when they need a Symfony `Response` from
a Twig template without coupling to a concrete renderer implementation.

## Quality Checks

```bash
composer qa
```

The QA command runs PHPCS, PHPStan, and PHPUnit with the package configuration used
by the GitHub Actions workflow.
