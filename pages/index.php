<?php
echo rex_view::title($this->i18n('clear_content'));

//include rex_be_controller::getCurrentPageObject()->getSubPath();
rex_be_controller::includeCurrentPageSubPath();
?>
