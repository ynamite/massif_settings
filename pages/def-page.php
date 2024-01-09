<?php


$key = rex_be_controller::getCurrentPageObject()->getKey();
$package = $this->getProperty('package') . '-';
$pages = $this->getProperty('page');
$be_page = \rex_be_controller::getCurrentPageObject();

$fields = $pages['subpages'][$key]['fields'];

$form = rex_config_form::factory($this->getProperty('package'));

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

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $be_page->getTitle(), false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
