<?php
/**
 * dossierDesk v0 — AB only · JSON bag (no myPI ledger).
 * Strip-mined from Builds/my-pocket-internet/t/tools/dossierDesk.
 */
declare(strict_types=1);

function dd_store_path(): string {
    $base = defined('SDK_ROOT') ? SDK_ROOT : dirname(__DIR__, 2);
    $dir = rtrim(str_replace('\\', '/', $base), '/') . '/work/dossier';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir . '/ab.dossier.json';
}

function dd_empty_bag(): array {
    return [
        'schema' => 'dossier.ab.v1',
        'station' => 'ab',
        'version' => 1,
        'updated' => time(),
        'persons' => [],
        'factions' => [],
        'memberships' => [],
        'notes' => [],
    ];
}

function dd_load(): array {
    $path = dd_store_path();
    if (!is_file($path)) {
        $bag = dd_empty_bag();
        dd_save($bag);
        return $bag;
    }
    try {
        $j = json_decode((string) file_get_contents($path), true);
    } catch (Throwable $e) {
        $j = null;
    }
    if (!is_array($j)) {
        $bag = dd_empty_bag();
        dd_save($bag);
        return $bag;
    }
    foreach (['persons', 'factions', 'memberships', 'notes'] as $k) {
        if (!isset($j[$k]) || !is_array($j[$k])) {
            $j[$k] = [];
        }
    }
    return $j;
}

function dd_save(array $bag): void {
    $bag['updated'] = time();
    $path = dd_store_path();
    $tmp = $path . '.tmp';
    file_put_contents(
        $tmp,
        json_encode($bag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
    );
    rename($tmp, $path);
}

function dd_id(string $prefix = 'dd'): string {
    return $prefix . '_' . bin2hex(random_bytes(4));
}

function dd_norm_status(string $st): string {
    $st = strtolower(trim($st));
    $ok = ['unsure', 'active', 'inactive', 'dissolved'];
    return in_array($st, $ok, true) ? $st : 'unsure';
}

/** Faction morality chip (CHG03) */
function dd_norm_morality(string $m): string {
    $m = strtolower(trim($m));
    $map = [
        'adversary' => 'adversary',
        'enemy' => 'adversary',
        'hostile' => 'adversary',
        'friendly' => 'friendly',
        'ally' => 'friendly',
        'friend' => 'friendly',
        'unsure' => 'unsure',
        'unknown' => 'unsure',
        'self-run' => 'self-run',
        'self_run' => 'self-run',
        'selfrun' => 'self-run',
        'self' => 'self-run',
    ];
    return $map[$m] ?? 'unsure';
}

/** @return list<string> */
function dd_morality_opts(): array {
    return ['adversary', 'friendly', 'unsure', 'self-run'];
}

/** @return list<string> */
function dd_split_list(string $raw): array {
    $parts = preg_split('/[,;]+/', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out;
}

function dd_person(array $bag, string $id): ?array {
    foreach ($bag['persons'] as $p) {
        if (($p['id'] ?? '') === $id) {
            return $p;
        }
    }
    return null;
}

function dd_faction(array $bag, string $id): ?array {
    foreach ($bag['factions'] as $f) {
        if (($f['id'] ?? '') === $id) {
            return $f;
        }
    }
    return null;
}

/** @return list<array> */
function dd_mems_for_person(array $bag, string $pid): array {
    $out = [];
    foreach ($bag['memberships'] as $m) {
        if (($m['person_id'] ?? '') === $pid) {
            $out[] = $m;
        }
    }
    return $out;
}

/** @return list<array> */
function dd_mems_for_faction(array $bag, string $fid): array {
    $out = [];
    foreach ($bag['memberships'] as $m) {
        if (($m['faction_id'] ?? '') === $fid) {
            $out[] = $m;
        }
    }
    return $out;
}

/** @return list<array{id:string,name:string}> */
function dd_faction_leaders(array $bag, string $fid): array {
    $out = [];
    foreach (dd_mems_for_faction($bag, $fid) as $m) {
        if (empty($m['is_leader'])) {
            continue;
        }
        $p = dd_person($bag, (string) ($m['person_id'] ?? ''));
        if ($p) {
            $out[] = ['id' => $p['id'], 'name' => (string) ($p['name'] ?? '?')];
        }
    }
    return $out;
}

/** @return list<array> */
function dd_notes_for(array $bag, ?string $pid, ?string $fid): array {
    $out = [];
    foreach ($bag['notes'] as $n) {
        if ($pid && ($n['person_id'] ?? '') === $pid) {
            $out[] = $n;
            continue;
        }
        if ($fid && ($n['faction_id'] ?? '') === $fid) {
            $out[] = $n;
        }
    }
    usort($out, static fn($a, $b) => (int) ($b['created'] ?? 0) <=> (int) ($a['created'] ?? 0));
    return $out;
}

function dd_filter_list(array $rows, string $q, string $nameKey = 'name'): array {
    $q = strtolower(trim($q));
    if ($q === '') {
        return $rows;
    }
    $out = [];
    foreach ($rows as $r) {
        $blob = strtolower(
            (string) ($r[$nameKey] ?? '') . ' ' .
            implode(' ', $r['akas'] ?? []) . ' ' .
            (string) ($r['body'] ?? '')
        );
        if (str_contains($blob, $q)) {
            $out[] = $r;
        }
    }
    return $out;
}

function dd_href(array $q = []): string {
    $base = ['room' => 'dossier', 'station' => 'ab'];
    if (function_exists('sdk_href')) {
        return sdk_href('dossier', array_merge(['station' => 'ab'], $q));
    }
    return '/?' . http_build_query(array_merge($base, $q));
}
