<?php

$content = '';
$buttons = '';

// Einstellungen speichern
if (rex_post('formsubmit', 'string') == '1') {



    // Kategorien und Artikel löschen

    $this->setConfig(rex_post('config', [
        ['checkbox_categories_articles', 'string'],
    ]));

    if ($this->getConfig('checkbox_categories_articles') == '1') {
        $sql = rex_sql::factory();
        $sql->setquery("TRUNCATE TABLE rex_article");
        $sql->setquery("TRUNCATE TABLE rex_article_slice");
        $sql->setquery("TRUNCATE TABLE rex_article_slice_history");
        rex_delete_cache();
        $this->setConfig('checkbox_categories_articles') == '0';
        echo rex_view::success($this->i18n('cc_del_success_categories_articles'));
    }



}

// Kategorien und Artikel löschen
$content .= '<fieldset><legend>' . $this->i18n('cc_legend_categories_articles') . '</legend>';
$formElements = [];
$n = [];
$n['label'] = '<label for="demo_addon-config-checkbox">' . $this->i18n('cc_config_checkbox_categories_articles') . '</label>';
$n['field'] = '<input type="checkbox" id="checkbox_categories_articles" name="config[checkbox_categories_articles]"' . (!empty($this->getConfig('checkbox_categories_articles')) && $this->getConfig('checkbox_categories_articles') == '1' ? ' checked="checked"' : '') . ' value="1" />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/checkbox.php');




$content .= '<fieldset><legend>' . $this->i18n('cc_legend_slices') . '</legend>';
$content .= '<fieldset><legend>' . $this->i18n('cc_legend_media') . '</legend>';
$content .= '<fieldset><legend>' . $this->i18n('cc_legend_mediacategories') . '</legend>';




$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="save" value="' . $this->i18n('cc_config_clear') . '">' . $this->i18n('cc_config_clear') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');
$buttons = '
<fieldset class="rex-form-action">
    ' . $buttons . '
</fieldset>
';


$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $this->i18n('clear_content'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$output = $fragment->parse('core/page/section.php');

$output = '
<form action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="formsubmit" value="1" />
    ' . $output . '
</form>';

echo $output;
