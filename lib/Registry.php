<?php

namespace Ynamite\MassifSettings;

use rex_addon;
use rex_be_controller;
use rex_be_page;
use rex_config;
use rex_extension;
use rex_extension_point;
use rex_file;
use rex_logger;
use rex_string;

/**
 * Central registry for massif_settings subpages, fields and placeholders.
 *
 * Field configuration is driven primarily by
 * redaxo/data/addons/massif_settings/fields.yml (project-owned, seeded on install).
 * The MASSIF_SETTINGS_REGISTER extension point and the public API on this class
 * exist as the escape hatch for cases YAML cannot express (callable formatters,
 * resolver-backed placeholders, conditional registration).
 */
final class Registry
{
    public const ADDON_NAME = 'massif_settings';

    /** @var array<string, array<string,mixed>> keyed by subpage key */
    private static array $subpages = [];

    /** @var array<string, array<string, array<string,mixed>>> [$subpage][$normName] = $spec */
    private static array $fields = [];

    /** @var array<string, array<string,mixed>> keyed by placeholder name */
    private static array $placeholders = [];

    private static bool $bootstrapped = false;
    private static bool $pagesPreparedRegistered = false;

    public static function subpage(string $key, array $config): void
    {
        $existing = self::$subpages[$key] ?? [];
        self::$subpages[$key] = array_replace($existing, $config);
    }

    public static function removeSubpage(string $key): void
    {
        unset(self::$subpages[$key], self::$fields[$key]);
    }

    public static function field(string $subpage, array $spec): void
    {
        if (!isset($spec['name']) || $spec['name'] === '') {
            rex_logger::factory()->warning('massif_settings Registry::field called without name');
            return;
        }
        $spec = self::normalizeFieldSpec($spec);
        $norm = rex_string::normalize($spec['name']);
        $existing = self::$fields[$subpage][$norm] ?? [];
        self::$fields[$subpage][$norm] = array_replace($existing, $spec);
    }

    public static function removeField(string $subpage, string $name): void
    {
        unset(self::$fields[$subpage][rex_string::normalize($name)]);
    }

    public static function disableField(string $subpage, string $name): void
    {
        $norm = rex_string::normalize($name);
        if (isset(self::$fields[$subpage][$norm])) {
            self::$fields[$subpage][$norm]['active'] = false;
        }
    }

    public static function placeholder(string $name, array $spec = []): void
    {
        self::$placeholders[$name] = $spec;
    }

    public static function removePlaceholder(string $name): void
    {
        unset(self::$placeholders[$name]);
    }

