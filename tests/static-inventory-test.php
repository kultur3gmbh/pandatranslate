<?php
define('ABSPATH',__DIR__);require_once __DIR__.'/../includes/class-pandatranslate-static-inventory.php';
$php= <<<'SOURCE'
<?php
__('Hello','theme');_x('Post','noun','theme');__('Ignore '.$customer,'theme');__('Default');esc_attr__('Order %s','woocommerce');__('Own UI','pandatranslate');
/* __('Comment','theme'); */
$literal = "__('String content','theme')";
SOURCE;
$entries=PandaTranslate_Static_Inventory::literals($php);
assert(array('Hello','Post','Default','Order %s')===array_column($entries,'text'));assert('noun'===$entries[1]['context']);assert('default'===$entries[2]['domain']);
echo "Static inventory: literal calls and gettext context/domain parsed without executing source; concatenated customer values, comments, quoted code and own UI excluded: ok\n";
