<?php

$db = \Contao\Database::getInstance();

if (!$db->fieldExists('config_hash', 'tl_iso_request_cache')) {
    $db->execute(
        "ALTER TABLE tl_iso_request_cache ADD config_hash VARCHAR(12) NOT NULL DEFAULT ''"
    );
}
