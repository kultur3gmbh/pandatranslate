<?php
require __DIR__ . '/bootstrap.php';
$root=dirname(__DIR__);$manifest=json_decode(file_get_contents($root.'/assets/panda-family-library.json'),true);
foreach($manifest['files'] as $path=>$checksum)assert(hash_file('sha256',$root.'/'.$path)===$checksum);
echo "Complete canonical family library: all bundled asset checksums match: ok\n";
