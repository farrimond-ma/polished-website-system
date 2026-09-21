<?php
/**
 * Reading the text out of a PDF, in plain PHP.
 *
 * Acturis quotations embed their fonts and store the text as glyph numbers, so simply pulling the
 * "(...)" pieces out of a PDF returns nothing readable. This walks the file the way a PDF viewer
 * does instead: page -> its fonts -> each font's /ToUnicode map (glyph number -> real letter) ->
 * its content. Text comes back page by page with the document's own line breaks, so the renewal
 * importer can read "label / value" pairs out of it.
 *
 * No outside library is needed. Encrypted PDFs and scans (pictures of text) cannot be read — the
 * caller is told to use the Word version instead.
 */
declare(strict_types=1);

/** Every "N 0 obj ... endobj" in the file: object number => its contents. */
function pdf_objects(string $raw): array {
    $objects = [];
    if (preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $raw, $m, PREG_SET_ORDER)) {
        foreach ($m as $o) $objects[(int)$o[1]] = $o[2];
    }
    return $objects;
}

/** The decoded stream inside an object, or '' if it has none (or uses a filter we cannot undo). */
function pdf_stream(string $body): string {
    $at = strpos($body, 'stream');
    if ($at === false) return '';
    $dict = substr($body, 0, $at);
    $start = $at + 6;
    if (substr($body, $start, 2) === "\r\n") $start += 2;
    elseif (in_array(substr($body, $start, 1), ["\n", "\r"], true)) $start += 1;
    $end = strpos($body, 'endstream', $start);
    $data = substr($body, $start, ($end === false ? strlen($body) : $end) - $start);

    if (stripos($dict, 'FlateDecode') !== false) {
        $un = @gzuncompress($data);
        if ($un === false) $un = @gzinflate(substr($data, 2));
        if ($un === false) $un = @gzinflate($data);
        return $un === false ? '' : $un;
    }
    if (stripos($dict, 'ASCIIHexDecode') !== false) {
        return (string)@hex2bin(preg_replace('/[^0-9a-fA-F]/', '', explode('>', $data)[0]));
    }
    if (stripos($dict, '/Filter') !== false) return '';
    return $data;
}

/** "12 0 R" => that object's contents; anything else is returned as it is. */
function pdf_deref(array $objects, string $value): string {
    if (preg_match('/^\s*(\d+)\s+\d+\s+R\s*$/', $value, $m)) return $objects[(int)$m[1]] ?? '';
    return $value;
}

/** The value of /Key in a dictionary, whether it is a name, number, reference, array or sub-dictionary. */
function pdf_dict_value(string $dict, string $key): string {
    if (!preg_match('/\/' . preg_quote($key, '/') . '(?![A-Za-z0-9])/', $dict, $m, PREG_OFFSET_CAPTURE)) return '';
    $rest = ltrim(substr($dict, $m[0][1] + strlen($m[0][0])));
    if ($rest === '') return '';
    if (str_starts_with($rest, '<<')) {                       // sub-dictionary: balance the brackets
        $depth = 0; $len = strlen($rest);
        for ($i = 0; $i < $len - 1; $i++) {
            if ($rest[$i] === '<' && $rest[$i + 1] === '<') { $depth++; $i++; }
            elseif ($rest[$i] === '>' && $rest[$i + 1] === '>') { $depth--; $i++; if ($depth === 0) return substr($rest, 0, $i + 1); }
        }
        return $rest;
    }
    if ($rest[0] === '[') { $to = strpos($rest, ']'); return $to === false ? $rest : substr($rest, 0, $to + 1); }
    if (preg_match('/^(\d+\s+\d+\s+R|\/[^\s\/\[\]<>()]+|-?[\d.]+)/', $rest, $v)) return $v[1];
    return '';
}

/** A character from its Unicode number. (Not mb_chr() directly: "0" is falsy in PHP.) */
function pdf_chr(int $code): string {
    if ($code <= 0) return '';
    $ch = mb_chr($code, 'UTF-8');
    return $ch === false ? '' : $ch;
}

/** One or more UTF-16 hex units turned into normal text. */
function pdf_hex_to_text(string $hex): string {
    $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);
    if ($hex === '') return '';
    if (strlen($hex) % 4) $hex = str_pad($hex, (int)ceil(strlen($hex) / 4) * 4, '0');
    $text = '';
    $units = str_split($hex, 4);
    for ($i = 0; $i < count($units); $i++) {
        $code = (int)hexdec($units[$i]);
        if ($code >= 0xD800 && $code <= 0xDBFF && isset($units[$i + 1])) {     // surrogate pair
            $low = (int)hexdec($units[++$i]);
            $code = 0x10000 + (($code - 0xD800) << 10) + ($low - 0xDC00);
        }
        $text .= pdf_chr($code);
    }
    return $text;
}

