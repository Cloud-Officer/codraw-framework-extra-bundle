# Code Review — codraw/framework-extra-bundle

Reviewed: 2026-07-20. Scope: this package's own code only (`DrawFrameworkExtraBundle.php`, `DependencyInjection/`, `Resources/`, `Tests/`, `composer.json`, docs).

## Fixes applied (2026-07-20)

- **composer.json:** PHP version constraint changed from unbounded `>=8.5` to `^8.5` (version-compatibility debt: prevents a future PHP 9 from installing against this package; no effect on any currently existing PHP version).
- **composer.json** — added a `suggest` section listing the 15 optional codraw packages whose `*Integration` classes are referenced in `DrawFrameworkExtraExtension::provideExtensionClasses()` (`codraw/application`, `codraw/aws-tool-kit`, `codraw/console`, `codraw/cron-job`, `codraw/doctrine-extra`, `codraw/entity-migrator`, `codraw/log`, `codraw/mailer`, `codraw/messenger`, `codraw/open-api`, `codraw/process`, `codraw/security`, `codraw/tester`, `codraw/validator`, `codraw/workflow`), matching the suggest style used by sibling packages. Addresses Medium finding 2. `composer validate --no-check-publish` passes.
- **README.md** — corrected the claim that "Some sections are enabled by default"; all integration sections use `canBeEnabled()` and are disabled by default, and the README now says so. Addresses Medium finding 1.
- **DrawFrameworkExtraBundle.php** — replaced the production-compiled-out `\assert()` in `build()` with an explicit `instanceof` check that throws a descriptive `\RuntimeException`, so a null/foreign extension no longer produces an untyped fatal `Error`. Addresses Low finding 6.
- **doc/OPEN_API.md** — fixed the syntactically invalid YAML example (unescaped quotes + "descriptoin" typo in the `description` line) and the missing closing `]` on the `#[OpenApi\Operation(...)]` attribute example. Partially addresses Low finding 7; the outdated `Sensio\...\ConfigurationInterface` / `Draw\Bundle\OpenApiBundle\Response\Serialization` content still needs a rewrite against the current `codraw/open-api` API.

Open items deliberately not touched: unused `ext-json` and `codraw/core` requirements (Low findings 1-2 — not removed because consumers may rely on them transitively), duplicate doc directories (Low finding 5 — content/compliance decision), dist archive contents / `.gitattributes` export-ignore (Low finding 6 — would change what consumers receive; some codraw packages autoload sibling `Tests/` classes).

### Validation pass (2026-07-20)

- `composer install --optimize-autoloader --no-interaction --prefer-dist --no-scripts` — OK (165 packages).
- `vendor/bin/phpunit` (with `DATABASE_URL` set) — OK: 6 tests, 95 assertions, 1 skipped (the skip is by design: `DrawFrameworkExtraExtensionTest::provideServiceDefinitionCases()` yields `[null]`, which the shared `ExtensionTestCase` skips; identical result without the applied fixes).
- PHPStan (`phpstan.dist.neon`) — 3 errors, all pre-existing (verified identical with the fixes stashed) and all in files not touched by the fixes: `Configuration.php:26/:36` (`method.notFound` on `NodeDefinition::children()`/`append()`, a generic-narrowing limitation of the config builder API) and `DrawFrameworkExtraExtension.php:47` (`method.unused` false positive — `provideExtensionClasses()` is invoked by `ExtendableExtensionTrait::registerDefaultIntegrations()`). No new errors from the fixes; no stale baseline entries (baseline is empty).
- `markdownlint-cli2` — 0 errors; no files needed changes.
- No code or test changes were required in this pass; the only edit was correcting this file's own inconsistency (finding 3 was listed both as fixed and as "deliberately not touched" — the `^8.5` constraint fix from the list above was in fact applied, so finding 3 is now marked FIXED).

Behavior of the integration classes this package wires (living in sibling `codraw/*` packages) was consulted only to verify correctness of how this package uses them.

## Overall assessment

