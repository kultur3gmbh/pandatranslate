<?php
require __DIR__ . '/media-test.php';
$html='<html><body><div class="trp-no-translation"><img src="/manual.jpg" alt="Manual"></div><img data-no-translation src="/manual-self.jpg" alt="Manual self"><img id="target" src="/ordinary.jpg" alt="Ordinary"></body></html>';
$editing=PandaTranslate_Media::transform($html,'de',true,7);
assert(1===substr_count($editing,'data-pandatranslate-media='));
assert(false!==strpos($editing,'src="/manual.jpg" alt="Manual"'));
assert(false!==strpos($editing,'src="/manual-self.jpg" alt="Manual self"'));
assert(image($editing)->hasAttribute('data-pandatranslate-media'));
echo "Protected media: legacy ancestor and self marker excluded; ordinary adjacent image remains editable: ok\n";
