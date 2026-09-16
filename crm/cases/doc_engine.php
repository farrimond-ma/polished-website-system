<?php
/**
 * Document template engine — renders SchemeServe-style templates.
 * Supports:
 *   [Token]                                     merge field
 *   ##[Field].ToString("C0"|"C2")##             currency (0 / 2 dp)
 *   ##([A]+[B]).ToString("C2")##                arithmetic then format
 *   ##IF(cond,'true','false')##                 conditional (also used in style="display:...")
 *   conditions: [F]='x'   [F]=0   [F]>0   with  AND / OR
 * Unmapped [Token]s are left visible as [Token] so you can see what still needs mapping.
 */
declare(strict_types=1);

function doc_render(string $tpl, array $data): string {
    // 1) evaluate every ##...## expression
    $out = preg_replace_callback('/##(.*?)##/s', function ($m) use ($data) {
        try { return doc_eval(trim($m[1]), $data); }
        catch (Throwable $e) { return ''; }   // malformed fragment -> harmless empty
    }, $tpl);
    // strip any stray unpaired ## (SchemeServe exports sometimes truncate an expression,
    // e.g. the [Record].HasEndorsement(...) fragment) so it can't leak into the output
    $out = str_replace('##', '', $out);
    // 2) substitute remaining [tokens]
    $out = preg_replace_callback('/\[([A-Za-z0-9_.]+)\]/', function ($m) use ($data) {
        $k = $m[1];
        return array_key_exists($k, $data) ? (string)$data[$k] : '[' . $k . ']';
    }, $out);
    return $out;
}

/* ---- expression evaluation ---- */
function doc_eval(string $expr, array $data): string {
    $expr = doc_decode($expr);
    $expr = trim($expr);

    // IF(cond, a, b)
    if (preg_match('/^IF\s*\((.*)\)$/is', $expr, $m)) {
        $parts = doc_split_top($m[1]);
        if (count($parts) >= 3) {
            $cond = trim($parts[0]);
            $a = trim($parts[1]);
            $b = trim(implode(',', array_slice($parts, 2))); // safety if extra commas
            return doc_cond($cond, $data) ? doc_value($a, $data) : doc_value($b, $data);
        }
        return '';
    }
    // operand.ToString("Cn")
    if (preg_match('/^(.*)\.ToString\(\s*"C(\d)"\s*\)$/is', $expr, $m)) {
        $num = doc_number(trim($m[1]), $data);
        return doc_currency($num, (int)$m[2]);
    }
    // bare value (field / arithmetic / literal)
    return doc_value($expr, $data);
}

/** Resolve an argument that may be a 'literal', a [Field], arithmetic, or nested IF/ToString. */
function doc_value(string $s, array $data): string {
    $s = trim($s);
    if ($s === '') return '';
    if ($s[0] === "'" && substr($s, -1) === "'") return str_replace("''", "'", substr($s, 1, -1));
    if (preg_match('/^IF\s*\(/i', $s) || preg_match('/\.ToString\(/i', $s)) return doc_eval($s, $data);
    if (strpos($s, '+') !== false || (isset($s[0]) && $s[0] === '(')) {
        // arithmetic -> keep as number string
        $n = doc_number($s, $data);
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
    if (preg_match('/^\[([A-Za-z0-9_.]+)\]$/', $s, $m)) {
        return array_key_exists($m[1], $data) ? (string)$data[$m[1]] : '';
    }
    return $s; // number or plain text literal
}

/** Numeric value of a field / arithmetic expression. */
function doc_number(string $s, array $data): float {
    $s = trim($s);
    if (preg_match('/^\((.*)\)$/s', $s, $m)) $s = $m[1];
    // replace [Field] with numeric values
    $s = preg_replace_callback('/\[([A-Za-z0-9_.]+)\]/', function ($m) use ($data) {
        $v = $data[$m[1]] ?? 0;
        $v = is_string($v) ? preg_replace('/[^0-9.\-]/', '', $v) : $v;
        return $v === '' ? '0' : (string)$v;
    }, $s);
    // only allow numbers and + - * / . ( )
    if (!preg_match('#^[0-9.\-+*/() ]*$#', $s)) return (float)preg_replace('/[^0-9.\-]/', '', $s);
    if (trim($s) === '') return 0.0;
    $val = @eval('return ' . $s . ';');
    return is_numeric($val) ? (float)$val : 0.0;
}

/** Evaluate a condition string to bool. Handles AND / OR and one comparison each. */
function doc_cond(string $cond, array $data): bool {
    $cond = doc_decode($cond);
    // split on top-level AND / OR
    if (preg_match('/\bAND\b/i', $cond) && !preg_match("/'[^']*\\bAND\\b[^']*'/i", $cond)) {
        foreach (preg_split('/\bAND\b/i', $cond) as $p) if (!doc_cond($p, $data)) return false;
        return true;
    }
    if (preg_match('/\bOR\b/i', $cond)) {
        foreach (preg_split('/\bOR\b/i', $cond) as $p) if (doc_cond($p, $data)) return true;
        return false;
    }
    // single comparison
    if (preg_match('/^(.*?)(>=|<=|=|>|<)(.*)$/s', $cond, $m)) {
        $lhsRaw = trim($m[1]); $op = $m[2]; $rhsRaw = trim($m[3]);
        $rhsIsStr = ($rhsRaw !== '' && $rhsRaw[0] === "'");
        if ($op === '=') {
            if ($rhsIsStr) {
                $l = doc_value($lhsRaw, $data);
                $r = str_replace("''", "'", substr($rhsRaw, 1, -1));
                return $l === $r;
            }
            return doc_number($lhsRaw, $data) == doc_number($rhsRaw, $data);
        }
        $l = doc_number($lhsRaw, $data); $r = doc_number($rhsRaw, $data);
        switch ($op) { case '>': return $l > $r; case '<': return $l < $r;
                       case '>=': return $l >= $r; case '<=': return $l <= $r; }
    }
    return false;
}

/** Split a string on top-level commas (respect quotes and parens). */
function doc_split_top(string $s): array {
    $out = []; $depth = 0; $inq = false; $cur = '';
    for ($i = 0; $i < strlen($s); $i++) {
        $c = $s[$i];
        if ($c === "'") $inq = !$inq;
        if (!$inq && $c === '(') $depth++;
        if (!$inq && $c === ')') $depth--;
        if (!$inq && $depth === 0 && $c === ',') { $out[] = $cur; $cur = ''; }
        else $cur .= $c;
    }
    if ($cur !== '') $out[] = $cur;
    return $out;
}

function doc_decode(string $s): string {
    return str_replace(['&quot;', '&gt;', '&lt;', '&amp;', '&nbsp;', '&pound;'],
                       ['"', '>', '<', '&', ' ', "\u{00A3}"], $s);
}
function doc_currency(float $n, int $dp): string {
    return "\u{00A3}" . number_format($n, $dp);
}
