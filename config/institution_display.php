<?php
/** Normalize display vocabulary only; stored values and application code stay intact. */
function inst_display_text(string $text): string
{
    if (inst_is('institute')) {
        return strtr($text, [
            'دانش‌آموزان' => 'فراگیران', 'دانش آموزان' => 'فراگیران', 'دانشآموزان' => 'فراگیران',
            'دانش‌آموز' => 'فراگیر', 'دانش آموز' => 'فراگیر', 'دانشآموز' => 'فراگیر',
            'پایه' => 'دوره',
        ]);
    }
    return strtr($text, ['فراگیران' => 'دانش‌آموزان', 'فراگیر' => 'دانش‌آموز', 'دوره' => 'پایه']);
}

function inst_display_html(string $html): string
{
    // JSON, downloads, and API payloads must never pass through text normalization.
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Disposition:') === 0) return $html;
        if (stripos($header, 'Content-Type:') === 0
            && stripos($header, 'text/html') === false) return $html;
    }
    if (!preg_match('/^\s*</u', $html)) return $html;
    // Keep scripts, CSS, comments, and editable textarea contents byte-for-byte intact.
    $parts = preg_split('~(<!--.*?-->|<script\b[^>]*>.*?</script\s*>|<style\b[^>]*>.*?</style\s*>|<textarea\b[^>]*>.*?</textarea\s*>|<(?:[^>"\x27]|"[^"]*"|\x27[^\x27]*\x27)*>)~is',
        $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    if ($parts === false) return $html;
    $out = '';
    foreach ($parts as $part) {
        if ($part[0] !== '<') {
            $out .= inst_display_text($part);
        } elseif (preg_match('~^<(?:script|style|textarea)\b|^<!--~i', $part)) {
            $out .= $part;
        } else {
            $out .= preg_replace_callback('~\b(title|aria-label|placeholder|alt|data-tooltip)\s*=\s*(["\x27])(.*?)\2~is',
                static fn($m) => $m[1] . '=' . $m[2] . inst_display_text($m[3]) . $m[2], $part);
        }
    }
    if (stripos($out, '</body>') !== false && function_exists('url')) {
        $script = '<script src="' . htmlspecialchars(url('assets/js/institution-display.js'), ENT_QUOTES, 'UTF-8')
            . '" data-institution-type="' . inst_type() . '"></script>';
        $out = preg_replace('~</body>~i', $script . '</body>', $out, 1);
    }
    return $out;
}
