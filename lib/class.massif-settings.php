<?php

namespace Ynamite\MassifSettings;

use rex_addon;
use rex_config;
use rex_file;
use rex_path;
use rex_string;
use rex_be_controller;
use rex_Formatter;
use rex_exception;
use rex_functional_exception;
use massif_utils;

class Utils
{
    private static $addonName = 'massif_settings';

    public static function getFaviconHtml()
    {
        $addon = rex_addon::get(self::$addonName);
        $html = rex_file::get($addon->getCachePath('faviconData.html'));
        if ($html) {
            return $html;
        }
        return self::generateFaviconData();
    }

    public static function generateFaviconData()
    {
        $addon = rex_addon::get(self::$addonName);
        try {
            $shellOutput = exec('source ~/.zshrc && cd ' . rex_path::base() . ' && yarn generate-favicon 2>&1');
            if (!str_contains($shellOutput, 'Done'))
                throw new rex_exception($shellOutput);
            $favIconData = rex_file::get(rex_path::base('faviconData.json'));
            if ($favIconData) {
                $favIconData = json_decode($favIconData, true);
                rex_file::put($addon->getCachePath('faviconData.html'), $favIconData['favicon']['html_code']);
                return $favIconData['favicon']['html_code'];
            }
        } catch (rex_functional_exception $e) {
            dump($e);
        }
    }

    public static function beGenerateFaviconDataFromFields($fields)
    {
        $key = rex_be_controller::getCurrentPageObject()->getKey();

        $addon = rex_addon::get(self::$addonName);
        $addonName = $addon->getName();

        $map = [
            'master' => '["masterPicture"]',
            'name' => '["design"]["androidChrome"]["manifest"]["name"]',
            'desktop_bg' => '["design"]["desktopBrowser"]["backgroundColor"]',
            'desktop_radius' => '["design"]["desktopBrowser"]["backgroundRadius"]',
            'desktop_scale' => '["design"]["desktopBrowser"]["imageScale"]',
            'ios_bg' => '["design"]["ios"]["backgroundColor"]',
            'ios_margin' => '["design"]["ios"]["margin"]',
            'win_bg' => '["design"]["windows"]["backgroundColor"]',
            'android_margin' => '["design"]["androidChrome"]["margin"]',
            'android_bg' => '["design"]["androidChrome"]["backgroundColor"]',
            'android_theme' => '["design"]["androidChrome"]["themeColor"]',
            'safari_theme' => '["design"]["safariPinnedTab"]["themeColor"]',
        ];
        $values = rex_config::get($addonName);
        $json = rex_file::get($addon->getDataPath('faviconDescription.json'));
        if ($json) {
            $jsonData = json_decode($json, true);
            foreach ($fields as $field) {
                $name = $key . '_' . rex_string::normalize($field['name']);
                $value = isset($values[$name]) ? $values[$name] : '';
                if (isset($map[$field['name']]) && $value !== '') {
                    eval('$jsonData' . $map[$field['name']] . ' = $value;');
                } else if (isset($map[$field['name']])) {
                    eval('$value = $jsonData' . $map[$field['name']] . ';');
                    rex_config::set($addonName, $name, $value);
                }
                if ($field['name'] == 'desktop_bg' && $value === '') {
                    $jsonData["design"]["desktopBrowser"]["design"] = 'raw';
                } else {
                    $jsonData["design"]["desktopBrowser"]["design"] = 'background';
                }
            }
        }
        $json = rex_file::put($addon->getDataPath('faviconDescription.json'), json_encode($jsonData, JSON_PRETTY_PRINT));
        if (self::generateFaviconData()) {
            return true;
        }
    }

    public static function replaceStrings($html)
    {

        $addon = rex_addon::get(self::$addonName);
        $pages = $addon->getProperty('page');

        $additionalFields = [' m2 ' => ' m&sup2; ', ' m3 ' => ' m&sup3; '];

        $search = [];
        $replace = [];

        foreach ($pages['subpages'] as $ns => $subpages) {
            $fields = $subpages['fields'];

            foreach ($fields as $field) {

                if (isset($field['active']) && $field['active'] === false)
                    continue;

                $f = strtolower($field['name']);
                $id = $ns . '_' . $f;

                $config = $addon->getConfig($ns . '_' . rex_string::normalize($f));
                if ($config === null)
                    continue;


                $val = rex_Formatter::nl2br($config);

                if ($val) {

                    if ($f == 'phone') {
                        $search[] = '{{' . $id . '_html}}';
                        $replace[] = '<a href="tel:' . massif_utils::formatPhone($val) . '" data-no-swup class="contact-link phone-number">' . $val . '</a>';
                    }
                    if ($f == 'hotline') {
                        $search[] = '{{' . $id . '_raw}}';
                        $replace[] = massif_utils::formatPhone($val);
                        $search[] = '{{' . $id . '_html}}';
                        $replace[] = '<a href="tel:' . massif_utils::formatPhone($val) . '" data-no-swup class="contact-link phone-number ' . $f . '-number">' . $val . '</a>';
                    }
                    if ($f == 'fax') {
                        $search[] = '{{' . $id . '_html}}';
                        $replace[] = '<a href="tel:' . massif_utils::formatPhone($val) . '" data-no-swup class="contact-link phone-number">Fax ' . $val . '</a>';
                    }
                    if ($f == 'linkedin') {
                        $search[] = '{{' . $id . '_html}}';
                        $replace[] = '<a href="' . $val . '" target="_blank" data-no-swup class="contact-link linkedin" rel="noreferrer">Linkedin</a>';
                    }
                    if ($f == 'xing') {
                        $search[] = '{{' . $id . '_html}}';
                        $replace[] = '<a href="' . $val . '" target="_blank" data-no-swup class="contact-link linkedin" rel="noreferrer">Xing</a>';
                    }
                    if ($f == 'e-mail') {
                        $search[] = '{{' . $id . '_html}}';
                        $replace[] = '<a href="mailto:' . $val . '" data-no-swup class="contact-link e-mail">' . $val . '</a>';
                    }

                    $search[] = '{{' . $id . '}}';
                    $replace[] = $val;
                }
            }
        }

        foreach ($additionalFields as $key => $val) {
            $search[] = $key;
            $replace[] = $val;
        }


        return str_replace($search, $replace, $html);
    }
}