This is a deliberately thin "glue" bundle: it exposes a single Symfony bundle + extension pair that discovers optional `*Integration` classes from other codraw packages (via `class_exists()` guards in `ExtendableExtensionTrait::registerDefaultIntegrations()`), builds a merged configuration tree under `draw_framework_extra`, and delegates load/prepend/build to those integrations. The design is sound: optional components degrade gracefully when not installed, the config tree is composed dynamically, and there is essentially no logic in this package that could contain an injection, deserialization, or traversal flaw. The real code surface is ~150 lines and is correct. The issues found are all packaging/metadata/documentation-level: an unbounded and unusual PHP constraint, dependencies declared but unused (`ext-json`, arguably `codraw/core`), no `suggest` section for the many optional integration packages, a badly outdated `doc/OPEN_API.md`, and a README claim about default-enabled sections that does not match the actual `canBeEnabled()` (default-disabled) behavior in `Configuration.php`.

## Findings

### Critical

None.

### High

None.

### Medium

1. **[FIXED]** **README contradicts actual configuration defaults** — `README.md:7` says "Some sections are enabled by default if the corresponding draw component is available." In `DependencyInjection/Configuration.php:31-33`, every integration section is created with `->canBeEnabled()`, which makes **all** sections disabled by default (`enabled: false`); no integration flips the top-level default (verified across sibling packages: `canBeDisabled()` only appears on sub-nodes, e.g. `codraw-open-api/DependencyInjection/OpenApiIntegration.php:463`). A user following the README will believe installing a component activates it; it does not. Either the README or the tree defaults are wrong — as documentation of a config surface this is a real usability defect, not a style nit.

2. **[FIXED]** **Optional integration packages are undiscoverable — no `suggest` section** — `composer.json` hard-requires only `codraw/core` and `codraw/dependency-injection`, while `DependencyInjection/DrawFrameworkExtraExtension.php:50-68` references 19 integration classes from ~15 optional packages (`codraw/application`, `codraw/console`, `codraw/mailer`, `codraw/messenger`, `codraw/open-api`, `codraw/security`, …), all of which are only in `require-dev`. The `class_exists()` guard makes this safe at runtime, but Composer's standard mechanism for "this package lights up if you also install X" is `suggest`, and its absence means consumers get zero signal about what the bundle can integrate. It also means a silent failure mode: a typo'd or renamed integration class is skipped without any warning (see `ExtendableExtensionTrait::registerDefaultIntegrations()` in codraw-dependency-injection, lines 28-31).

3. **[FIXED]** **Unbounded PHP requirement `>=8.5`** — `composer.json:19`. Besides being an unusually aggressive floor (8.5 shipped Nov 2025; this excludes every 8.1–8.4 runtime while the Symfony deps are pinned to the older 6.4 LTS line, an odd pairing), the constraint has no upper bound, so a future PHP 9.0 with BC breaks will satisfy it. `^8.5` (or `>=8.5 <9.0`) is the conventional, safer form. This pattern is repeated across sibling packages, so it may be a monorepo-wide decision, but it is worth fixing at the source.

### Low

1. **`ext-json` is required but never used** — `composer.json:20`. `grep` finds no `json_*` call or JSON handling anywhere in the package's PHP code. Dead platform requirement; can needlessly block installation on stripped-down runtimes.

2. **`codraw/core` is required but not referenced** — `composer.json:21`. No class in this package imports `Draw\Component\Core\*`. If it is only needed transitively it should come via the packages that use it (`codraw/dependency-injection` does not require it either); if it is intentionally a base requirement for the ecosystem, a comment/README note would prevent future "why is this here" churn.

3. **[FIXED]** **`assert()`-guarded type assumption in `build()`** — `DrawFrameworkExtraBundle.php:14-16`. `getContainerExtension()` is typed `?ExtensionInterface`; the `\assert($containerExtension instanceof DrawFrameworkExtraExtension)` is compiled out in production (`zend.assertions=-1`), after which a null or foreign extension (possible if a subclass overrides `createContainerExtension()`/`getContainerExtension()`) produces an untyped fatal `Error` on `getIntegrations()` instead of a clear exception. Container build happens at deploy/warm-up time so impact is low, but an explicit `instanceof` check with a descriptive exception costs nothing.

