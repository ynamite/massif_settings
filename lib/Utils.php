<?php

namespace Ynamite\MassifSettings;

use rex_addon;
use rex_config;
use rex_Formatter;
use rex_string;
use Ynamite\Massif\Utils as MassifUtils;

class Utils
{
    private static string $addonName = 'massif_settings';
    private static ?array $formattedData = null;

    /**
     * Replace placeholders in the given HTML with the corresponding values from the formatted data.
     *
     * @param string $html The HTML content with placeholders.
     * @return string The HTML content with replaced placeholders.
     */
    public static function replaceStrings(string $html): string
    {
        $search = [];
        $replace = [];
        foreach (self::getData() as $key => $value) {
            $search[] = '{{' . $key . '}}';
            $replace[] = $value;
        }
        return str_replace($search, $replace, $html);
    }

    /**
     * Get the formatted data array.
     *
     * @return array<string, string> The formatted data array.
     */
    public static function getData(): array
    {
        if (self::$formattedData !== null) {
            return self::$formattedData;
        }

        Registry::bootstrap();

        $configData = rex_addon::get(self::$addonName)->getConfig();
        $data = [];

        foreach (Registry::getSubpages() as $subKey => $sub) {
            foreach (Registry::getFields($subKey) as $field) {
                if (($field['active'] ?? true) === false) {
                    continue;
                }
                if (($field['placeholder'] ?? true) === false) {
                    continue;
                }
                $f = rex_string::normalize($field['name']);
                $id = $subKey . '_' . $f;
                if (!array_key_exists($id, $configData)) {
                    continue;
                }
                $val = rex_Formatter::nl2br((string) $configData[$id]);
                $data[$id] = $val;

                foreach ((array) ($field['placeholderAlias'] ?? []) as $alias) {
                    $data[(string) $alias] = $val;
                }

                if (empty($field['formatter'])) {
                    continue;
                }
                $formatted = self::applyFormatter($field['formatter'], $f, $val, (string) ($field['label'] ?? ''));
                if ($formatted !== '') {
                    $data[$id . '_formatted'] = $formatted;
                }
            }
        }

        foreach (Registry::getPlaceholders() as $name => $spec) {
            $val = self::resolvePlaceholder($name, $spec);
            $data[$name] = $val;
            if (empty($spec['formatter']) || $val === '') {
                continue;
            }
            $formatted = self::applyFormatter($spec['formatter'], $name, $val, (string) ($spec['label'] ?? ''));
            if ($formatted !== '') {
                $data[$name . '_formatted'] = $formatted;
            }
        }

        $data += ['m2' => 'm&sup2;', 'm3' => 'm&sup3;'];

        self::$formattedData = $data;
        return $data;
    }

    /**
     * Get a specific setting value.
     * If the setting does not exist, an empty string will be returned.
     *
     * @param string $key The setting key.
     * @return string The formatted setting value.
     */
    public static function get(string $key): string
    {
        $data = self::getData();
        return $data[$key] ?? '';
    }

    /**
     * Get a specific setting value wrapped in $element with $className.
     * If the setting does not exist, the $element will not be returned.
     *
     * @param string $key The setting key.
     * @param string $element The HTML element to wrap the setting value.
     * @param string $className The CSS class to add to the element.
     * @return string The formatted setting value.
     */
    public static function getWrapped(string $key, string $element = 'div', string $className = ''): string
    {
        $value = self::get($key);
        if ($value === '') {
            return '';
        }
        $classAttribute = $className !== '' ? ' class="' . htmlspecialchars($className) . '"' : '';
        return '<' . $element . $classAttribute . '>' . $value . '</' . $element . '>';
    }

    /** Clear the per-request data cache. Test helper, called from Registry::reset(). */
    public static function resetCache(): void
    {
        self::$formattedData = null;
    }

    // -- internals -----------------------------------------------------------

    /** @param string|callable $formatter */
    private static function applyFormatter($formatter, string $key, string $value, string $label): string
    {
        if ($value === '') {
            return '';
        }
        if (is_callable($formatter)) {
            return (string) $formatter($value, $label, $key);
        }
        switch ($formatter) {
            case 'phone':
                return '<a href="tel:' . MassifUtils\Format::phone($value) . '" data-no-swup class="contact-link phone-number">' . $value . '</a>';
            case 'fax':
                return '<a href="tel:' . MassifUtils\Format::phone($value) . '" data-no-swup class="contact-link phone-number fax-number">Fax ' . $value . '</a>';
            case 'email':
                return '<a href="mailto:' . $value . '" data-no-swup class="contact-link e-mail">' . $value . '</a>';
            case 'url':
                return '<a href="' . $value . '" target="_blank" data-no-swup class="url" rel="noreferrer">' . ($label !== '' ? $label : $value) . '</a>';
        }
        return '';
    }

    /** @param array<string,mixed> $spec */
    private static function resolvePlaceholder(string $name, array $spec): string
    {
        if (array_key_exists('value', $spec)) {
            return (string) $spec['value'];
        }
        if (isset($spec['resolver']) && is_callable($spec['resolver'])) {
            return (string) ($spec['resolver'])();
        }
        $package = (string) ($spec['package'] ?? self::$addonName);
        $key = (string) ($spec['key'] ?? $name);
        $value = rex_config::get($package, $key, '');
        return (string) $value;
    }
}
