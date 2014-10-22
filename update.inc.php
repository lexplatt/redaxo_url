<?php

if (rex_string::versionCompare(OOAddon::getVersion('yrewrite'), '1.1', '<=')) {
    $sql = rex_sql::factory();
    $sql->setQuery('ALTER TABLE `rex_yrewrite_domain` DROP `clang`;');
    $sql->setQuery('ALTER TABLE `rex_yrewrite_domain` ADD `clangs` varchar(255) NOT NULL;');
    $sql->setQuery('ALTER TABLE `rex_yrewrite_domain` ADD `clang_start` varchar(255) NOT NULL;');

}

rex_generateAll();
