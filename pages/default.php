<?php

use Ynamite\Massif\Be\ConfigForm;

$key = rex_be_controller::getCurrentPageObject()->getKey();
$package = $this->getProperty('package') . '-';
$pages = $this->getProperty('page');
$be_page = \rex_be_controller::getCurrentPageObject();

$form = ConfigForm::getForm(package: $this->getProperty('package'), subpage: $key);

$content = '';
$content .= $form->get();

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $be_page->getTitle(), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
