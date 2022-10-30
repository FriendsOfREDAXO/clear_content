<?php

$addon = rex_addon::get('clear_content');

if (!function_exists('deleteCategory')) {

    function deleteCategory($article_id = null) {
        if (!$article_id) {
            return null;
        }

        //MAKE SURE WE ARE USING THE CATEGORY
        $category_id = rex_article::get($article_id)->getCategoryId();

        //DELETE IF ROOT ARTICLE
        if ($category_id === 0) {
            rex_article_service::deleteArticle($article_id);
            return null;
        }
        //GET CHILDD CATEGORIES
        $children = rex_category::get($category_id)->getChildren();
        if ($children) {
            foreach ($children as $child) {
                //GET ARTICLES FOR CHILD
                $articles = $child->getArticles();
                if ($articles) {
                    foreach ($articles as $article) {
                        if ($article && !$article->isStartArticle()) {
                            //DELETE ALL NON START ARTICLES
                            rex_article_service::deleteArticle($article->getId());
                        }
                    }
                }
                if (0 === count($child->getChildren())) {
                    //DELETE CAT AND START ARTICLE OF CAT
                    rex_category_service::deleteCategory($child->getId());
                } else {
                    //RECURSE IF THERE ARE MORE CHILDREN
                    deleteCategory($child->getId());
                }
            }
        }
        //DELETE ARTICLES AND THE CAT ITSELF
        if (!rex_article::get($category_id)->isSiteStartArticle() && !rex_article::get($category_id)->isNotFoundArticle()) {
            $articles = rex_category::get($category_id)->getArticles();
            if ($articles) {
                foreach ($articles as $article) {
                    if ($article && !$article->isStartArticle()) {
                        rex_article_service::deleteArticle($article->getId());
                    }
                }
            }
            rex_category_service::deleteCategory($category_id);
        }
        return $category_id;
    }
}

$content = '';
$buttons = '';

