<?php
/**
 * Station media — resolve media:m.HEX for paper view (print first; upload later).
 * Looks in SDK_MEDIA_ROOT, then optional legacy Builds d/_MEDIA.
 */
declare(strict_types=1);

function fk_media_root(): string {
    $r = defined('SDK_MEDIA_ROOT') ? SDK_MEDIA_ROOT : (SDK_ROOT . '/media');
    $r = rtrim(str_replace('\\', '/', $r), '/');
    if (!is_dir($r)) {
        @mkdir($r, 0775, true);
    }
    return $r;
}

/** @return list<string> */
function fk_media_search_roots(): array {
    $roots = [fk_media_root()];
    $legacy = 'C:/Builds/my-pocket-internet/d/_MEDIA';
    if (is_dir($legacy)) {
        $roots[] = $legacy;
    }
    // also under ALICE if ever copied
    $alt = 'C:/ALICE_BOX/.media';
    if (is_dir($alt)) {
        $roots[] = $alt;
    }
    return $roots;
}

function fk_media_safe_id(string $id): string {
    $id = trim($id);
    if (!preg_match('/^m\.([A-Fa-f0-9]{16})$/i', $id, $m)) {
        return '';
    }
    return 'm.' . strtoupper($m[1]);
}

/** Filesystem path to image bytes, or null. */
function fk_media_resolve(string $asset_id): ?string {
    $id = fk_media_safe_id($asset_id);
    if ($id === '') {
        return null;
    }
    foreach (fk_media_search_roots() as $root) {
        $hits = glob($root . '/' . $id . '.*') ?: [];
        foreach ($hits as $f) {
            if (preg_match('/\.json$/i', $f)) {
                continue;
            }
            if (is_file($f)) {
                return str_replace('\\', '/', $f);
            }
        }
    }
    return null;
}

function fk_media_mime(string $path): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match ($ext) {
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        default => 'application/octet-stream',
    };
}

/**
 * data: URI for in-page print (reliable; no extra request).
 * Empty if missing or over max_bytes.
 */
function fk_media_data_uri(string $asset_id, int $max_bytes = 4_000_000): string {
    $path = fk_media_resolve($asset_id);
    if ($path === null) {
        return '';
    }
    $bytes = (int) filesize($path);
    if ($bytes <= 0 || $bytes > $max_bytes) {
        // still offer door href via caller
        return '';
    }
    $mime = fk_media_mime($path);
    if (!str_starts_with($mime, 'image/')) {
        return '';
    }
    $bin = @file_get_contents($path);
    if ($bin === false || $bin === '') {
        return '';
    }
    return 'data:' . $mime . ';base64,' . base64_encode($bin);
}

/** Public URL for media door (fallback / large files). */
function fk_media_href(string $asset_id): string {
    $id = fk_media_safe_id($asset_id);
    if ($id === '') {
        return '';
    }
    return '/media?id=' . rawurlencode($id);
}

function fk_media_img_src(string $asset_id): string {
    $data = fk_media_data_uri($asset_id);
    if ($data !== '') {
        return $data;
    }
    if (fk_media_resolve($asset_id) !== null) {
        return fk_media_href($asset_id);
    }
    return '';
}

/**
 * Rewrite ![alt](media:ID) and bare media:ID mentions for HTML view.
 * Returns body HTML-ready fragments for fk_render_md pipeline: replace markdown
 * image lines with raw HTML <img> so we don't depend on md img support.
 */
function fk_media_rewrite_body_for_view(string $body): string {
    // ![alt](media:m.HEX) → HTML img (so print always works)
    $body = preg_replace_callback(
        '/!\[([^\]]*)\]\(\s*media:([a-zA-Z0-9.]+)\s*\)/u',
        static function ($m) {
            $alt = $m[1];
            $src = fk_media_img_src($m[2]);
            if ($src === '') {
                return '*[missing image: ' . $m[2] . ']*';
            }
            $a = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
            $s = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
            return "\n\n" . '<figure class="fk-media-fig"><img class="fk-md-img" src="' . $s . '" alt="' . $a . '"><figcaption>' . $a . '</figcaption></figure>' . "\n\n";
        },
        $body
    );
    return $body ?? '';
}

/**
 * Ensure a known legacy asset exists under SDK_MEDIA_ROOT (copy once).
 */
function fk_media_seed_from_legacy(string $asset_id): bool {
    $id = fk_media_safe_id($asset_id);
    if ($id === '') {
        return false;
    }
    if (fk_media_resolve($id) !== null && str_starts_with((string) fk_media_resolve($id), fk_media_root())) {
        return true;
    }
    $legacy = 'C:/Builds/my-pocket-internet/d/_MEDIA';
    $hits = glob($legacy . '/' . $id . '.*') ?: [];
    $ok = false;
    $destRoot = fk_media_root();
    foreach ($hits as $f) {
        $base = basename($f);
        $dest = $destRoot . '/' . $base;
        if (!is_file($dest)) {
            @copy($f, $dest);
        }
        $ok = is_file($dest) || $ok;
    }
    return $ok;
}