/** A font's /ToUnicode map: glyph code => the letter it stands for. */
function pdf_tounicode(string $cmap): array {
    $map = [];
    if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $blocks)) {
        foreach ($blocks[1] as $block) {
            if (preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]*)>/', $block, $pairs, PREG_SET_ORDER)) {
                foreach ($pairs as $p) $map[(int)hexdec($p[1])] = pdf_hex_to_text($p[2]);
            }
        }
    }
    if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $blocks)) {
        foreach ($blocks[1] as $block) {
            // <from> <to> <first>  — a run of consecutive letters
            if (preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>/', $block, $runs, PREG_SET_ORDER)) {
                foreach ($runs as $r) {
                    $from = (int)hexdec($r[1]); $to = (int)hexdec($r[2]); $first = (int)hexdec($r[3]);
                    if ($to < $from || $to - $from > 65535) continue;
                    for ($c = $from; $c <= $to; $c++) $map[$c] = pdf_chr($first + ($c - $from));
                }
            }
            // <from> <to> [ <a> <b> ... ] — a list of letters
            if (preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>\s*\[(.*?)\]/s', $block, $lists, PREG_SET_ORDER)) {
                foreach ($lists as $l) {
                    $from = (int)hexdec($l[1]);
                    if (preg_match_all('/<([0-9a-fA-F]*)>/', $l[3], $items)) {
                        foreach ($items[1] as $i => $hex) $map[$from + $i] = pdf_hex_to_text($hex);
                    }
                }
            }
        }
    }
    return $map;
}

/** The fonts a page uses: resource name (F1, TT2...) => ['two_byte' => bool, 'map' => [code => letter]]. */
function pdf_page_fonts(array $objects, string $resources): array {
    $fonts = [];
    $fontDict = pdf_deref($objects, pdf_dict_value($resources, 'Font'));
    if ($fontDict === '') return $fonts;
    if (!preg_match_all('/\/([^\s\/\[\]<>()]+)\s+(\d+)\s+\d+\s+R/', $fontDict, $refs, PREG_SET_ORDER)) return $fonts;
    foreach ($refs as $ref) {
        $body = $objects[(int)$ref[2]] ?? '';
        if ($body === '') continue;
        $toUnicode = pdf_dict_value($body, 'ToUnicode');
        $map = $toUnicode === '' ? [] : pdf_tounicode(pdf_stream(pdf_deref($objects, $toUnicode)));
        if (!$map && str_contains($body, '/DescendantFonts')) {               // Type0: the map may sit on the child
            $child = pdf_deref($objects, trim((string)preg_replace('/[\[\]]/', '', pdf_dict_value($body, 'DescendantFonts'))));
            $childMap = pdf_dict_value($child, 'ToUnicode');
            if ($childMap !== '') $map = pdf_tounicode(pdf_stream(pdf_deref($objects, $childMap)));
        }
        $fonts[$ref[1]] = [
            'two_byte' => str_contains($body, '/Type0') || str_contains($body, '/Identity-H'),
            'map'      => $map,
        ];
    }
    return $fonts;
}

/** One string from a content stream turned into readable text using the current font. */
function pdf_show_text(string $bytes, array $font): string {
    $map = $font['map'] ?? [];
    $out = '';
    if (!empty($font['two_byte'])) {
        $len = strlen($bytes) - (strlen($bytes) % 2);
        for ($i = 0; $i < $len; $i += 2) {
            $code = (ord($bytes[$i]) << 8) | ord($bytes[$i + 1]);
            if (isset($map[$code])) { $out .= $map[$code]; continue; }
            $out .= $map ? '' : pdf_chr($code);
        }
        return $out;
    }
    $len = strlen($bytes);
    for ($i = 0; $i < $len; $i++) {
        $code = ord($bytes[$i]);
        if (isset($map[$code])) { $out .= $map[$code]; continue; }
        if ($code === 0) continue;
        $out .= $code < 128 ? $bytes[$i] : pdf_chr($code);     // close enough to WinAnsi here
    }
    return $out;
}

/** A literal "(...)" string with its backslash escapes undone. */
function pdf_literal(string $s): string {
    $out = ''; $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        if ($s[$i] !== '\\') { $out .= $s[$i]; continue; }
        $next = $s[++$i] ?? '';
        if ($next === '') break;
        if (ctype_digit($next)) {                                             // \ooo octal
            $oct = $next;
            while (strlen($oct) < 3 && ctype_digit($s[$i + 1] ?? '')) $oct .= $s[++$i];
            $out .= chr((int)octdec($oct) & 0xFF);
            continue;
        }
        $out .= ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C"][$next] ?? $next;
    }
    return $out;
}

