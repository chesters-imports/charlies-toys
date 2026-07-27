<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/app/filekeeper/media_lib.php';
require dirname(__DIR__) . '/app/filekeeper/paper_lib.php';

fk_media_seed_from_legacy('m.AAAEB1D70000528F');
$path = fk_media_resolve('m.AAAEB1D70000528F');
echo "resolve: $path\n";
$src = fk_media_img_src('m.AAAEB1D70000528F');
echo str_starts_with($src, 'data:image/gif') ? ('data-uri-ok len=' . strlen($src) . "\n") : ("src=$src\n");
$body = "<!-- IMG SUPPORT ONLINE -->\n![Sine_and_cosine_animation.gif](media:m.AAAEB1D70000528F)\n";
$rw = fk_media_rewrite_body_for_view($body);
$html = fk_render_md($rw);
echo str_contains($html, '<img') ? "view-has-img\n" : "view-no-img\n";
echo str_contains($html, 'missing image') ? "MISSING\n" : "ok\n";
