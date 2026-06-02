<?php

$src = $this->getPath('data/fields.yml');
$dst = $this->getDataPath('fields.yml');

if (!file_exists($dst) && file_exists($src)) {
    rex_dir::create(dirname($dst));
    rex_file::copy($src, $dst);
}