4. **[PARTIALLY FIXED — broken examples corrected; outdated namespace content remains]** **`doc/OPEN_API.md` is significantly out of date and contains broken examples** — it references the abandoned `Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationInterface` (line 143) and the legacy `Draw\Bundle\OpenApiBundle\Response\Serialization` namespace (lines 119, 129, 153) which do not exist in the current `codraw/open-api` component; line 55's YAML example is syntactically invalid (`description: 'This is the descriptoin of the 'Acme API'` — unescaped quotes plus a typo); line 74's attribute example is missing its closing `]` (`#[OpenApi\Operation(operationId: 'default', tags: ["Acme"])`). Misleading docs on a config-heavy bundle actively cost users time.

5. **Duplicate documentation directories** — both `doc/` (linked from README) and `docs/` (contains only `soup.md`, an empty SOUP table header) exist. Consolidate; the empty `docs/soup.md` is either a compliance placeholder that should be filled or removed.

6. **`Tests/` ships in the dist package** — the PSR-4 root mapping `"Draw\\Bundle\\FrameworkExtraBundle\\": ""` (`composer.json:60-62`) plus the absence of a `.gitattributes` with `export-ignore` means `Tests/`, `phpstan*`, `trivy.yaml`, etc. are included in release archives and the test classes are autoloadable in production. Harmless but avoidable weight; most Symfony bundles export-ignore these.

## Strengths

- **Correct optional-dependency pattern**: every integration class referenced in `DrawFrameworkExtraExtension::provideExtensionClasses()` is guarded by `class_exists()` (in the shared trait) before instantiation, so the bundle genuinely works with any subset of codraw components installed. The constructor also accepts an explicit `$integrations` array (`DrawFrameworkExtraExtension.php:38-45`), which makes the extension unit-testable and lets applications compose their own integration list.
- **Clean separation of concerns**: `Configuration` composes per-integration subtrees via `ArrayNodeDefinition::canBeEnabled()` + `append()` (`Configuration.php:30-37`), keeping all real DI logic in the component packages. This package stays a stable, near-zero-maintenance shell.
- **`build()` correctly forwards compiler-pass registration** to integrations implementing `ContainerBuilderIntegrationInterface` only, without instantiating anything else (`DrawFrameworkExtraBundle.php:18-22`).
- **Prepend support** is wired through `PrependExtensionInterface` → `prependIntegrations()` with the correct alias `draw_framework_extra` (`DrawFrameworkExtraExtension.php:84-87`).
- **Empty phpstan baseline** (`phpstan-baseline.neon` has `ignoreErrors: []`) at level 5 across the whole package root — no suppressed static-analysis debt.
- Routing resource files (`Resources/config/routing-*.yaml`) are minimal, opt-in (must be imported by the host app), and correctly restrict methods where it matters (`GET` on the messenger click route and ping route).

## Test coverage

Coverage is proportionate to the package's size and reasonably good for what this package owns:

- **`Tests/DrawFrameworkExtraBundleTest.php`** is the strongest test: it mocks `ContainerBuilder` and asserts the exact sequence, type, and priority of all 10 compiler passes registered through the integrations during `build()`, plus the two security authenticator factories added to the `security` extension. This pins down the observable contract of `build()` well (though it is inherently coupled to which dev dependencies are installed, so it doubles as an implicit "all integrations discovered" test).
- **`Tests/DependencyInjection/ConfigurationTest.php`** only exercises `new Configuration()` with an *empty* integration list — it verifies `symfony_console_path` defaulting and unknown-key rejection, but the dynamic integration-subtree composition (the class's main job, lines 30-37) is never tested here with even one stub integration. Per-integration config trees are presumably tested in their own packages, but the composition/`append()` path in this class is untested at the boundary (e.g., two integrations with the same section name would silently collide).
- **`Tests/DependencyInjection/DrawFrameworkExtraExtensionTest.php`** relies on the shared `ExtensionTestCase` with an empty config and `provideServiceDefinitionCases` yielding `[null]` — effectively a smoke test that `load()` does not blow up; it does not assert the `draw.symfony_console_path` parameter is set, and `prepend()` has no direct test at all.

**Untested areas**: `prepend()`, the explicit-integrations constructor path (`new DrawFrameworkExtraExtension([$integration])`), `Configuration` with a non-empty integration list, and the parameter set in `load()`. None of these are high-risk, but the first two would be cheap to cover.

**Grade: B** — the code itself is clean and correct; the deductions are for documentation drift, dependency-metadata hygiene, and thin direct tests of the extension's own load/prepend behavior.
