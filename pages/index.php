<?php

/** @var rex_addon $this */

$version = $this->getVersion();
echo rex_view::title(
    $this->i18n('clear_content_title')
    . ' <small style="opacity:.7;font-size:.6em;">v' . rex_escape($version) . '</small>',
);
rex_be_controller::includeCurrentPageSubPath();
