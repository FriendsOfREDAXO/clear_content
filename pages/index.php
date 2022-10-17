<?php

$addon = rex_addon::get('clear_content');
echo rex_view::title($addon->i18n('clear_content'));

//include rex_be_controller::getCurrentPageObject()->getSubPath();
rex_be_controller::includeCurrentPageSubPath();