/** The readable text of one page's content stream. */
function pdf_content_text(string $content, array $fonts): string {
    $out = '';
    $font = ['two_byte' => false, 'map' => []];
    $operands = [];
    $len = strlen($content);
    // Where the text sits on the page. A PDF places each piece of a line separately, so the only
    // way to tell where one line ends and the next begins is to follow the position down the page.
    $x = 0.0; $y = 0.0; $leading = 12.0;
    $lastY = null;

    for ($i = 0; $i < $len; $i++) {
        $c = $content[$i];

        if ($c === '(') {                                                     // (literal string)
            $depth = 1; $start = ++$i;
            for (; $i < $len; $i++) {
                if ($content[$i] === '\\') { $i++; continue; }
                if ($content[$i] === '(') $depth++;
                elseif ($content[$i] === ')' && --$depth === 0) break;
            }
            $operands[] = ['str', pdf_literal(substr($content, $start, $i - $start))];
            continue;
        }
        if ($c === '<' && ($content[$i + 1] ?? '') !== '<') {                  // <hex string>
            $to = strpos($content, '>', $i);
            if ($to === false) break;
            $hex = preg_replace('/[^0-9a-fA-F]/', '', substr($content, $i + 1, $to - $i - 1));
            if (strlen($hex) % 2) $hex .= '0';
            $operands[] = ['str', (string)@hex2bin($hex)];
            $i = $to;
            continue;
        }
        if ($c === '/') {                                                     // /Name
            $j = $i + 1;
            while ($j < $len && !preg_match('/[\s\/\[\]<>(){}%]/', $content[$j])) $j++;
            $operands[] = ['name', substr($content, $i + 1, $j - $i - 1)];
            $i = $j - 1;
            continue;
        }
        if (preg_match('/[\d.]/', $c) || (($c === '-' || $c === '+') && preg_match('/[\d.]/', $content[$i + 1] ?? ''))) {
            $j = $i + 1;
            while ($j < $len && preg_match('/[\d.]/', $content[$j])) $j++;
            $operands[] = ['num', (float)substr($content, $i, $j - $i)];
            $i = $j - 1;
            continue;
        }
        if (!preg_match('/[A-Za-z\'"*]/', $c)) continue;                      // whitespace, brackets and the rest

        $j = $i;                                                              // an operator
        while ($j < $len && preg_match('/[A-Za-z0-9*\'"]/', $content[$j])) $j++;
        $op = substr($content, $i, $j - $i);
        $i = $j - 1;

        $nums = array_values(array_map(fn($o) => $o[1], array_filter($operands, fn($o) => $o[0] === 'num')));

        switch ($op) {
            case 'Tf':
                foreach ($operands as $o) if ($o[0] === 'name') $font = $fonts[$o[1]] ?? ['two_byte' => false, 'map' => []];
                break;
            case 'TL':
                $leading = $nums[0] ?? $leading;
                break;
            case 'BT':
                $x = 0.0; $y = 0.0;
                break;
            case 'Td': case 'TD':
                $x += $nums[0] ?? 0.0;
                $y += $nums[1] ?? 0.0;
                if ($op === 'TD' && ($nums[1] ?? 0.0) < 0) $leading = -$nums[1];
                break;
            case 'Tm':
                $x = $nums[4] ?? 0.0;
                $y = $nums[5] ?? 0.0;
                break;
            case 'T*':
                $y -= $leading;
                break;
            case 'Tj': case 'TJ': case "'": case '"':
                if ($op === "'" || $op === '"') $y -= $leading;
                $text = '';
                foreach ($operands as $k => $o) {
                    if ($o[0] === 'str') $text .= pdf_show_text($o[1], $font);
                    // A wide gap between two pieces of one line is a space in the original
                    elseif ($o[0] === 'num' && $o[1] <= -120 && ($operands[$k + 1][0] ?? '') === 'str') $text .= ' ';
                }
                if ($text === '') break;
                if ($lastY === null || abs($y - $lastY) > 1.5) $out .= "
";            // next line down the page
                elseif ($out !== '' && !preg_match('/\s$/', $out) && !str_starts_with($text, ' ')) $out .= ' ';
                $out .= $text;
                $lastY = $y;
                break;
        }
        $operands = [];
    }
    return $out;
}

/** The whole document as text, page by page. Returns ['text' => ..., 'error' => null|string]. */
function pdf_text(string $raw): array {
    if (!str_starts_with($raw, '%PDF')) return ['text' => '', 'error' => 'That does not look like a PDF.'];
    if (preg_match('/\/Encrypt[\s\/\d]/', $raw)) {
        return ['text' => '', 'error' => 'This PDF is password protected, so its text cannot be read. Please use the Word version.'];
    }

    $objects = pdf_objects($raw);
    $text = '';
    foreach ($objects as $body) {
        if (!preg_match('/\/Type\s*\/Page(?![A-Za-z])/', $body)) continue;
        $resources = pdf_deref($objects, pdf_dict_value($body, 'Resources'));
        $fonts = pdf_page_fonts($objects, $resources);
        $content = '';
        $contents = pdf_dict_value($body, 'Contents');
        if (preg_match_all('/(\d+)\s+\d+\s+R/', $contents, $refs)) {
            foreach ($refs[1] as $ref) $content .= pdf_stream($objects[(int)$ref] ?? '') . "\n";
        }
        if ($content === '') continue;
        $text .= pdf_content_text($content, $fonts) . "\n\n";
    }

    // Tidy up: no repeated spaces, no runs of blank lines
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/ *\n */', "\n", (string)$text);
    $text = trim((string)preg_replace('/\n{3,}/', "\n\n", (string)$text));

    if (mb_strlen($text) < 40) {
        return ['text' => $text, 'error' => 'Hardly any text could be read — this PDF is probably a scan (a picture of the page). Please use the Word version instead.'];
    }
    return ['text' => $text, 'error' => null];
}
