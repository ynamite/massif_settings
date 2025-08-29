<?php

namespace Ynamite\MassifSettings;

use rex_addon;
use rex_string;
use rex_Formatter;
use Ynamite\Massif\Utils as MassifUtils;

class Utils
{
    private static string $addonName = 'massif_settings';
    private static array $formattedData = [];

    /**
     * Replace placeholders in the given HTML with the corresponding values from the formatted data.
     *
     * @param string $html The HTML content with placeholders.
     * @return string The HTML content with replaced placeholders.
     */
    public static function replaceStrings(string $html)
    {

        $search = [];
        $replace = [];

        $data = self::getData();

        foreach ($data as $key => $value) {
            $search[] = '{{' . $key . '}}';
            $replace[] = $value;
        }

        return str_replace($search, $replace, $html);
    }

    /**
     * Get the formatted data array.
     *
     * @return array The formatted data array.
     */
    public static function getData(): array
    {
        if (self::$formattedData) {
            return self::$formattedData;
        }

        $addon = rex_addon::get(self::$addonName);
        $pages = $addon->getProperty('page');
        $configData = $addon->getConfig();

        $additionalFields = ['m2' => 'm&sup2;', 'm3' => 'm&sup3;'];

        $data = [];

        foreach ($pages['subpages'] as $ns => $subpages) {
            $fields = $subpages['fields'];

            foreach ($fields as $field) {

                $f = rex_string::normalize($field['name']);
                $id = $ns . '_' . $f;

                if (!isset($configData[$id]))
                    continue;

                $val = rex_Formatter::nl2br($configData[$id]);

                $data[$id] = $val;

                $valFormatted = self::getFormattedData($f, $val, $data);

                if ($valFormatted) {
                    $data[$id . '_formatted'] = $valFormatted;
                }
            }
        }

        foreach ($additionalFields as $key => $val) {
            $data[$key] = $val;
        }

        self::$formattedData = $data;

        return $data;
    }

    private static function getFormattedData(string $key, string $value): string
    {
        $out = '';
        if (!$value) {
            return $out;
        }
        switch ($key) {
            case 'phone':
                $out = '<a href="tel:' . MassifUtils\Format::phone($value) . '" data-no-swup class="contact-link phone-number">' . $value . '</a>';
                break;
            case 'fax':
                $out = '<a href="tel:' . MassifUtils\Format::phone($value) . '" data-no-swup class="contact-link phone-number fax-number">Fax ' . $value . '</a>';
                break;
            case 'e_mail':
                $out = '<a href="mailto:' . $value . '" data-no-swup class="contact-link e-mail">' . $value . '</a>';
                break;
            case 'instagram':
                $out = '<a href="' . $value . '" target="_blank" data-no-swup class="social-link instagram" rel="noreferrer">Instagram</a>';
                break;
            case 'linkedin':
                $out = '<a href="' . $value . '" target="_blank" data-no-swup class="social-link linkedin" rel="noreferrer">Linkedin</a>';
                break;
            case 'xing':
                $out = '<a href="' . $value . '" target="_blank" data-no-swup class="social-link xing" rel="noreferrer">Xing</a>';
                break;
        }
        return $out;
    }

    /**
     * Get a specific setting value.
     * If the setting does not exist, an empty string will be returned.
     *  @param string $key The setting key.
     * 
     *  @return string The formatted setting value.
     */

    public static function get(string $key): string
    {
        $data = self::getData();

        if (!isset($data[$key])) {
            return '';
        }

        return $data[$key];
    }

    /**
     * Get a specific setting value wrapped in $element with $className.
     * If the setting does not exist, the $element will not be returned.
     * If $className is provided, it will be added to the $element.
     *  @param string $key The setting key.
     *  @param string $element The HTML element to wrap the setting value.
     *  @param string $className The CSS class to add to the element.
     * 
     *  @return string The formatted setting value.
     */

    public static function getWrapped(string $key, string $element = 'div', string $className = ''): string
    {
        $value = self::get($key);

        if (!$value) {
            return '';
        }

        $classAttribute = $className ? ' class="' . htmlspecialchars($className) . '"' : '';

        return '<' . $element . $classAttribute . '>' . $value . '</' . $element . '>';
    }
}
