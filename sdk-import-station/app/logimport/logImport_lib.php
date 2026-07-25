<?php
/**
 * logImport helpers — tree-core catalog + immutable shard load.
 * Catalog: z/logs/tree_cores/catalog.json (from ledger/tree_core_catalog.py)
 */

if (!function_exists('logimport_paths')) {
    /**
     * sdk-import-station paths — glass-logs inputs + island work/ outputs.
     * No alphabet / echoSONAR required.
     */
    function logimport_paths(): array {
        if (!defined('SDK_OT_PATH')) {
            $cfg = dirname(__DIR__, 2) . '/config.php';
            if (is_file($cfg)) {
                require_once $cfg;
            }
        }
        if (defined('SDK_OT_PATH')) {
            return [
                'ot' => SDK_OT_PATH,
                'nt' => SDK_NT_DIR,
                'catalog' => SDK_CATALOG,
                'glass' => SDK_GLASS_DIR,
                'wip' => SDK_WIP_DIR,
                'exports' => SDK_EXPORTS_DIR,
            ];
        }
        // Fallback excavate-only (should not hit in normal ROM run)
        $root = 'C:/Builds/my-pocket-internet';
        return [
            'ot' => $root . '/z/conversations.json',
            'nt' => $root . '/z/logs/NEW_MASTER_USE_THESE',
            'catalog' => $root . '/z/logs/tree_cores/catalog.json',
            'glass' => $root . '/z/logs/tree_cores/glass',
            'wip' => $root . '/z/logs/tree_cores/wip',
            'exports' => $root . '/z/logs/tree_cores/exports',
        ];
    }

    function logimport_load_catalog(): ?array {
        static $cache = null;
        static $loaded = false;
        if ($loaded) {
            return $cache;
        }
        $loaded = true;
        $p = logimport_paths()['catalog'];
        if (!is_file($p)) {
            $cache = null;
            return null;
        }
        // catalog is metadata only (~few MB); still avoid double-read per request
        $j = json_decode((string) file_get_contents($p), true);
        $cache = is_array($j) ? $j : null;
        return $cache;
    }

    function logimport_safe_glass_name(string $conversationId): string {
        $s = preg_replace('/[^a-zA-Z0-9_-]/', '_', $conversationId) ?? '';
        $s = substr($s, 0, 120);
        return $s !== '' ? $s : 'unknown';
    }

    function logimport_face_key($face): string {
        $s = trim((string) $face);
        if ($s === '') {
            return '';
        }
        if (ctype_digit($s)) {
            return sprintf('%03d', (int) $s);
        }
        return $s;
    }

    function logimport_core_by_face($face): ?array {
        $cat = logimport_load_catalog();
        if (!$cat) {
            return null;
        }
        $key = logimport_face_key($face);
        $n = ctype_digit(trim((string) $face)) ? (int) $face : null;
        foreach ($cat['cores'] ?? [] as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (($c['face_id'] ?? '') === $key) {
                return $c;
            }
            if ($n !== null && (int) ($c['n'] ?? 0) === $n) {
                return $c;
            }
        }
        return null;
    }

    function logimport_load_conversation(array $core): ?array {
        $paths = logimport_paths();
        $cid = (string) ($core['conversation_id'] ?? '');
        if ($cid === '') {
            return null;
        }

        // Preferred: one-chat glass extract (never load 230MB OT or 50MB NT shard)
        $glassDir = $paths['glass'];
        $candidates = [];
        if (!empty($core['glass_basename'])) {
            $candidates[] = $glassDir . '/' . $core['glass_basename'];
        }
        $candidates[] = $glassDir . '/' . logimport_safe_glass_name($cid) . '.json';
        foreach ($candidates as $gp) {
            if (is_file($gp)) {
                $one = json_decode((string) file_get_contents($gp), true);
                return is_array($one) ? $one : null;
            }
        }

        // No glass extract — refuse to load full OT (OOM). Ask operator to rebuild catalog.
        $exportKey = (string) ($core['load_export_key'] ?? $core['export_key'] ?? 'nt');
        if ($exportKey === 'ot') {
            error_log('logImport: missing glass extract for OT core ' . $cid . ' — run python ledger/tree_core_catalog.py');
            return null;
        }

        // NT shard fallback (smaller risk than OT, still heavy — prefer glass)
        $cat = logimport_load_catalog();
        $export = $paths['nt'];
        if (is_array($cat) && !empty($cat['sources']['nt']['path']) && is_dir($cat['sources']['nt']['path'])) {
            $export = rtrim(str_replace('\\', '/', (string) $cat['sources']['nt']['path']), '/');
        }
        $shardName = (string) ($core['load_shard'] ?? $core['shard'] ?? '');
        $shard = $export . '/' . $shardName;
        if (!is_file($shard)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($shard), true);
        if (!is_array($data)) {
            return null;
        }
        foreach ($data as $conv) {
            if (!is_array($conv)) {
                continue;
            }
            $id = (string) ($conv['conversation_id'] ?? $conv['id'] ?? '');
            if ($id === $cid) {
                return $conv;
            }
        }
        return null;
    }

    /**
     * ChatGPT parts may be strings OR structured objects (images, etc.).
     * Never cast arrays to string (PHP 8+ warning / noise).
     */
    function logimport_part_to_text($part): string {
        if ($part === null) {
            return '';
        }
        if (is_string($part)) {
            return $part;
        }
        if (is_int($part) || is_float($part) || is_bool($part)) {
            return (string) $part;
        }
        if (!is_array($part)) {
            return '';
        }
        // Common structured part shapes in exports
        $ctype = (string) ($part['content_type'] ?? $part['type'] ?? '');
        if ($ctype === 'image_asset_pointer' || isset($part['asset_pointer'])) {
            $ptr = (string) ($part['asset_pointer'] ?? '');
            $h = (int) ($part['height'] ?? 0);
            $w = (int) ($part['width'] ?? 0);
            $dim = ($w > 0 && $h > 0) ? " {$w}×{$h}" : '';
            return '[image' . $dim . ($ptr !== '' ? ' · ' . $ptr : '') . ']';
        }
        if ($ctype === 'audio_asset_pointer' || isset($part['audio_asset_pointer'])) {
            return '[audio]';
        }
        if (isset($part['text']) && is_string($part['text'])) {
            return $part['text'];
        }
        if (isset($part['value']) && is_string($part['value'])) {
            return $part['value'];
        }
        // nested parts
        if (isset($part['parts']) && is_array($part['parts'])) {
            $bits = [];
            foreach ($part['parts'] as $sub) {
                $t = logimport_part_to_text($sub);
                if ($t !== '') {
                    $bits[] = $t;
                }
            }
            return implode("\n", $bits);
        }
        // last resort: compact JSON label, not a full dump
        if ($ctype !== '') {
            return '[' . $ctype . ']';
        }
        return '[attachment]';
    }

    /**
     * @return list<array{seq:int,message_id:string,role:string,create_time:?float,text:string}>
     */
    function logimport_extract_messages(array $conv): array {
        $mapping = $conv['mapping'] ?? null;
        if (!is_array($mapping)) {
            return [];
        }
        $rows = [];
        foreach ($mapping as $mid => $node) {
            if (!is_array($node)) {
                continue;
            }
            $msg = $node['message'] ?? null;
            if (!is_array($msg)) {
                continue;
            }
            $author = is_array($msg['author'] ?? null) ? $msg['author'] : [];
            $role = strtolower((string) ($author['role'] ?? ''));
            if ($role !== 'user' && $role !== 'assistant') {
                continue;
            }
            $content = is_array($msg['content'] ?? null) ? $msg['content'] : [];
            $parts = $content['parts'] ?? [];
            if (!is_array($parts)) {
                // some messages use content as plain string
                if (is_string($content) && trim($content) !== '') {
                    $parts = [$content];
                } else {
                    continue;
                }
            }
            $texts = [];
            foreach ($parts as $p) {
                $s = logimport_part_to_text($p);
                if (trim($s) !== '') {
                    $texts[] = $s;
                }
            }
            if (!$texts) {
                continue;
            }
            $ct = $msg['create_time'] ?? null;
            $create = is_numeric($ct) ? (float) $ct : null;
            $rows[] = [
                'message_id' => (string) $mid,
                'role' => $role,
                'create_time' => $create,
                'text' => implode("\n", $texts),
            ];
        }
        usort($rows, static function ($a, $b) {
            $an = $a['create_time'] === null ? 1 : 0;
            $bn = $b['create_time'] === null ? 1 : 0;
            if ($an !== $bn) {
                return $an - $bn;
            }
            $ac = $a['create_time'] ?? 0.0;
            $bc = $b['create_time'] ?? 0.0;
            if ($ac == $bc) {
                return strcmp($a['message_id'], $b['message_id']);
            }
            return $ac <=> $bc;
        });
        foreach ($rows as $i => &$r) {
            $r['seq'] = $i;
        }
        unset($r);
        return $rows;
    }

    function logimport_wip_path(string $face): string {
        $key = logimport_face_key($face);
        $dir = logimport_paths()['wip'];
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir . '/wip_' . $key . '.json';
    }

    function logimport_wip_load(string $face): ?array {
        $p = logimport_wip_path($face);
        if (!is_file($p)) {
            return null;
        }
        $j = json_decode((string) file_get_contents($p), true);
        return is_array($j) ? $j : null;
    }

    function logimport_wip_save(string $face, array $wip): bool {
        $p = logimport_wip_path($face);
        $wip['face_id'] = logimport_face_key($face);
        $wip['saved_at'] = time();
        return file_put_contents(
            $p,
            json_encode($wip, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ) !== false;
    }

    /**
     * List WIP sidecars for the Active bay (one file per core you've touched).
     * Faces with sealed export status "complete" are omitted — WIP stays on disk
     * so "reopen log" / desk can still load encode book & cuts.
     *
     * @return list<array{face_id:string,yard_title:string,notes_preview:string,n_segments:int,saved_at:int,glass_title:string,testament_tag:string}>
     */
    function logimport_list_wips(): array {
        $dir = logimport_paths()['wip'];
        if (!is_dir($dir)) {
            return [];
        }
        $cat = logimport_load_catalog();
        $byFace = [];
        if (is_array($cat)) {
            foreach ($cat['cores'] ?? [] as $c) {
                if (is_array($c) && !empty($c['face_id'])) {
                    $byFace[(string) $c['face_id']] = $c;
                }
            }
        }
        $out = [];
        foreach (glob($dir . '/wip_*.json') ?: [] as $file) {
            $base = basename($file);
            if (!preg_match('/^wip_([0-9A-Za-z_-]+)\.json$/', $base, $m)) {
                continue;
            }
            $face = logimport_face_key($m[1]);
            // Complete export → drop from active list
            $st = logimport_export_status_load($face);
            if (($st['status'] ?? '') === 'complete') {
                continue;
            }
            $j = json_decode((string) file_get_contents($file), true);
            if (!is_array($j)) {
                continue;
            }
            $core = $byFace[$face] ?? null;
            $segs = is_array($j['segments'] ?? null) ? $j['segments'] : [];
            $notes = trim((string) ($j['notes'] ?? ''));
            $preview = $notes;
            if (function_exists('mb_substr')) {
                $preview = mb_substr($notes, 0, 80);
            } else {
                $preview = substr($notes, 0, 80);
            }
            if (strlen($notes) > 80) {
                $preview .= '…';
            }
            $out[] = [
                'face_id' => $face,
                'yard_title' => trim((string) ($j['yard_title'] ?? '')),
                'notes_preview' => $preview,
                'n_segments' => count($segs),
                'saved_at' => (int) ($j['saved_at'] ?? filemtime($file) ?: 0),
                'glass_title' => is_array($core) ? (string) ($core['title'] ?? '') : '',
                'testament_tag' => is_array($core) ? (string) ($core['testament_tag'] ?? '') : '',
            ];
        }
        usort($out, static fn($a, $b) => ($b['saved_at'] <=> $a['saved_at']));
        return $out;
    }

    /**
     * Workflow status for a sealed face (parent). Sidecar — not inside part wood.
     * Path: work/exports/status_{face}.json
     * Holds mint-once import_order (prophetic release 001…) and testament chip.
     */
    function logimport_export_status_path(string $face): string {
        $key = logimport_face_key($face);
        $dir = logimport_paths()['exports'];
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir . '/status_' . $key . '.json';
    }

    /** House registry of mint-once import orders across faces. */
    function logimport_import_order_registry_path(): string {
        $dir = logimport_paths()['exports'];
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir . '/import_order_registry.json';
    }

    /**
     * OT | NT | BN (both / hybrid). Books outside bible later: vault nick codes.
     */
    function logimport_testament_chip(array $core): string {
        $tag = strtoupper(trim((string) ($core['testament_tag'] ?? '')));
        $key = strtolower(trim((string) ($core['load_export_key'] ?? $core['export_key'] ?? '')));
        if ($tag === 'OT+NT' || $tag === 'NT+OT' || $tag === 'BOTH' || $tag === 'BN') {
            return 'BN';
        }
        if ($tag === 'OT' || $key === 'ot') {
            return 'OT';
        }
        if ($tag === 'NT' || $key === 'nt') {
            return 'NT';
        }
        if (str_contains($tag, 'OT') && str_contains($tag, 'NT')) {
            return 'BN';
        }
        return $key === 'ot' ? 'OT' : ($key === 'nt' ? 'NT' : 'BN');
    }

    /**
     * Mint-once prophetic import order (001…) for a face.
     * Assigned at first seal or first complete — never reassigned.
     */
    function logimport_import_order_ensure(string $face): int {
        $key = logimport_face_key($face);
        $st = logimport_export_status_load($key);
        if (!empty($st['import_order']) && (int) $st['import_order'] > 0) {
            return (int) $st['import_order'];
        }
        $regPath = logimport_import_order_registry_path();
        $reg = ['next' => 1, 'faces' => []];
        if (is_file($regPath)) {
            $j = json_decode((string) file_get_contents($regPath), true);
            if (is_array($j)) {
                $reg['next'] = max(1, (int) ($j['next'] ?? 1));
                $reg['faces'] = is_array($j['faces'] ?? null) ? $j['faces'] : [];
            }
        }
        if (!empty($reg['faces'][$key]) && (int) $reg['faces'][$key] > 0) {
            $order = (int) $reg['faces'][$key];
        } else {
            $order = (int) $reg['next'];
            $reg['faces'][$key] = $order;
            $reg['next'] = $order + 1;
            file_put_contents(
                $regPath,
                json_encode($reg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
        }
        // Persist onto status sidecar (merge)
        $st['import_order'] = $order;
        $st['face_id'] = $key;
        $st['updated_at'] = time();
        if (empty($st['status'])) {
            $st['status'] = 'in_progress';
        }
        file_put_contents(
            logimport_export_status_path($key),
            json_encode([
                'face_id' => $key,
                'status' => $st['status'] ?? 'in_progress',
                'import_order' => $order,
                'completed_at' => $st['completed_at'] ?? null,
                'updated_at' => $st['updated_at'],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        return $order;
    }

    /** 001-OT-008 or 001-OT-008.1 */
    function logimport_export_id(int $importOrder, string $testament, string $face, ?int $part = null): string {
        $ord = sprintf('%03d', max(1, $importOrder));
        $test = strtoupper($testament);
        if (!in_array($test, ['OT', 'NT', 'BN'], true)) {
            $test = 'BN';
        }
        $faceKey = logimport_face_key($face);
        $id = $ord . '-' . $test . '-' . $faceKey;
        if ($part !== null && $part > 0) {
            $id .= '.' . $part;
        }
        return $id;
    }

    /** 001-OT-008.1.23 — bag_seq is index inside this glassbox section (0-based). */
    function logimport_msg_chip(string $exportId, int $bagSeq): string {
        return $exportId . '.' . max(0, $bagSeq);
    }

    /**
     * @return array{status:string,completed_at:?int,updated_at:int,import_order:?int}
     */
    function logimport_export_status_load(string $face): array {
        $p = logimport_export_status_path($face);
        $defaults = [
            'status' => 'in_progress',
            'completed_at' => null,
            'updated_at' => 0,
            'import_order' => null,
        ];
        if (!is_file($p)) {
            return $defaults;
        }
        $j = json_decode((string) file_get_contents($p), true);
        if (!is_array($j)) {
            return $defaults;
        }
        $st = (string) ($j['status'] ?? 'in_progress');
        if ($st !== 'complete' && $st !== 'in_progress') {
            $st = 'in_progress';
        }
        $order = isset($j['import_order']) && is_numeric($j['import_order'])
            ? (int) $j['import_order']
            : null;
        return [
            'status' => $st,
            'completed_at' => isset($j['completed_at']) && is_numeric($j['completed_at'])
                ? (int) $j['completed_at']
                : null,
            'updated_at' => (int) ($j['updated_at'] ?? 0),
            'import_order' => $order && $order > 0 ? $order : null,
        ];
    }

    /**
     * @param 'in_progress'|'complete' $status
     */
    function logimport_export_status_set(string $face, string $status): bool {
        $key = logimport_face_key($face);
        if ($key === '') {
            return false;
        }
        if ($status !== 'complete' && $status !== 'in_progress') {
            return false;
        }
        $prev = logimport_export_status_load($key);
        // Prophetic order: mint-once on first complete (or keep existing from seal)
        $order = $prev['import_order'] ?? null;
        if ($status === 'complete' || $order === null) {
            $order = logimport_import_order_ensure($key);
        }
        $now = time();
        $payload = [
            'face_id' => $key,
            'status' => $status,
            'import_order' => $order,
            'completed_at' => $status === 'complete' ? $now : null,
            'updated_at' => $now,
        ];
        return file_put_contents(
            logimport_export_status_path($key),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ) !== false;
    }

    /**
     * Submitted exports: sealed snapshot of WIP + transformed messages.
     * Path: {order}-{OT|NT|BN}-{face}[.{part}].glassbox  (+ legacy export_*.json)
     * Glass is never modified.
     *
     * @return list<array{face_id:string,path:string,saved_at:int,title:string,part:?int,part_count:?int,parent_face:string,glass_title:string,yard_title:string,basename:string,export_id?:string}>
     */
    function logimport_list_exports(): array {
        $dir = logimport_paths()['exports'];
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        $files = array_merge(
            glob($dir . '/*.glassbox') ?: [],
            glob($dir . '/export_*.json') ?: []
        );
        foreach ($files as $file) {
            $j = json_decode((string) file_get_contents($file), true);
            $meta = is_array($j) ? $j : [];
            $part = array_key_exists('part', $meta) && $meta['part'] !== null
                ? (int) $meta['part']
                : null;
            if ($part !== null && $part < 1) {
                $part = null;
            }
            $partCount = isset($meta['part_count']) ? (int) $meta['part_count'] : null;
            $title = (string) ($meta['title'] ?? $meta['part_title'] ?? $meta['yard_title'] ?? basename($file));
            $faceId = (string) ($meta['face_id'] ?? '');
            $parent = (string) ($meta['parent_face'] ?? '');
            $exportId = (string) ($meta['export_id'] ?? pathinfo($file, PATHINFO_FILENAME));
            if ($parent === '') {
                // export_008.1 or 001-OT-008.1 → parent 008 when meta missing
                if (preg_match('/^(.+)\.(\d+)$/', $faceId, $m)) {
                    $parent = $m[1];
                    if ($part === null) {
                        $part = (int) $m[2];
                    }
                } else {
                    $parent = $faceId;
                }
            }
            $out[] = [
                'face_id' => $faceId,
                'export_id' => $exportId,
                'path' => $file,
                'basename' => basename($file),
                'saved_at' => (int) ($meta['exported_at'] ?? filemtime($file) ?: 0),
                'title' => $title,
                'part' => $part,
                'part_count' => $partCount,
                'parent_face' => $parent,
                'glass_title' => (string) ($meta['glass_title'] ?? ''),
                'yard_title' => (string) ($meta['yard_title'] ?? ''),
            ];
        }
        usort($out, static function ($a, $b) {
            $pa = (string) ($a['parent_face'] ?? $a['face_id'] ?? '');
            $pb = (string) ($b['parent_face'] ?? $b['face_id'] ?? '');
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            $na = (int) ($a['part'] ?? 0);
            $nb = (int) ($b['part'] ?? 0);
            if ($na !== $nb) {
                return $na <=> $nb;
            }
            return ($b['saved_at'] <=> $a['saved_at']);
        });
        return $out;
    }

    /**
     * Group sealed files by parent face for the exports bay UI.
     *
     * @return list<array{
     *   parent_face:string,
     *   glass_title:string,
     *   yard_title:string,
     *   display_title:string,
     *   part_count:int,
     *   exported_at:int,
     *   status:string,
     *   completed_at:?int,
     *   parts:list<array>
     * }>
     */
    function logimport_list_export_groups(): array {
        $flat = logimport_list_exports();
        $groups = [];
        foreach ($flat as $ex) {
            $pf = (string) ($ex['parent_face'] ?? $ex['face_id'] ?? '');
            if ($pf === '') {
                $pf = 'unknown';
            }
            if (!isset($groups[$pf])) {
                $groups[$pf] = [
                    'parent_face' => $pf,
                    'glass_title' => (string) ($ex['glass_title'] ?? ''),
                    'yard_title' => (string) ($ex['yard_title'] ?? ''),
                    'exported_at' => (int) ($ex['saved_at'] ?? 0),
                    'parts' => [],
                ];
            }
            if (($ex['glass_title'] ?? '') !== '' && $groups[$pf]['glass_title'] === '') {
                $groups[$pf]['glass_title'] = (string) $ex['glass_title'];
            }
            if (($ex['yard_title'] ?? '') !== '' && $groups[$pf]['yard_title'] === '') {
                $groups[$pf]['yard_title'] = (string) $ex['yard_title'];
            }
            $groups[$pf]['exported_at'] = max(
                (int) $groups[$pf]['exported_at'],
                (int) ($ex['saved_at'] ?? 0)
            );
            $groups[$pf]['parts'][] = $ex;
        }

        $out = [];
        foreach ($groups as $g) {
            $nParts = count($g['parts']);
            // Prefer declared part_count from any part file
            $declared = 0;
            foreach ($g['parts'] as $p) {
                $declared = max($declared, (int) ($p['part_count'] ?? 0));
            }
            $partCount = max($nParts, $declared > 0 ? $declared : $nParts);
            $st = logimport_export_status_load($g['parent_face']);
            $yard = trim((string) $g['yard_title']);
            $glass = trim((string) $g['glass_title']);
            $display = $glass !== '' ? $glass : ($yard !== '' ? $yard : 'untitled');
            $out[] = [
                'parent_face' => $g['parent_face'],
                'glass_title' => $glass,
                'yard_title' => $yard,
                'display_title' => $display,
                'part_count' => $partCount,
                'exported_at' => (int) $g['exported_at'],
                'status' => $st['status'],
                'completed_at' => $st['completed_at'],
                'parts' => $g['parts'],
            ];
        }

        // Newest seal groups first
        usort($out, static fn($a, $b) => ($b['exported_at'] <=> $a['exported_at']));
        return $out;
    }

    /**
     * One export group for a parent face, or null if no sealed files.
     * Status-only complete faces (no files) still return a stub when $allowStatusOnly.
     */
    function logimport_export_group_by_face(string $face, bool $allowStatusOnly = true): ?array {
        $key = logimport_face_key($face);
        if ($key === '') {
            return null;
        }
        foreach (logimport_list_export_groups() as $g) {
            if ((string) ($g['parent_face'] ?? '') === $key) {
                return $g;
            }
        }
        if (!$allowStatusOnly) {
            return null;
        }
        $st = logimport_export_status_load($key);
        if (($st['status'] ?? '') !== 'complete') {
            return null;
        }
        $core = logimport_core_by_face($key);
        $glass = is_array($core) ? (string) ($core['title'] ?? '') : '';
        $wip = logimport_wip_load($key);
        $yard = is_array($wip) ? trim((string) ($wip['yard_title'] ?? '')) : '';
        return [
            'parent_face' => $key,
            'glass_title' => $glass,
            'yard_title' => $yard,
            'display_title' => $glass !== '' ? $glass : ($yard !== '' ? $yard : 'untitled'),
            'part_count' => 0,
            'exported_at' => (int) ($st['completed_at'] ?? $st['updated_at'] ?? 0),
            'status' => 'complete',
            'completed_at' => $st['completed_at'],
            'parts' => [],
        ];
    }

    /**
     * Glassbox path: {order}-{OT|NT|BN}-{face}.glassbox
     *             or {order}-{OT|NT|BN}-{face}.{part}.glassbox
     * Legacy export_*.json still cleared by clear_stale.
     *
     * @param int|null $part 1-based part number; null = whole-face monolithic file
     */
    function logimport_export_path(
        string $face,
        ?int $part = null,
        ?int $importOrder = null,
        ?string $testament = null
    ): string {
        $key = logimport_face_key($face);
        $dir = logimport_paths()['exports'];
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $order = $importOrder ?? logimport_import_order_ensure($key);
        $test = $testament ?? 'BN';
        $id = logimport_export_id($order, $test, $key, $part);
        return $dir . '/' . $id . '.glassbox';
    }

    /**
     * Remove stale seal files when re-exporting (monolith ↔ multi-part).
     * Clears both legacy export_*.json and *.glassbox for this face.
     */
    function logimport_export_clear_stale(string $faceKey, int $keepParts): void {
        $dir = logimport_paths()['exports'];
        if (!is_dir($dir) || $faceKey === '') {
            return;
        }
        $faceKey = logimport_face_key($faceKey);
        // Always drop legacy export_*.json for this face (migrated to .glassbox)
        @unlink($dir . '/export_' . $faceKey . '.json');
        foreach (glob($dir . '/export_' . $faceKey . '.*.json') ?: [] as $file) {
            @unlink($file);
        }
        // New *.glassbox — match *-FACE.glassbox and *-FACE.N.glassbox
        $reMono = '/^\d{3}-(OT|NT|BN)-' . preg_quote($faceKey, '/') . '\.glassbox$/';
        $rePart = '/^\d{3}-(OT|NT|BN)-' . preg_quote($faceKey, '/') . '\.(\d+)\.glassbox$/';
        foreach (glob($dir . '/*.glassbox') ?: [] as $file) {
            $base = basename($file);
            if (preg_match($rePart, $base, $m)) {
                $n = (int) $m[2];
                if ($keepParts < 2 || $n < 1 || $n > $keepParts) {
                    @unlink($file);
                }
            } elseif (preg_match($reMono, $base)) {
                if ($keepParts >= 2) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Public encode face for sealed export — never ship original / also spellings.
     *
     * @return list<array{code:string,alias:string}>
     */
    function logimport_encodes_public(?array $wip): array {
        $out = [];
        foreach (logimport_encodes_list($wip) as $e) {
            $out[] = [
                'code' => (string) ($e['code'] ?? ''),
                'alias' => (string) ($e['alias'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Redaction summary for sealed export — no private phrase originals.
     *
     * @return list<array{kind:string,seq?:int,label?:string}>
     */
    function logimport_redactions_public(?array $wip): array {
        $out = [];
        foreach (logimport_redactions_list($wip) as $r) {
            if (($r['kind'] ?? '') === 'message') {
                $out[] = [
                    'kind' => 'message',
                    'seq' => (int) ($r['seq'] ?? 0),
                    'label' => (string) ($r['label'] ?? ''),
                ];
            } else {
                // phrase redaction applied in text; do not export the barred phrase
                $out[] = [
                    'kind' => 'phrase',
                    'label' => (string) ($r['label'] ?? 'phrase'),
                ];
            }
        }
        return $out;
    }

    /**
     * Load ledger rail (big db). Fail soft if unavailable.
     */
    function logimport_ledger_boot(): bool {
        if (function_exists('mypi_ledger_create_post')) {
            return true;
        }
        $candidates = [];
        if (defined('ROUTE_TO_SYSTEMS')) {
            $candidates[] = ROUTE_TO_SYSTEMS . 'ledger/Ledger.php';
        }
        if (defined('echoSONAR')) {
            $root = rtrim(str_replace('\\', '/', (string) echoSONAR), '/');
            $candidates[] = $root . '/k/systems/ledger/Ledger.php';
        }
        $candidates[] = dirname(__DIR__, 3) . '/k/systems/ledger/Ledger.php';
        foreach ($candidates as $p) {
            if (is_string($p) && is_file($p)) {
                require_once $p;
                break;
            }
        }
        return function_exists('mypi_ledger_create_post');
    }

    /**
     * Place for IO terminal notes (sky if present, else io defaults).
     *
     * @return array{sys:string,dom:string,room:string,mod:string,place_label:string,agent:string}
     */
    function logimport_ledger_place(string $roomHint = 'exports'): array {
        $sys = '';
        $dom = 'io';
        $room = $roomHint;
        $mod = '';
        $place_label = 'terminal io';
        $agent = 'io';
        if (function_exists('mypi_ledger_place_from_sky')) {
            $p = mypi_ledger_place_from_sky();
            $sys = (string) ($p['sys'] ?? '');
            $dom = (string) (($p['dom'] ?? '') !== '' ? $p['dom'] : $dom);
            $room = (string) (($p['room'] ?? '') !== '' ? $p['room'] : $room);
            $mod = (string) ($p['mod'] ?? '');
            $place_label = (string) (($p['place_label'] ?? '') !== '' ? $p['place_label'] : $place_label);
        }
        if (function_exists('mypi_auth_agent')) {
            $a = mypi_auth_agent();
            if (is_array($a) && !empty($a['slug'])) {
                $agent = (string) $a['slug'];
            }
        } elseif (function_exists('mypi_auth_check') && mypi_auth_check() && function_exists('mypi_auth_agent')) {
            $a = mypi_auth_agent();
            if (is_array($a) && !empty($a['slug'])) {
                $agent = (string) $a['slug'];
            }
        }
        if ($sys === '' && defined('echoSONAR')) {
            $sys = 'mypi';
        }
        return compact('sys', 'dom', 'room', 'mod', 'place_label', 'agent');
    }

    /**
     * Big-db awareness: export sealed (public fields only — no encode originals).
     *
     * @param list<string> $paths
     * @return array{ok:bool,c_uid?:string,error?:string}
     */
    function logimport_ledger_note_export(
        string $faceKey,
        array $core,
        array $wip,
        int $parts,
        array $paths = []
    ): array {
        if (!logimport_ledger_boot()) {
            return ['ok' => false, 'error' => 'ledger unavailable'];
        }
        $place = logimport_ledger_place('exports');
        $yard = trim((string) ($wip['yard_title'] ?? ''));
        $glass = (string) ($core['title'] ?? '');
        $title = $yard !== '' ? $yard : ($glass !== '' ? $glass : ('face ' . $faceKey));
        $n = max(1, $parts);
        $topic = 'export sealed · ' . $faceKey
            . ($n > 1 ? (' · ' . $n . ' parts') : '');
        $bodyLines = [
            'IO seal export',
            'face: ' . $faceKey,
            'title: ' . $title,
        ];
        if ($glass !== '' && $glass !== $title) {
            $bodyLines[] = 'glass: ' . $glass;
        }
        $bodyLines[] = 'parts: ' . $n;
        if ($n > 1) {
            $bodyLines[] = 'part faces: ' . $faceKey . '.1 … ' . $faceKey . '.' . $n;
        }
        $bases = [];
        foreach ($paths as $p) {
            if (is_string($p) && $p !== '') {
                $bases[] = basename($p);
            }
        }
        if ($bases) {
            $bodyLines[] = 'files: ' . implode(', ', $bases);
        }
        $bodyLines[] = 'private encode book stays in wip · not in this crate';
        $r = mypi_ledger_create_post([
            'topic' => $topic,
            'body' => implode("\n", $bodyLines),
            'kind' => 'log_export',
            'scale' => 'leaf',
            'tool' => 'logImport',
            'tool_version' => 2,
            'face_id' => $faceKey,
            'glass_title' => $glass,
            'yard_title' => $yard !== '' ? $yard : $title,
            'sys' => $place['sys'],
            'dom' => $place['dom'],
            'room' => $place['room'],
            'mod' => $place['mod'],
            'place_label' => $place['place_label'],
            'agent' => $place['agent'],
            'actor' => $place['agent'],
            'tags_raw' => 'logexport ' . $faceKey,
            'meta' => [
                'event' => 'seal_export',
                'parent_face' => $faceKey,
                'part_count' => $n,
                'export_basenames' => $bases,
                'via' => 'io',
            ],
        ]);
        if (empty($r['ok'])) {
            return ['ok' => false, 'error' => (string) ($r['error'] ?? 'ledger write failed')];
        }
        return ['ok' => true, 'c_uid' => (string) ($r['c_uid'] ?? '')];
    }

    /**
     * Big-db awareness: VEN ship/modify (public code + alias only — never matches/original).
     *
     * @param 'ship'|'modify' $event
     * @return array{ok:bool,c_uid?:string,error?:string}
     */
    function logimport_ledger_note_ven(
        string $kven,
        string $alias,
        string $via = 'logImport',
        string $faceKey = '',
        string $event = 'ship'
    ): array {
        if (!logimport_ledger_boot()) {
            return ['ok' => false, 'error' => 'ledger unavailable'];
        }
        $domHint = (stripos($via, 'ven') !== false || stripos($via, 'rx') !== false) ? 'rx' : 'io';
        $place = logimport_ledger_place($domHint === 'rx' ? 'ven' : 'import');
        if ($domHint === 'rx' && ($place['dom'] === 'io' || $place['dom'] === '')) {
            $place['dom'] = 'rx';
        }
        $event = strtolower(trim($event)) === 'modify' ? 'modify' : 'ship';
        $verb = $event === 'modify' ? 'MODIFY' : 'SHIP';
        $kind = $event === 'modify' ? 'ven_modify' : 'ven_ship';
        $kven = strtoupper(trim($kven));
        $alias = trim($alias);
        $topic = 'VEN ' . $verb . ' · ' . $kven . ($alias !== '' ? (' · ' . $alias) : '');
        $bodyLines = [
            'VEN registry ' . $verb,
            'code: ' . $kven,
        ];
        if ($alias !== '') {
            $bodyLines[] = 'alias: ' . $alias;
        }
        $bodyLines[] = 'via: ' . $via;
        if ($faceKey !== '') {
            $bodyLines[] = 'from face: ' . $faceKey;
        }
        $bodyLines[] = 'private matches stay in ven registry / wip · not in this crate';
        $r = mypi_ledger_create_post([
            'topic' => $topic,
            'body' => implode("\n", $bodyLines),
            'kind' => $kind,
            'scale' => 'leaf',
            'tool' => $via === 'venDesk' ? 'venDesk' : 'logImport',
            'tool_version' => 1,
            'face_id' => $faceKey,
            'sys' => $place['sys'],
            'dom' => $place['dom'],
            'room' => $place['room'],
            'mod' => $place['mod'],
            'place_label' => $place['place_label'],
            'agent' => $place['agent'],
            'actor' => $place['agent'],
            'tags_raw' => 'ven ' . $kven . ($event === 'modify' ? ' venmodify' : ' venship'),
            'meta' => [
                'event' => $event === 'modify' ? 'ven_modify' : 'ven_ship',
                'kven' => $kven,
                'alias' => $alias,
                'via' => $via,
                'parent_face' => $faceKey,
            ],
        ]);
        if (empty($r['ok'])) {
            return ['ok' => false, 'error' => (string) ($r['error'] ?? 'ledger write failed')];
        }
        return ['ok' => true, 'c_uid' => (string) ($r['c_uid'] ?? '')];
    }

    /**
     * Span min/max create_time from a message list.
     *
     * @param list<array> $messages
     * @return array{0:?float,1:?float}
     */
    function logimport_messages_span(array $messages): array {
        $tMin = null;
        $tMax = null;
        foreach ($messages as $m) {
            $ctF = $m['create_time'] ?? null;
            if (!is_numeric($ctF)) {
                continue;
            }
            $ctF = (float) $ctF;
            $tMin = $tMin === null ? $ctF : min($tMin, $ctF);
            $tMax = $tMax === null ? $ctF : max($tMax, $ctF);
        }
        return [$tMin, $tMax];
    }

    /**
     * Seal current WIP to work/exports/ as .glassbox wood.
     * Filename: {import_order}-{OT|NT|BN}-{face}[.{part}].glassbox
     * Message chip: {export_id}.{bag_seq}  (bag_seq = index inside this glassbox section)
     * Bodies always encode+redact sanitized. Does not write glass.
     *
     * @return array{ok:bool,path:string,paths:list<string>,parts:int,c_uid:?string,error:?string}
     */
    function logimport_export_submit(string $face, array $core, ?array $wip = null): array {
        $faceKey = logimport_face_key($face);
        $wip = is_array($wip) ? $wip : logimport_wip_load($faceKey);
        if (!is_array($wip)) {
            return ['ok' => false, 'path' => '', 'paths' => [], 'parts' => 0, 'c_uid' => null, 'error' => 'no wip to export — save wip first'];
        }

        $importOrder = logimport_import_order_ensure($faceKey);
        $testament = logimport_testament_chip($core);

        $messagesOut = [];
        $conv = logimport_load_conversation($core);
        $lastSeq = -1;
        if ($conv) {
            $msgs = logimport_extract_messages($conv);
            $doRedact = true;
            $doEncode = true;
            foreach ($msgs as $m) {
                $seq = (int) ($m['seq'] ?? 0);
                if ($seq > $lastSeq) {
                    $lastSeq = $seq;
                }
                $text = (string) ($m['text'] ?? '');
                if (function_exists('logimport_transform_text')) {
                    $tx = logimport_transform_text($text, $seq, $wip, $doRedact, $doEncode, false);
                    $text = is_array($tx) ? (string) ($tx['text'] ?? $text) : (string) $tx;
                }
                $ct = $m['create_time'] ?? null;
                $ctF = is_numeric($ct) ? (float) $ct : null;
                $iso = null;
                if ($ctF !== null && $ctF > 0) {
                    try {
                        $iso = gmdate('c', (int) floor($ctF));
                    } catch (Throwable $e) {
                        $iso = null;
                    }
                }
                $messagesOut[] = [
                    'glass_seq' => $seq,
                    'message_id' => (string) ($m['message_id'] ?? ''),
                    'role' => (string) ($m['role'] ?? ''),
                    'create_time' => $ctF,
                    'create_time_iso' => $iso,
                    'text' => $text,
                ];
            }
        }

        $yardTitle = trim((string) ($wip['yard_title'] ?? ''));
        $defaultTitle = $yardTitle !== ''
            ? $yardTitle
            : (string) ($core['title'] ?? ('export ' . $faceKey));
        $encPub = logimport_encodes_public($wip);
        $redPub = logimport_redactions_public($wip);
        $exportedAt = time();
        $privacy = [
            'originals_omitted' => true,
            'wip_snapshot_omitted' => true,
            'note' => 'Yard wood: bodies encoded/redacted; encode book ships code+alias only',
        ];
        $baseMeta = [
            'schema' => 'glassbox.v1',
            'house' => 'CHESTER',
            'mat' => 'glassbox',
            'import_order' => $importOrder,
            'testament' => $testament,
            'conversation_id' => (string) ($core['conversation_id'] ?? ''),
            'glass_title' => (string) ($core['title'] ?? ''),
            'yard_title' => $yardTitle,
            'testament_tag' => (string) ($core['testament_tag'] ?? ''),
            'create_time' => $core['create_time'] ?? null,
            'notes' => (string) ($wip['notes'] ?? ''),
            'encodes_public' => $encPub,
            'redactions_public' => $redPub,
            'sanitized' => true,
            'encode_applied' => true,
            'redact_applied' => true,
            'exported_at' => $exportedAt,
            'glass_sealed' => true,
            'privacy' => $privacy,
        ];

        $segSrc = is_array($wip['segments'] ?? null) ? $wip['segments'] : [];
        foreach ($segSrc as $s) {
            if (is_array($s) && isset($s['to_seq'])) {
                $lastSeq = max($lastSeq, (int) $s['to_seq']);
            }
        }
        $seqCeiling = max(0, $lastSeq);
        $segments = logimport_segments_normalize($segSrc, $seqCeiling);
        $segments = logimport_segments_collapse_trivial($segments, $seqCeiling);
        $partCount = count($segments);

        $siblings = [];
        if ($partCount >= 2) {
            foreach ($segments as $i => $seg) {
                $p = $i + 1;
                $pt = trim((string) ($seg['title'] ?? ''));
                $eid = logimport_export_id($importOrder, $testament, $faceKey, $p);
                $siblings[] = [
                    'part' => $p,
                    'export_id' => $eid,
                    'face_id' => $faceKey . '.' . $p,
                    'title' => $pt !== '' ? $pt : ($defaultTitle . ' · part ' . $p),
                    'from_seq' => (int) $seg['from_seq'],
                    'to_seq' => (int) $seg['to_seq'],
                ];
            }
        }

        $write = static function (string $path, array $payload): bool {
            return file_put_contents(
                $path,
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ) !== false;
        };

        /** Stamp bag_seq + chip_id on messages inside one glassbox section. */
        $chipMsgs = static function (array $partMsgs, string $exportId) {
            $out = [];
            foreach (array_values($partMsgs) as $bagSeq => $m) {
                $m['bag_seq'] = $bagSeq;
                $m['chip_id'] = logimport_msg_chip($exportId, $bagSeq);
                // keep glass_seq; drop bare seq if present
                if (isset($m['seq']) && !isset($m['glass_seq'])) {
                    $m['glass_seq'] = (int) $m['seq'];
                }
                unset($m['seq']);
                $out[] = $m;
            }
            return $out;
        };

        // No multi-cut → one glassbox for the whole face
        if ($partCount < 2) {
            logimport_export_clear_stale($faceKey, 1);
            $exportId = logimport_export_id($importOrder, $testament, $faceKey, null);
            $msgs = $chipMsgs($messagesOut, $exportId);
            [$tMin, $tMax] = logimport_messages_span($messagesOut);
            $payload = array_merge($baseMeta, [
                'export_id' => $exportId,
                'face_id' => $faceKey,
                'parent_face' => $faceKey,
                'part' => null,
                'part_count' => 1,
                'part_title' => '',
                'from_seq' => $messagesOut !== [] ? (int) ($messagesOut[0]['glass_seq'] ?? 0) : 0,
                'to_seq' => $lastSeq >= 0 ? $lastSeq : 0,
                'title' => $defaultTitle,
                'span_start' => $tMin,
                'span_end' => $tMax,
                'segments' => [],
                'siblings' => [],
                'messages' => $msgs,
            ]);
            $path = logimport_export_path($faceKey, null, $importOrder, $testament);
            $ok = $write($path, $payload);
            $cUid = null;
            if ($ok) {
                logimport_export_status_set($faceKey, 'in_progress');
                $note = logimport_ledger_note_export($faceKey, $core, $wip, 1, [$path]);
                if (!empty($note['ok'])) {
                    $cUid = (string) ($note['c_uid'] ?? '');
                }
            }
            return [
                'ok' => $ok,
                'path' => $path,
                'paths' => $ok ? [$path] : [],
                'parts' => 1,
                'c_uid' => $cUid,
                'error' => $ok ? null : 'could not write export file',
            ];
        }

        // Multi-part seal: each split = its own .glassbox log
        logimport_export_clear_stale($faceKey, $partCount);
        $paths = [];
        $allOk = true;
        foreach ($segments as $i => $seg) {
            $p = $i + 1;
            $from = (int) $seg['from_seq'];
            $to = (int) $seg['to_seq'];
            $partMsgs = array_values(array_filter(
                $messagesOut,
                static fn($m) => (int) ($m['glass_seq'] ?? -1) >= $from && (int) ($m['glass_seq'] ?? -1) <= $to
            ));
            $exportId = logimport_export_id($importOrder, $testament, $faceKey, $p);
            $msgs = $chipMsgs($partMsgs, $exportId);
            [$tMin, $tMax] = logimport_messages_span($partMsgs);
            $partTitle = trim((string) ($seg['title'] ?? ''));
            $title = $partTitle !== '' ? $partTitle : ($defaultTitle . ' · part ' . $p);
            $partFace = $faceKey . '.' . $p;
            $payload = array_merge($baseMeta, [
                'export_id' => $exportId,
                'face_id' => $partFace,
                'parent_face' => $faceKey,
                'part' => $p,
                'part_count' => $partCount,
                'part_title' => $partTitle,
                'from_seq' => $from,
                'to_seq' => $to,
                'title' => $title,
                'span_start' => $tMin,
                'span_end' => $tMax,
                'segments' => $segments,
                'siblings' => $siblings,
                'messages' => $msgs,
            ]);
            $path = logimport_export_path($faceKey, $p, $importOrder, $testament);
            if (!$write($path, $payload)) {
                $allOk = false;
                break;
            }
            $paths[] = $path;
        }

        $ok = $allOk && $paths !== [];
        $cUid = null;
        if ($ok) {
            logimport_export_status_set($faceKey, 'in_progress');
            $note = logimport_ledger_note_export($faceKey, $core, $wip, count($paths), $paths);
            if (!empty($note['ok'])) {
                $cUid = (string) ($note['c_uid'] ?? '');
            }
        }
        return [
            'ok' => $ok,
            'path' => $paths[0] ?? '',
            'paths' => $paths,
            'parts' => count($paths),
            'c_uid' => $cUid,
            'error' => $ok ? null : 'could not write one or more part export files',
        ];
    }

    /**
     * Merge form fields into existing WIP (preserves encodes/redactions).
     */
    function logimport_wip_merge_form(string $face, array $post, ?array $existing = null): array {
        $wip = is_array($existing) ? $existing : (logimport_wip_load($face) ?: []);
        if (array_key_exists('yard_title', $post)) {
            $wip['yard_title'] = trim((string) $post['yard_title']);
        }
        if (array_key_exists('notes', $post)) {
            $wip['notes'] = (string) $post['notes'];
        }
        if (!isset($wip['encodes']) || !is_array($wip['encodes'])) {
            $wip['encodes'] = [];
        }
        if (!isset($wip['redactions']) || !is_array($wip['redactions'])) {
            $wip['redactions'] = [];
        }
        if (!isset($wip['segments']) || !is_array($wip['segments'])) {
            $wip['segments'] = [];
        }
        return $wip;
    }

    /**
     * Build working segment list from POST titles + WIP ranges (same form submit).
     *
     * @return list<array{title:string,from_seq:int,to_seq:int}>
     */
    function logimport_segments_from_post_and_wip(array $post, array $wip, int $lastSeq): array {
        $segs = logimport_segments_normalize($wip['segments'] ?? [], $lastSeq);
        if ($segs === []) {
            return [];
        }
        if (isset($post['seg_title']) && is_array($post['seg_title'])) {
            foreach ($segs as $i => $seg) {
                if (array_key_exists($i, $post['seg_title'])) {
                    $segs[$i]['title'] = trim((string) $post['seg_title'][$i]);
                }
            }
        }
        return $segs;
    }

    /**
     * Normalize segment list; empty = whole log (no cuts).
     *
     * @param list<array|mixed> $segments
     * @return list<array{title:string,from_seq:int,to_seq:int}>
     */
    function logimport_segments_normalize($segments, int $lastSeq): array {
        if (!is_array($segments) || $segments === []) {
            return [];
        }
        $out = [];
        foreach ($segments as $s) {
            if (!is_array($s)) {
                continue;
            }
            $from = (int) ($s['from_seq'] ?? 0);
            $to = (int) ($s['to_seq'] ?? $lastSeq);
            if ($from < 0) {
                $from = 0;
            }
            if ($lastSeq >= 0 && $to > $lastSeq) {
                $to = $lastSeq;
            }
            if ($to < $from) {
                continue;
            }
            $out[] = [
                'title' => trim((string) ($s['title'] ?? '')),
                'from_seq' => $from,
                'to_seq' => $to,
            ];
        }
        usort($out, static fn($a, $b) => $a['from_seq'] <=> $b['from_seq']);
        return $out;
    }

    /** Single full-span empty title → treat as no splits. */
    function logimport_segments_collapse_trivial(array $segments, int $lastSeq): array {
        if (count($segments) === 1
            && (int) $segments[0]['from_seq'] === 0
            && (int) $segments[0]['to_seq'] === $lastSeq
            && trim((string) $segments[0]['title']) === ''
        ) {
            return [];
        }
        return $segments;
    }

    /**
     * Cut points = last seq of each segment except the final one.
     *
     * @param list<array{from_seq:int,to_seq:int}> $segments
     * @return list<int>
     */
    function logimport_segment_cuts(array $segments): array {
        if (count($segments) < 2) {
            return [];
        }
        $cuts = [];
        $n = count($segments);
        for ($i = 0; $i < $n - 1; $i++) {
            $cuts[] = (int) $segments[$i]['to_seq'];
        }
        return $cuts;
    }

    /**
     * Build segments from cuts; inherit titles from $oldSegments by range overlap.
     * First fragment of a named segment keeps the name; later fragments stay empty
     * (user can name them) — never force "part N" over a saved name.
     *
     * @param list<int> $cuts
     * @param list<array{title:string,from_seq:int,to_seq:int}> $oldSegments
     * @return list<array{title:string,from_seq:int,to_seq:int}>
     */
    function logimport_segments_from_cuts(array $cuts, int $lastSeq, array $oldSegments = []): array {
        if ($lastSeq < 0) {
            return [];
        }
        $cuts = array_values(array_unique(array_map('intval', $cuts)));
        sort($cuts);
        $cuts = array_values(array_filter(
            $cuts,
            static fn($c) => $c >= 0 && $c < $lastSeq
        ));
        if ($cuts === []) {
            $title = '';
            if (count($oldSegments) === 1) {
                $title = trim((string) ($oldSegments[0]['title'] ?? ''));
            }
            return [[
                'title' => $title,
                'from_seq' => 0,
                'to_seq' => $lastSeq,
            ]];
        }
        $bounds = array_merge([-1], $cuts, [$lastSeq]);
        $segs = [];
        for ($i = 0; $i < count($bounds) - 1; $i++) {
            $from = $bounds[$i] + 1;
            $to = $bounds[$i + 1];
            $title = logimport_inherit_segment_title($from, $to, $oldSegments);
            $segs[] = [
                'title' => $title,
                'from_seq' => $from,
                'to_seq' => $to,
            ];
        }
        return $segs;
    }

    /**
     * Prefer old segment that shares the same from_seq (head of a cut keeps name).
     * Else exact range match. Else empty (do not invent "part N" here).
     */
    function logimport_inherit_segment_title(int $from, int $to, array $oldSegments): string {
        if ($oldSegments === []) {
            return '';
        }
        foreach ($oldSegments as $old) {
            if ((int) $old['from_seq'] === $from && (int) $old['to_seq'] === $to) {
                return trim((string) ($old['title'] ?? ''));
            }
        }
        foreach ($oldSegments as $old) {
            if ((int) $old['from_seq'] === $from
                && $to <= (int) $old['to_seq']
                && $from >= (int) $old['from_seq']
            ) {
                // head piece of a previously named segment
                return trim((string) ($old['title'] ?? ''));
            }
        }
        return '';
    }

    function logimport_segments_add_cut(array $segments, int $afterSeq, int $lastSeq): array {
        if ($afterSeq < 0 || $afterSeq >= $lastSeq) {
            return logimport_segments_normalize($segments, $lastSeq);
        }
        $base = $segments !== []
            ? $segments
            : [['from_seq' => 0, 'to_seq' => $lastSeq, 'title' => '']];
        $cuts = logimport_segment_cuts($base);
        if (!in_array($afterSeq, $cuts, true)) {
            $cuts[] = $afterSeq;
        }
        return logimport_segments_from_cuts($cuts, $lastSeq, $base);
    }

    function logimport_segments_remove_cut(array $segments, int $afterSeq, int $lastSeq): array {
        $cuts = logimport_segment_cuts($segments);
        $cuts = array_values(array_filter($cuts, static fn($c) => (int) $c !== $afterSeq));
        if ($cuts === []) {
            return [];
        }
        return logimport_segments_from_cuts($cuts, $lastSeq, $segments);
    }

    /**
     * @return array<int,int> seq => segmentIndex
     */
    function logimport_seq_to_segment(array $segments, int $lastSeq): array {
        if ($segments === []) {
            $map = [];
            for ($i = 0; $i <= $lastSeq; $i++) {
                $map[$i] = 0;
            }
            return $map;
        }
        $map = [];
        foreach ($segments as $si => $seg) {
            $from = (int) $seg['from_seq'];
            $to = (int) $seg['to_seq'];
            for ($i = $from; $i <= $to; $i++) {
                $map[$i] = $si;
            }
        }
        return $map;
    }

    // ── Encode book (z/ WIP only — originals never on ledger) ─────────

    function logimport_new_id(string $prefix = 'e'): string {
        try {
            return $prefix . '.' . strtoupper(bin2hex(random_bytes(4)));
        } catch (Throwable $e) {
            return $prefix . '.' . strtoupper(dechex(mt_rand()) . dechex(time()));
        }
    }

    /**
     * VEN-ish code: letters from original + serial (HJI-048).
     *
     * @param list<array> $encodes
     */
    function logimport_encode_mint_code(string $original, array $encodes): string {
        $letters = preg_replace('/[^a-zA-Z]/', '', $original) ?? '';
        $prefix = strtoupper(substr($letters !== '' ? $letters : 'ENC', 0, 3));
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }
        $used = [];
        foreach ($encodes as $e) {
            if (is_array($e) && !empty($e['code'])) {
                $used[strtoupper((string) $e['code'])] = true;
            }
        }
        for ($n = 1; $n < 1000; $n++) {
            $code = $prefix . '-' . sprintf('%03d', $n);
            if (empty($used[$code])) {
                return $code;
            }
        }
        return $prefix . '-' . strtoupper(substr(md5($original . microtime()), 0, 3));
    }

    /**
     * @return list<array{id:string,code:string,alias:string,original:string,also:list<string>,created_at:int}>
     */
    function logimport_encodes_list(?array $wip): array {
        if (!is_array($wip) || !is_array($wip['encodes'] ?? null)) {
            return [];
        }
        $out = [];
        foreach ($wip['encodes'] as $e) {
            if (!is_array($e)) {
                continue;
            }
            $orig = trim((string) ($e['original'] ?? ''));
            $alias = trim((string) ($e['alias'] ?? ''));
            if ($orig === '' || $alias === '') {
                continue;
            }
            $also = [];
            $rawAlso = $e['also'] ?? $e['alts'] ?? $e['spellings'] ?? [];
            if (is_string($rawAlso)) {
                $rawAlso = preg_split('/\s*,\s*/', $rawAlso) ?: [];
            }
            if (is_array($rawAlso)) {
                foreach ($rawAlso as $a) {
                    $a = trim((string) $a);
                    if ($a !== '' && strcasecmp($a, $orig) !== 0) {
                        $also[] = $a;
                    }
                }
            }
            $out[] = [
                'id' => (string) ($e['id'] ?? logimport_new_id('e')),
                'code' => (string) ($e['code'] ?? ''),
                'alias' => $alias,
                'original' => $orig,
                'also' => array_values(array_unique($also)),
                'created_at' => (int) ($e['created_at'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Expand encode book to [original|also → alias] pairs (longest first via replace_map sort).
     *
     * @return list<array{0:string,1:string}>
     */
    function logimport_encode_pairs(?array $wip): array {
        $pairs = [];
        foreach (logimport_encodes_list($wip) as $e) {
            $alias = (string) $e['alias'];
            $pairs[] = [(string) $e['original'], $alias];
            foreach ($e['also'] as $a) {
                $pairs[] = [$a, $alias];
            }
        }
        return $pairs;
    }

    /**
     * Safe HTML form key for encode row id (PHP converts '.' in field names to '_').
     */
    function logimport_encode_form_key(string $id): string {
        return preg_replace('/[^a-zA-Z0-9]/', '', $id) ?? '';
    }

    /**
     * @return list<array{id:string,kind:string,original?:string,seq?:int,label?:string,created_at:int}>
     */
    function logimport_redactions_list(?array $wip): array {
        if (!is_array($wip) || !is_array($wip['redactions'] ?? null)) {
            return [];
        }
        $out = [];
        foreach ($wip['redactions'] as $r) {
            if (!is_array($r)) {
                continue;
            }
            $kind = (string) ($r['kind'] ?? 'phrase');
            if ($kind === 'message') {
                if (!array_key_exists('seq', $r)) {
                    continue;
                }
                $out[] = [
                    'id' => (string) ($r['id'] ?? logimport_new_id('r')),
                    'kind' => 'message',
                    'seq' => (int) $r['seq'],
                    'label' => trim((string) ($r['label'] ?? '')),
                    'created_at' => (int) ($r['created_at'] ?? 0),
                ];
            } else {
                $orig = trim((string) ($r['original'] ?? ''));
                if ($orig === '') {
                    continue;
                }
                $out[] = [
                    'id' => (string) ($r['id'] ?? logimport_new_id('r')),
                    'kind' => 'phrase',
                    'original' => $orig,
                    'label' => trim((string) ($r['label'] ?? '')),
                    'created_at' => (int) ($r['created_at'] ?? 0),
                ];
            }
        }
        return $out;
    }

    /**
     * Word/phrase boundary pattern so "mom" does not match inside "moment".
     * Boundaries = not a letter/digit/_ on either side (Unicode letters via \p{L}).
     */
    function logimport_replace_pattern(string $from): string {
        $q = preg_quote($from, '/');
        return '/(?<![\p{L}\p{N}_])' . $q . '(?![\p{L}\p{N}_])/iu';
    }

    /**
     * Replace longest originals first (avoid partial clobber).
     * Case-insensitive · whole-word/phrase only · replacement keeps alias casing.
     * Plain text only — no UI markers (use replace_map_marked for encode chips).
     *
     * @param list<array{0:string,1:string}> $pairs
     */
    function logimport_replace_map(string $text, array $pairs): string {
        if ($text === '' || $pairs === []) {
            return $text;
        }
        usort($pairs, static function ($a, $b) {
            return strlen((string) $b[0]) <=> strlen((string) $a[0]);
        });
        foreach ($pairs as $pair) {
            $from = (string) $pair[0];
            $to = (string) $pair[1];
            if ($from === '') {
                continue;
            }
            $text = preg_replace(logimport_replace_pattern($from), $to, $text) ?? $text;
        }
        return $text;
    }

    /**
     * Chip token (desk view only). Must not appear in normal log text.
     * Format: «E»alias«/E»  — no [[ ]] so paste/regex cannot half-eat "LIENC".
     */
    function logimport_enc_token(string $alias): string {
        $alias = str_replace(["\n", "\r", '«', '»'], ' ', $alias);
        $alias = trim($alias);
        return '«E»' . $alias . '«/E»';
    }

    /**
     * Strip paste junk from form fields (original/alias/also).
     * Collapses any chip tokens to their alias text only.
     */
    function logimport_sanitize_paste(string $s): string {
        // Current chips
        $s = preg_replace('/«E»(.*?)«\/E»/us', '$1', $s) ?? $s;
        // Short-lived [[LIENC:…]] chips
        $s = preg_replace('/\[\[LIENC:(.*?)\]\]/us', '$1', $s) ?? $s;
        // Broken half-tokens
        $s = preg_replace('/\[\[LIENC:/iu', '', $s) ?? $s;
        $s = preg_replace('/\[\[LINC:/iu', '', $s) ?? $s;
        $s = str_replace([']]', '«E»', '«/E»'], '', $s);
        // Legacy binary tokens
        $s = preg_replace("/\x1ELIENC\x1F(.*?)\x1E/us", '$1', $s) ?? $s;
        $s = str_replace(["\x1E", "\x1F"], '', $s);
        // C0 controls except tab/LF/CR
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? $s;
        if (str_contains($s, "\u{FFFD}")) {
            $s = str_replace("\u{FFFD}", '', $s);
        }
        return trim($s);
    }

    /**
     * Encode-only: leave opaque tokens where hits landed so UI can chip them.
     * Protects already-marked chips so a later pair cannot match inside an alias
     * (e.g. "Child" inside «E»The Inner Child«/E»).
     *
     * @param list<array{0:string,1:string}> $pairs
     * @return array{text:string,hit_count:int}
     */
    function logimport_replace_map_marked(string $text, array $pairs): array {
        if ($text === '' || $pairs === []) {
            return ['text' => $text, 'hit_count' => 0];
        }
        usort($pairs, static function ($a, $b) {
            return strlen((string) $b[0]) <=> strlen((string) $a[0]);
        });

        // Normalize any legacy chip forms → current, then park chips as placeholders
        $text = preg_replace("/\x1ELIENC\x1F(.*?)\x1E/us", '«E»$1«/E»', $text) ?? $text;
        $text = preg_replace('/\[\[LIENC:(.*?)\]\]/us', '«E»$1«/E»', $text) ?? $text;

        $parked = [];
        $text = preg_replace_callback(
            '/«E»(.*?)«\/E»/us',
            static function (array $m) use (&$parked): string {
                $key = '§§P' . count($parked) . '§§';
                $parked[$key] = (string) $m[1];
                return $key;
            },
            $text
        ) ?? $text;

        $n = 0;
        foreach ($pairs as $pair) {
            $from = (string) $pair[0];
            $to = (string) $pair[1];
            if ($from === '') {
                continue;
            }
            // Never match inside placeholders §§P0§§
            $text = preg_replace_callback(
                logimport_replace_pattern($from),
                static function () use ($to, &$n, &$parked): string {
                    $n++;
                    $key = '§§P' . count($parked) . '§§';
                    $parked[$key] = str_replace(["\n", "\r", '«', '»', '§'], ' ', $to);
                    $parked[$key] = trim($parked[$key]);
                    return $key;
                },
                $text
            ) ?? $text;
        }

        foreach ($parked as $key => $alias) {
            $text = str_replace($key, logimport_enc_token($alias), $text);
        }
        return ['text' => $text, 'hit_count' => $n];
    }

    /**
     * Turn marked encode text into safe HTML (chips on hits, escaped plain elsewhere).
     */
    function logimport_format_marked_html(string $marked): string {
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        // Normalize legacy → current tokens
        $marked = preg_replace("/\x1ELIENC\x1F(.*?)\x1E/us", '«E»$1«/E»', $marked) ?? $marked;
        $marked = preg_replace('/\[\[LIENC:(.*?)\]\]/us', '«E»$1«/E»', $marked) ?? $marked;
        // Strip any broken half-open junk left in plain text
        $marked = preg_replace('/\[\[LIENC?:/iu', '', $marked) ?? $marked;

        $parts = preg_split('/(«E».*?«\/E»)/us', $marked, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $h($marked);
        }
        $html = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^«E»(.*)«\/E»$/us', $part, $m)) {
                $html .= '<mark class="li-enc-hit">' . $h($m[1]) . '</mark>';
            } else {
                // Plain text only — do not run full sanitize (that was eating tokens)
                $plain = str_replace(["\x1E", "\x1F"], '', $part);
                $plain = preg_replace('/\[\[LIENC?:/iu', '', $plain) ?? $plain;
                $html .= $h($plain);
            }
        }
        return $html;
    }

    /**
     * Push one encode row into RX venDesk registry (z/ven_registry).
     * KVEN from code · label = alias · matches = original + also spellings.
     * Also dual-writes a public ven_ship crate to the big ledger (code + alias only).
     * @return array{ok:bool,error?:string,kven?:string,c_uid?:string}
     */
    function logimport_encode_push_ven(array $enc, string $faceKey = ''): array {
        $venLib = dirname(__DIR__) . '/venDesk/venDesk_lib.php';
        if (!is_file($venLib)) {
            return ['ok' => false, 'error' => 'venDesk lib missing'];
        }
        require_once $venLib;
        if (!function_exists('vendesk_load') || !function_exists('vendesk_save')) {
            return ['ok' => false, 'error' => 'venDesk not loaded'];
        }
        $code = trim((string) ($enc['code'] ?? ''));
        $alias = trim((string) ($enc['alias'] ?? ''));
        $orig = trim((string) ($enc['original'] ?? ''));
        $also = is_array($enc['also'] ?? null) ? $enc['also'] : [];
        if ($alias === '' || $orig === '') {
            return ['ok' => false, 'error' => 'encode needs original + alias'];
        }
        $reg = vendesk_load();
        if ($code === '' || !function_exists('vendesk_valid_kven') || !vendesk_valid_kven($code)) {
            $code = function_exists('vendesk_mint_kven')
                ? vendesk_mint_kven($reg, $orig)
                : logimport_encode_mint_code($orig, []);
        }
        $code = vendesk_normalize_kven($code);
        $matches = array_values(array_unique(array_filter(array_merge([$orig], $also), static fn($s) => trim((string) $s) !== '')));
        // merge into existing KVEN if present
        $existing = vendesk_find($reg, $code);
        $venEvent = $existing ? 'modify' : 'ship';
        if ($existing) {
            $existing['label'] = $alias !== '' ? $alias : (string) ($existing['label'] ?? '');
            $alts = $existing['alts'] ?? [];
            if ($alias !== '' && !in_array($alias, $alts, true)) {
                $alts[] = $alias;
            }
            $existing['alts'] = array_values(array_unique(array_map('strval', $alts)));
            $m = $existing['matches'] ?? [];
            foreach ($matches as $mt) {
                $found = false;
                foreach ($m as $em) {
                    if (strcasecmp((string) $em, (string) $mt) === 0) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $m[] = $mt;
                }
            }
            $existing['matches'] = array_values($m);
            $existing['updated'] = time();
            $entries = [];
            foreach ($reg['entries'] as $e) {
                $entries[] = (($e['id'] ?? '') === ($existing['id'] ?? '') || ($e['kven'] ?? '') === $code)
                    ? $existing
                    : $e;
            }
            $reg['entries'] = $entries;
        } else {
            $reg['entries'][] = vendesk_normalize_entry([
                'kven' => $code,
                'label' => $alias,
                'alts' => [$alias],
                'matches' => $matches,
                'type' => 'person',
                'notes' => 'from logImport encode book',
            ]);
        }
        if (!vendesk_save($reg)) {
            return ['ok' => false, 'error' => 'could not write ven registry'];
        }
        // Big db awareness — public face only (ship new / modify existing)
        $note = logimport_ledger_note_ven($code, $alias, 'logImport', $faceKey, $venEvent);
        return [
            'ok' => true,
            'kven' => $code,
            'c_uid' => !empty($note['ok']) ? (string) ($note['c_uid'] ?? '') : null,
        ];
    }

    /**
     * Apply redactions then encodes for display/export preview.
     * Glass raw is never mutated — this is a view transform only.
     * When encode is on, text may include chip markers (see format_marked_html).
     *
     * @param bool $markEncodes when true (desk view), wrap encode hits in tokens for HTML chips
     * @return array{text:string,wholly_redacted:bool,encode_marked:bool,hit_count:int}
     */
    function logimport_transform_text(
        string $text,
        int $seq,
        ?array $wip,
        bool $applyRedact,
        bool $applyEncode,
        bool $markEncodes = false
    ): array {
        $wholly = false;
        $encodeMarked = false;
        $hitCount = 0;
        $redacts = logimport_redactions_list($wip);

        if ($applyRedact) {
            foreach ($redacts as $r) {
                if (($r['kind'] ?? '') === 'message' && (int) ($r['seq'] ?? -1) === $seq) {
                    $wholly = true;
                    break;
                }
            }
            if ($wholly) {
                $text = '████████  [REDACTED · msg #' . $seq . ']  ████████';
            } else {
                $pairs = [];
                foreach ($redacts as $r) {
                    if (($r['kind'] ?? '') === 'phrase' && !empty($r['original'])) {
                        $n = max(4, min(48, (int) (strlen((string) $r['original']) * 0.9)));
                        $pairs[] = [(string) $r['original'], str_repeat('█', $n)];
                    }
                }
                $text = logimport_replace_map($text, $pairs);
            }
        }

        if ($applyEncode && !$wholly) {
            $pairs = logimport_encode_pairs($wip);
            if ($markEncodes) {
                $r = logimport_replace_map_marked($text, $pairs);
                $text = $r['text'];
                $hitCount = (int) $r['hit_count'];
                $encodeMarked = $hitCount > 0;
            } else {
                $text = logimport_replace_map($text, $pairs);
            }
        }

        return [
            'text' => $text,
            'wholly_redacted' => $wholly,
            'encode_marked' => $encodeMarked,
            'hit_count' => $hitCount,
        ];
    }

    function logimport_view_flags(?array $wip): array {
        return [
            'apply_redact' => !empty($wip['apply_redact']),
            'apply_encode' => !empty($wip['apply_encode']),
        ];
    }

    function logimport_bar(string $s, int $max = 48): string {
        $n = max(4, min($max, (int) (strlen($s) * 0.85)));
        return str_repeat('█', $n);
    }
}
