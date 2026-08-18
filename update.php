<?php

/** @var rex_addon $this */

if (rex_version::compare($this->getVersion(), '2.0.0', '<')) {
    // Version 1.x stored pending deletion choices in rex_config. Version 2.x
    // deliberately keeps destructive form state request-local.
    rex_config::removeNamespace($this->getPackageId());
}
