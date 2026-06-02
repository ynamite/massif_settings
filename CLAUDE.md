# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project type

REDAXO 5 addon (PHP ≥ 7.4). No build, no test suite, no linter configured. Installed by placing the directory under `redaxo/src/addons/massif_settings/` of a REDAXO instance and activating it in the backend.

Hard dependency on the sibling addon **[massif](https://github.com/ynamite/redaxo_massif)** (`^1.0.0`). The backend page-rendering classes (`Ynamite\Massif\BE\Package`, `Ynamite\Massif\Be\ConfigForm`, `Ynamite\Massif\Utils\Format`) live there, not in this repo. When tracing how the BE form renders or what field keys are honored, look in the parent addon. A working checkout is typically at `/Users/yvestorres/Repositories/viterex/redaxo-massif/`.

> Note: `composer.json` declares `psr-4` for `lib/`, matching where code actually lives. REDAXO autoloads `lib/` itself; the composer file is for IDE tooling only.

## Architecture

### Source-of-truth layering (since v1.1.0)

Field configuration is no longer in `package.yml`. The runtime field tree is built in this order (later overrides earlier):

1. **Project YAML** — `redaxo/data/addons/massif_settings/fields.yml`. Seeded by `install.php` from the addon's `data/fields.yml` on first activation, then owned by the project (updates never touch it).
2. **PHP `MASSIF_SETTINGS_REGISTER` extension point** — escape hatch for things YAML can't express (callable formatters, resolver-backed placeholders, conditional registration).
3. **Legacy `MASSIF_SETTINGS_CUSTOM_FIELDS`** — translated into `Registry::placeholder()` calls during bootstrap. Kept working for back-compat.

The parent `massif` addon's `ConfigForm::getForm()` is unchanged; it reads `$addon->getProperty('page')['subpages'][$subpage]['fields']`. To make this work without modifying the parent, `Registry::bootstrap()` mutates the addon's `page` property in-place (see `lib/Registry.php` → `syncToAddonPageProperty()`). This is why nothing under `page.subpages` exists in `package.yml` anymore — it's all written in at boot. Future Claudes should not be alarmed.

### Data flow

1. **Definition** — `data/fields.yml` (shipped) seeds → `redaxo/data/addons/massif_settings/fields.yml` (project). Plus `MASSIF_SETTINGS_REGISTER` listeners.
2. **Storage** — Values save to `rex_config` under namespace `massif_settings`, key `<subpage>_<normalized_name>` (e.g. `address_firma`). `rex_string::normalize()` handles spaces/special chars.
3. **Defaults** — Fields with `default:` get written to `rex_config` once during `Registry::bootstrap()` if the key isn't set yet. (Parent's `ConfigForm` silently ignored this; we apply it ourselves.)
4. **Retrieval** — `Utils::getData()` walks `Registry::getSubpages()` → `getFields()` → `getPlaceholders()`, reads `rex_config`, applies `nl2br` and per-field formatters, returns a flat `[key => value]` map. Cached in a static for the request.
5. **Frontend replacement** — `boot.php` registers an `OUTPUT_FILTER` running `Utils::replaceStrings()` over the final HTML. Skip rules: backend pages whose key starts with `massif_settings/`, the content edit form, and any request where `search_it_build_index`/`search_highlighter` are set (search indexing pass).
6. **Formatters** — `phone`, `fax`, `email`, `url` (string keys), or any callable. Produce a wrapped link tag exposed under `{{key_formatted}}` alongside the raw `{{key}}`.

### Registry API (`lib/Registry.php`)

```php
Registry::subpage(string $key, array $config);   // title, icon, perm, priority, active
Registry::field(string $subpage, array $spec);   // see README for spec keys
Registry::placeholder(string $name, array $spec = []);  // value | resolver | package+key
Registry::removeSubpage / removeField / disableField / removePlaceholder
Registry::getSubpages / getFields($subpage) / getPlaceholders
Registry::bootstrap();   // idempotent; called from boot.php
Registry::reset();       // test-only
```

`Registry::field()` normalizes `style_input` → `style` on registration (parent `ConfigForm` only reads `style`).

A `PAGES_PREPARED` listener registered during bootstrap auto-creates BE nav entries for any subpage declared in YAML/registry that isn't already in REDAXO's page tree.

### SEO helpers (`lib/Seo.php`, `lib/YrewriteSeo.php`)

Independent from the field registry. `Seo::getTags()` produces meta tags + `LocalBusiness` / `Organization` JSON-LD using `{{address_*}}` and `{{google_geo_*}}` placeholders. Auto-detects `yrewrite` vs `redaxo_url` (via `\Url\Url::resolveCurrent()`) and emits the appropriate hooks. JSON-LD is suppressed for AJAX requests (`rex::getProperty('req-with')`).

`YrewriteSeo` extends `rex_yrewrite_seo` to add the `YREWRITE_CANONICAL_URL` extension point.

## Conventions when editing

- **Placeholder naming**: `<subpage>_<normalized_field_name>` (e.g. `address_e_mail`). Keep `notice` in `data/fields.yml` in sync with the resulting placeholder when adding/renaming.
- **Output filter skips**: when adding new admin UIs that legitimately render `{{...}}`-like syntax, extend the backend skip list in `boot.php` rather than escaping each occurrence.
- **Adding a built-in formatter**: add a `case` in `Utils::applyFormatter()` and document it in README. Both YAML fields and `Registry::placeholder()` entries route through the same method, including the `is_callable()` branch.
- **Adding new field-spec keys**: if the parent `ConfigForm` reads a new key (check `/Users/yvestorres/Repositories/viterex/redaxo-massif/lib/BE/ConfigForm.php`), pass it through unchanged. If parent ignores it (like `default` and `style_input` historically did), handle it inside `Registry` and document the workaround in `lib/Registry.php`.
- **README is in German.** Match the existing language when updating it.
- **Don't put fields back in `package.yml`.** The `page.subpages` block was deliberately removed; the YAML loader and Registry are now the single configuration path.
