<?php

class massif_settings
{

    public static function replaceStrings($html)
    {

        $key = 'massif_settings';
        $addon = rex_addon::get($key);
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

                $val = rex_Formatter::nl2br($addon->getConfig($ns . '_' . rex_string::normalize($f)));

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