// Einstellungen speichern
if (rex_post('formsubmit', 'string') === '1') {


    $addon->setConfig(rex_post('config', [
        ['checkbox_slices_all', 'string'],
        ['checkbox_categories_articles', 'string'],
        ['specific_category_articles', 'string'],
        ['checkbox_media_cats', 'string'],
        ['checkbox_media', 'string'],
        ['checkbox_slices_all', 'string']
    ]));


    foreach (rex_clang::getAll(false) as $lang) {
        $lang_id   = $lang->getValue('id');
        $addon->setConfig(rex_post('config', [
            ['checkbox_slices_lang_'.$lang_id, 'string']
        ]));
    }

    $sql = rex_sql::factory();

    // Slices
    if ($addon->getConfig('checkbox_slices_all') === '1' ) {

        if (rex_sql_table::get(rex::getTablePrefix() .'article_slice_history')->exists()) {
           $sql->setquery("TRUNCATE TABLE ". rex::getTablePrefix() ."article_slice_history");
        }

        if (rex_sql_table::get(rex::getTablePrefix() .'article_slice')->exists()) {
            $sql->setquery("TRUNCATE TABLE ". rex::getTablePrefix() ."article_slice");
        }

        echo rex_view::success($addon->i18n('cc_del_success_slices'));
    }

    if ($addon->getConfig('checkbox_slices_all') !== '1' ) {
        foreach (rex_clang::getAll(false) as $lang) {
            $lang_id = $lang->getValue('id');
            $lang_name = $lang->getValue('name');
            if ($addon->getConfig('checkbox_slices_lang_' . $lang_id) === $lang) {


                if (rex_sql_table::get(rex::getTablePrefix() .'article_slice')->exists()) {
                    $sql->setquery('DELETE FROM '. rex::getTablePrefix() .'article_slice WHERE clang_id !=:cid', array('cid'=> $lang_id));

                }

                if (rex_sql_table::get(rex::getTablePrefix() .'article_slice_history')->exists()) {
                    $sql->setquery('DELETE FROM '. rex::getTablePrefix() .'article_slice_history WHERE clang_id !=:cid', array('cid'=> $lang_id));
                }

                echo rex_view::success($addon->i18n('cc_del_success_slices1').' <b>'.$lang_name.'</b> '.$addon->i18n('cc_del_success_slices2'));
            }
        }
    }

    // Kategorien und Artikel löschen
    if ($addon->getConfig('checkbox_categories_articles') === '1') {

        if (rex_sql_table::get(rex::getTablePrefix() .'article')->exists()) {
            $sql->setquery("TRUNCATE TABLE ". rex::getTablePrefix() ."article");
        }
        if (rex_sql_table::get(rex::getTablePrefix() .'article_slice')->exists()) {
            $sql->setquery("TRUNCATE TABLE ". rex::getTablePrefix() ."article_slice");
        }
        if (rex_sql_table::get(rex::getTablePrefix() .'article_slice_history')->exists()) {
            $sql->setquery("TRUNCATE TABLE ". rex::getTablePrefix() ."article_slice_history");
        }
        echo rex_view::success($addon->i18n('cc_del_success_categories_articles'));
    }

    // Bestimmte Kategorie und Artikel rekursiv löschen
    if ($addon->getConfig('specific_category_articles') > 0) {

        $article_id = $addon->getConfig('specific_category_articles');
        $deleted_category_id = deleteCategory($article_id);
        echo rex_view::success(sprintf($addon->i18n('cc_del_success_specific_category_articles'), $deleted_category_id));
    }

    // Medienkategorien löschen
    if ($addon->getConfig('checkbox_media_cats') === '1') {

        if (rex_sql_table::get(rex::getTablePrefix() .'media_category')->exists()) {
            $sql->setquery("TRUNCATE TABLE ". rex::getTablePrefix() ."media_category");
            $sql->setquery("UPDATE ". rex::getTablePrefix() ."media SET category_id = 0");
        }
        echo rex_view::success($addon->i18n('cc_del_success_media_cats'));
    }

    // Medien löschen
    if ($addon->getConfig('checkbox_media') === '1') {
        // rex_dir::deleteIterator(rex_finder::factory(rex_path::media())->ignoreFiles('.redaxo'));
        rex_dir::deleteIterator(rex_finder::factory(rex_path::media())
            ->filesOnly()
            ->ignoreFiles('.redaxo')
        );
        $sql = rex_sql::factory();

        if (rex_sql_table::get(rex::getTablePrefix() .'media')->exists()) {
            $sql->setquery("TRUNCATE TABLE ". rex::getTablePrefix() ."media");
            $sql->setquery("UPDATE ". rex::getTablePrefix() ."media SET category_id = 0");

        }
        echo rex_view::success($addon->i18n('cc_del_success_media'));
    }

    foreach (rex_clang::getAll(false) as $lang) {
        $lang_id   = $lang->getValue('id');
        $addon->setConfig('checkbox_slices_lang_'.$lang_id) === '';
    }

    $addon->setConfig('checkbox_categories_articles') === '';
    $addon->setConfig('checkbox_media_cats') === '';
    $addon->setConfig('checkbox_media') === '';
    $addon->setConfig('checkbox_slices_all') === '';
    rex_delete_cache();
}


    // Slices
    $content .= '<fieldset><legend>' . $addon->i18n('cc_legend_slices') . '</legend>';
    $formElements = [];
    $n = [];
    $n['label'] = '<label>' . $addon->i18n('cc_config_checkbox_slices_all') . '</label>';
    $n['field'] = '<input type="checkbox" id="checkbox_languages_all" name="config[checkbox_slices_all]"' . ($addon->getConfig('checkbox_slices_all') !== '' && $addon->getConfig('checkbox_slices_all') === '1' ? ' checked="checked"' : '') . ' value="1" />';
    $formElements[] = $n;
    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/checkbox.php');

