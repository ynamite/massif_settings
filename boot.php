<?php

namespace Ynamite\MassifSettings;

use rex;
use rex_extension;

$addon = $this->getAddon();

// Defer bootstrap until all addons' boot.php have loaded so that consumers'
// MASSIF_SETTINGS_REGISTER listeners are in place before we fire that EP.
rex_extension::register('PACKAGES_INCLUDED', static function () {
    Registry::bootstrap();
});

$search_it_indexer = rex_get('search_it_build_index', 'string');
$search_it_highlighter = rex_get('search_highlighter', 'string');
if ($search_it_indexer == "" && $search_it_highlighter != "") {
    $search_it_indexer = $search_it_highlighter;
}

if ($search_it_indexer == "") {

    rex_extension::register('OUTPUT_FILTER', function (\rex_extension_point $ep) {

        if (rex::isBackend()) {
            $be_page = \rex_be_controller::getCurrentPageObject();
            if ($be_page) {
                $key = $be_page->getFullKey();
                if (str_starts_with($key, 'massif_settings/') || ($key === 'content/edit' && rex_request('function', 'string') === 'edit')) {
                    return;
                }
            }
        }

        $ep->setSubject(Utils::replaceStrings($ep->getSubject()));
    }, rex_extension::NORMAL);
}