    /** @return array<string, array<string,mixed>> sorted by priority, inactive subpages skipped */
    public static function getSubpages(): array
    {
        $out = self::$subpages;
        uasort($out, static fn ($a, $b) => ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100));
        return array_filter($out, static fn ($s) => ($s['active'] ?? true) !== false);
    }

    /** @return list<array<string,mixed>> sorted by priority, inactive fields kept (parent ConfigForm hides them) */
    public static function getFields(string $subpage): array
    {
        $list = array_values(self::$fields[$subpage] ?? []);
        usort($list, static fn ($a, $b) => ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100));
        return $list;
    }

    /** @return array<string, array<string,mixed>> */
    public static function getPlaceholders(): array
    {
        return self::$placeholders;
    }

    /**
     * Idempotent. Loads YAML, fires MASSIF_SETTINGS_REGISTER, merges the legacy EP,
     * applies defaults to rex_config, syncs subpages onto the addon's `page` property,
     * and registers a PAGES_PREPARED listener for late-added subpages.
     */
    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        self::loadYaml();

        rex_extension::registerPoint(new rex_extension_point('MASSIF_SETTINGS_REGISTER', null));

        self::mergeLegacyCustomFields();

        self::applyDefaults();

        self::syncToAddonPageProperty();

        self::registerPagesPreparedListener();
    }

    /** Test helper. */
    public static function reset(): void
    {
        self::$subpages = [];
        self::$fields = [];
        self::$placeholders = [];
        self::$bootstrapped = false;
        Utils::resetCache();
    }

    // -- internals -----------------------------------------------------------

    /**
     * Normalize a field spec: defaults, style_input → style translation,
     * checkbox option type coercion.
     */
    private static function normalizeFieldSpec(array $spec): array
    {
        if (isset($spec['style_input']) && !isset($spec['style'])) {
            $spec['style'] = $spec['style_input'];
        }
        unset($spec['style_input']);

        if (!isset($spec['priority'])) {
            $spec['priority'] = 100;
        }
        if (!array_key_exists('placeholder', $spec)) {
            $spec['placeholder'] = true;
        }
        return $spec;
    }

    private static function loadYaml(): void
    {
        $addon = rex_addon::get(self::ADDON_NAME);
        $path = $addon->getDataPath('fields.yml');
        if (!file_exists($path)) {
            return;
        }

        try {
            $config = rex_file::getConfig($path);
        } catch (\Throwable $e) {
            rex_logger::factory()->error('massif_settings: failed to parse ' . $path . ': ' . $e->getMessage());
            return;
        }
        if (!is_array($config)) {
            return;
        }

        foreach ((array) ($config['subpages'] ?? []) as $key => $sub) {
            if (!is_array($sub)) {
                continue;
            }
            $fields = $sub['fields'] ?? [];
            unset($sub['fields']);
            self::subpage((string) $key, $sub);
            foreach ((array) $fields as $field) {
                if (is_array($field)) {
                    self::field((string) $key, $field);
                }
            }
        }

        foreach ((array) ($config['placeholders'] ?? []) as $entry) {
            if (is_string($entry)) {
                self::placeholder($entry);
            } elseif (is_array($entry) && isset($entry['name'])) {
                $name = (string) $entry['name'];
                unset($entry['name']);
                self::placeholder($name, $entry);
            }
        }
    }

    private static function mergeLegacyCustomFields(): void
    {
        $legacy = rex_extension::registerPoint(new rex_extension_point('MASSIF_SETTINGS_CUSTOM_FIELDS', []));
        if (!is_array($legacy)) {
            return;
        }
        foreach ($legacy as $entry) {
            if (is_string($entry)) {
                self::placeholder($entry);
                continue;
            }
            if (is_array($entry) && isset($entry['name'])) {
                $name = (string) $entry['name'];
                $spec = $entry;
                unset($spec['name']);
                self::placeholder($name, $spec);
            }
        }
    }

    /**
     * For every field with a `default` value, write it to rex_config if no
     * value is currently stored. Fixes parent ConfigForm's silent ignore of
     * the `default` key.
     */
    private static function applyDefaults(): void
    {
        $existing = rex_config::get(self::ADDON_NAME);
        $existing = is_array($existing) ? $existing : [];

        foreach (self::$fields as $subKey => $fields) {
            foreach ($fields as $spec) {
                if (!array_key_exists('default', $spec)) {
                    continue;
                }
                $id = $subKey . '_' . rex_string::normalize($spec['name']);
                if (array_key_exists($id, $existing)) {
                    continue;
                }
                rex_config::set(self::ADDON_NAME, $id, $spec['default']);
            }
        }
    }

    /**
     * Push the merged subpage tree onto the addon's `page` property so the
     * parent massif addon's ConfigForm and Package classes pick them up
     * exactly as they would from package.yml.
     */
    private static function syncToAddonPageProperty(): void
    {
        $addon = rex_addon::get(self::ADDON_NAME);
        $page = $addon->getProperty('page');
        if (!is_array($page)) {
            $page = [];
        }
        $page['subpages'] = $page['subpages'] ?? [];

        foreach (self::getSubpages() as $key => $sub) {
            $page['subpages'][$key] = [
                'title' => $sub['title'] ?? $key,
                'icon' => $sub['icon'] ?? null,
                'fields' => self::serializeFieldsForParent($key),
            ];
            if (isset($sub['perm'])) {
                $page['subpages'][$key]['perm'] = $sub['perm'];
            }
        }

        $addon->setProperty('page', $page);
    }

    /**
     * Convert internal field specs into the array shape parent's ConfigForm reads.
     * Sorted by priority. Callable formatters and registry-only keys are stripped
     * so they don't end up as bogus HTML attributes.
     */
    private static function serializeFieldsForParent(string $subpage): array
    {
        $out = [];
        foreach (self::getFields($subpage) as $spec) {
            $clean = $spec;
            unset(
                $clean['priority'],
                $clean['placeholder'],
                $clean['placeholderAlias'],
                $clean['default']
            );
            if (isset($clean['formatter']) && !is_string($clean['formatter'])) {
                unset($clean['formatter']);
            }
            $out[] = $clean;
        }
        return $out;
    }

    /**
     * If a subpage was registered after PAGES_PREPARED-equivalent boot phase
     * had a chance to run via setProperty, this listener catches it and adds
     * a rex_be_page entry to the BE navigation.
     */
    private static function registerPagesPreparedListener(): void
    {
        if (self::$pagesPreparedRegistered) {
            return;
        }
        self::$pagesPreparedRegistered = true;

        rex_extension::register('PAGES_PREPARED', static function () {
            $pageObject = rex_be_controller::getPageObject(self::ADDON_NAME);
            if ($pageObject === null) {
                return;
            }
            $addon = rex_addon::get(self::ADDON_NAME);
            foreach (self::getSubpages() as $key => $sub) {
                if ($pageObject->getSubpage($key) !== null) {
                    continue;
                }
                $bePage = new rex_be_page($key, $sub['title'] ?? $key);
                if (!empty($sub['icon'])) {
                    $bePage->setIcon($sub['icon']);
                }
                if (!empty($sub['perm'])) {
                    $bePage->setRequiredPermissions((array) $sub['perm']);
                }
                $bePage->setSubPath($addon->getPath('pages/default.php'));
                $pageObject->addSubpage($bePage);
            }
        });
    }
}