if (count(rex_clang::getAll(false)) > 1) {
    foreach (rex_clang::getAll(false) as $lang) {

        $lang_id = $lang->getValue('id');
        $lang_name = $lang->getValue('name');

        $formElements = [];
        $n = [];
        $n['label'] = '<label>'.$addon->i18n('cc_info1_slices') .' <b>'. $lang_name . ' (ID = '.$lang_id.')</b> '.$addon->i18n('cc_info2_slices') .'</label>';
        $n['field'] = '<input type="checkbox" id="checkbox_slices_lang_' . $lang_id . '" name="config[checkbox_slices_lang_' . $lang_id . ']"' . ($addon->getConfig('checkbox_slices_lang_' . $lang_id) !== ''  && $addon->getConfig('checkbox_slices_lang_' . $lang_id) === '1' ? ' checked="checked"' : '') . ' value="1" />';
        $formElements[] = $n;
        $fragment = new rex_fragment();
        $fragment->setVar('elements', $formElements, false);
        $content .= $fragment->parse('core/form/checkbox.php');

    }
}



// Kategorien und Artikel löschen
$content .= '<fieldset><legend>' . $addon->i18n('cc_legend_categories_articles') . '</legend>';
$formElements = [];
$n = [];
$n['label'] = '<label>' . $addon->i18n('cc_config_checkbox_categories_articles') . '</label>';
$n['field'] = '<input type="checkbox" id="checkbox_categories_articles" name="config[checkbox_categories_articles]"' . (($addon->getConfig('checkbox_categories_articles') !== '' ) && $addon->getConfig('checkbox_categories_articles') === '1' ? ' checked="checked"' : '') . ' value="1" />';


$formElements[] = $n;
$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/checkbox.php');

// Bestimmte Kategorien und Artikel rekursiv löschen
$content .= '<fieldset><legend>' . $addon->i18n('cc_legend_specific_category_articles') . '</legend>';
$formElements = [];
$n = [];
$n['label'] = '<label for="REX_LINK_1_NAME">' . $addon->i18n('cc_select_specific_category_articles') . '</label>';
$n['field'] = rex_var_link::getWidget(1, 'config[specific_category_articles]', '');
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/container.php');


// Ausgewählte Artikel löschen
$content .= '<fieldset><legend>' . $addon->i18n('cc_legend_specific_articles') . '</legend>';
$formElements = [];
$n = [];
$n['label'] = '<label for="REX_LINK_2_NAME">' . $addon->i18n('cc_select_specific_articles') . '</label>';
$n['field'] = rex_var_linklist::getWidget(1, 'config[specific_articles]', '');
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/container.php');


// Medien
$content .= '<fieldset><legend>' . $addon->i18n('cc_legend_media') . '</legend>';
// Medienkategorien löschen
$formElements = [];
$n = [];
$n['label'] = '<label>' . $addon->i18n('cc_config_checkbox_media_cats') . '</label>';
$n['field'] = '<input type="checkbox" id="checkbox_media_cats" name="config[checkbox_media_cats]"' . ($addon->getConfig('checkbox_media_cats') !== ''  && $addon->getConfig('checkbox_media_cats') === '1' ? ' checked="checked"' : '') . ' value="1" />';
$formElements[] = $n;
$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/checkbox.php');

// Medien löschen
$formElements = [];
$n = [];
$n['label'] = '<label>' . $addon->i18n('cc_config_checkbox_media') . '</label>';
$n['field'] = '<input type="checkbox" id="checkbox_media" name="config[checkbox_media]"' . ($addon->getConfig('checkbox_media') !== '' && $addon->getConfig('checkbox_media') === '1' ? ' checked="checked"' : '') . ' value="1" />';
$formElements[] = $n;
$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/checkbox.php');

$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="save" value="' . $addon->i18n('cc_config_clear') . '" data-confirm="' . $addon->i18n('cc_config_clear_confirmation') . '">' . $addon->i18n('cc_config_clear') . '</button>';
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
$fragment->setVar('title', $addon->i18n('clear_content'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$output = $fragment->parse('core/page/section.php');

$output = '
<div id="clear_content">
<form action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="formsubmit" value="1" />
    ' . $output . '
</form>
</div>';

echo $output;
