<?php

/**
 * Snippets AddOn - Backend Entry Point
 *
 * @package redaxo\snippets
 */

$package = rex_addon::get('snippets');
echo rex_view::title($package->i18n('snippets_title'));

// Subpage laden
rex_be_controller::includeCurrentPageSubPath();
