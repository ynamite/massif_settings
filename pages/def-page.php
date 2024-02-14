<?php

use Ynamite\MassifSettings\ConfigForm;

$key = rex_be_controller::getCurrentPageObject()->getKey();
$package = $this->getProperty('package') . '-';
$pages = $this->getProperty('page');
$be_page = \rex_be_controller::getCurrentPageObject();

$fields = $pages['subpages'][$key]['fields'];


$form = ConfigForm::factory($this->getProperty('package'));


foreach ($fields as $f) {
    if (isset($f['active']) && $f['active'] === false)
        continue;
    $name = $key . '_' . rex_string::normalize($f['name']);
    $type = isset($f['type']) ? $f['type'] : 'text';
    switch ($type) {
        case "rex_media":
            $field = $form->addMediaField($name);
            break;
        case "textarea":
            $field = $form->addTextAreaField($name);
            break;
        case "text":
        default:
            $field = $form->addTextField($name);
            break;
    }
    if ($field) {
        if (isset($f['class']) && $f['class']) {
            $field->setAttribute('class', "form-control " . $f['class']);
        }
        if (isset($f['rows'])) {
            $field->setAttribute('rows', $f['rows']);
        }
        if (isset($f['style'])) {
            $field->setAttribute('style', $f['style']);
        }
        if (isset($f['data'])) {
            foreach ($f['data'] as $k => $v) {
                $field->setAttribute('data-' . $k, $v);
            }
        }

        $field->setLabel($f['label'] . '<br><small class="help-block rex-note" style="display:inline-block; font-weight: normal; margin: 0;">{{' . $name . '}}</small>');
        //$field->setNotice('test');
    }
}
$content = '';
if ($be_page->getKey() == 'favicon' && !class_exists('Ynamite\ViteRex\ViteRex')) {
    $content .= '<div class="alert alert-info" role="alert">Requires ViteRex!</div>';
} else {

    if ($be_page->getKey() == 'favicon') {
        $icons = [
            'favicon-16x16.png',
            'favicon-32x32.png',
            'favicon.ico',
            'mstile-150x150.png',
            'android-chrome-192x192.png',
            'android-chrome-512x512.png',
            'apple-touch-icon.png',
            'safari-pinned-tab.svg',
        ];
        $content .= '<div style="display: flex; gap: 10px; margin-bottom: 40px;">';
        foreach ($icons as $icon) {
            $content .= '<div style="display: flex; text-align: center; align-items:end;">';
            $content .= '<div>';
            $content .= '<img src="' . rex_url::base() . $icon . '" class="thumbnail" style="max-width: 100px; display: inline-block;"><br /><span style="font-size: 0.7em">' . $icon . '</span>';
            $content .= '</div>';
            $content .= '</div>';
        }
        $content .= '</div>';
    }

    $content .= $form->get();
}

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $be_page->getTitle(), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
