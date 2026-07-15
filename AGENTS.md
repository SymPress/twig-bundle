# SymPress Twig Bundle

## Scope and entry points

- This package is a thin adapter over Symfony TwigBundle; read `CONTRIBUTING.md` before adding behavior.
- `src/TwigBundle.php` registers SymPress autoconfiguration and the global compiler pass.
- `src/DependencyInjection/TwigExtension.php` delegates to Symfony and supplies kernel parameters.
- `Resources/config/services.yaml` wires globals and `TemplateRendererInterface`.

## Verification

- Fast behavior check: `composer tests`.
- Full required check: `composer qa`.
- Add an integration-style test only when changing template discovery, service wiring, or rendering behavior.

## Invariants

- Delegate standard Twig behavior to `symfony/twig-bundle`; do not copy upstream configuration locally.
- Keep Symfony TwigBundle service definitions intact.
- Preserve `AsTwigGlobal` autoconfiguration and service-backed global resolution.
- `TwigResponseTrait` must fail clearly when no renderer is available and return the renderer response unchanged otherwise.

## Cross-repository impact and done

- This package depends on `sympress/framework-bundle` and `sympress/kernel`; `extra.kernel` exposes `TwigBundle`.
- A change is done when the focused adapter test and `composer qa` pass and upstream delegation remains explicit.
