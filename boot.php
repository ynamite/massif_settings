<?php

namespace Ynamite\MassifSettings;

use rex;
use rex_extension;
use rex_extension_point;

$addon = $this->getAddon();

$search_it_indexer = rex_get('search_it_build_index', 'string');
$search_it_highlighter = rex_get('search_highlighter', 'string');
if ($search_it_indexer == "" && $search_it_highlighter != "") {
    $search_it_indexer = 'search_it_highlighter';
}

if (/*!rex::isBackend() &&*/$search_it_indexer == "") {

    rex_extension::register('OUTPUT_FILTER', function (\rex_extension_point $ep) {

        if (rex::isBackend()) {
            $be_page = \rex_be_controller::getCurrentPageObject();
            if ($be_page) {
                $key = $be_page->getFullKey();
                if ($key === 'massif_settings/address' || ($key === 'content/edit' && rex_request('function', 'string') === 'edit')) {
                    $ep->setSubject($ep->getSubject());
                    return;
                }
            }
        }

        $html = Utils::replaceStrings($ep->getSubject());

        $ep->setSubject($html);

        return;
    }, rex_extension::NORMAL);
}
