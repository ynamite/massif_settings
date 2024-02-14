<?php

namespace Ynamite\MassifSettings;

use rex_addon;
use rex_config_form;
use rex_be_controller;

class ConfigForm extends rex_config_form
{
  protected function save()
  {
    parent::save();

    $key = rex_be_controller::getCurrentPageObject()->getKey();
    $pages = rex_addon::get('massif_settings')->getProperty('page');
    $be_page = rex_be_controller::getCurrentPageObject();

    $fields = $pages['subpages'][$key]['fields'];


    if ($be_page->getKey() == 'favicon') {
      return \Ynamite\MassifSettings\Utils::beGenerateFaviconDataFromFields($fields);
    }
    return true;
  }
}
