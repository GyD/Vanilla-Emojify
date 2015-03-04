<?php

/**
 * Custom array merge recursive who do not transform non-array values into array
 *
 * @param array $array1
 * @param array $array2
 * @return array
 */
function array_merge_recursive_distinct(array &$array1, array &$array2)
{
    $merged = $array1;

    foreach ($array2 as $key => &$value) {
        if (is_array($value) && array_key_exists($key, $merged) && is_array($merged[$key])) {
            $merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
        } else {
            $merged[$key] = $value;
        }
    }

    return $merged;
}

/**
 * @param $uni
 * @return string
 */
function unicode_bytes($uni)
{
    $out = '';

    $cps = explode('-', $uni);
    foreach ($cps as $cp) {
        $out .= emoji_utf8_bytes(hexdec($cp));
    }

    return $out;
}

/**
 * @param $cp
 * @return string
 */
function emoji_utf8_bytes($cp)
{

    if ($cp > 0x10000) {
        # 4 bytes
        return chr(0xF0 | (($cp & 0x1C0000) >> 18)) .
        chr(0x80 | (($cp & 0x3F000) >> 12)) .
        chr(0x80 | (($cp & 0xFC0) >> 6)) .
        chr(0x80 | ($cp & 0x3F));
    } else {
        if ($cp > 0x800) {
            # 3 bytes
            return chr(0xE0 | (($cp & 0xF000) >> 12)) .
            chr(0x80 | (($cp & 0xFC0) >> 6)) .
            chr(0x80 | ($cp & 0x3F));
        } else {
            if ($cp > 0x80) {
                # 2 bytes
                return chr(0xC0 | (($cp & 0x7C0) >> 6)) .
                chr(0x80 | ($cp & 0x3F));
            } else {
                # 1 byte
                return chr($cp);
            }
        }
    }
}

/**
 * @param $s
 * @return string
 */
function format_string($s)
{
    $out = "";
    for ($i = 0; $i < strlen($s); $i++) {
        $c = ord(substr($s, $i, 1));
        if ($c >= 0x20 && $c < 0x80 && !in_array($c, array(34, 39, 92))) {
            $out .= chr($c);
        } else {
            $out .= sprintf('\x%02x', $c);
        }
    }

    return $out;
}

// parse emoji.json
$content = file_get_contents(__DIR__ . '/emoji.json');
$emoji_json = json_decode($content, true);
$content = file_get_contents(__DIR__ . '/emoji.alter.json');
$emoji_alter_json = json_decode($content, true);

// build catalog
$catalog = array('toShort' => array(), 'toHtml' => array());

$json_processed = array();

foreach ($emoji_json as $emoji_entry) {
    $unified_code = $emoji_entry['unified'];

    if (!array_key_exists('texts', $emoji_entry)) {
        $emoji_entry['texts'] = array();
    }

    // alter emoji list
    if (array_key_exists($unified_code, $emoji_alter_json)) {
        $emoji_entry = array_merge_recursive_distinct($emoji_entry, $emoji_alter_json[$unified_code]);
    }

    $current = array(
      'unified' => $emoji_entry['unified'],
      'short_name' => $emoji_entry['short_name'],
      'short_names' => $emoji_entry['short_names'],
      'text' => $emoji_entry['text'],
      'texts' => $emoji_entry['texts'],
    );
    if (!empty($emoji_entry['variations'])) {
        foreach ($emoji_entry['variations'] as $variation_code) {
            $variation = $current;
            $variation['unified'] = $variation_code;

            $json_processed[] = $variation;
        }
    }

    $json_processed[] = $current;
}

$autocomplete_json = array();

/*
 *
 */
foreach ($json_processed as $emoji_entry) {

    $bytes = array(
      'unified' => format_string(unicode_bytes($emoji_entry['unified'])),
        #'docomo' => format_string(unicode_bytes($row['docomo'])),
    );


    foreach ($bytes as $bytetype => $byte) {
        if (!isset($catalog[$bytetype][$byte])) {
//            $short_code = '';
//            if (!empty($emoji_entry['text'])) {
//                $short_code = $emoji_entry['text'];
//            } else {
            $short_code = ':' . $emoji_entry['short_name'] . ':';
//            }
            $catalog['toShort'][$bytetype][$byte] = $short_code;
        }

        $codes = explode('-', $emoji_entry['unified']);
        $html_code = '&#x' . implode(';&#x', $codes) . ';';

        foreach ($emoji_entry['short_names'] as $shortName) {
            $short = ':' . $shortName . ':';

            if (!array_key_exists($short, $catalog['toHtml'])) {
                $catalog['toHtml'][$short] = $html_code;
                $autocomplete_json[$short] = $emoji_entry['unified'];
            }

        }

//        if( !empty($emoji_entry['text'])){
//            if (!array_key_exists($emoji_entry['text'], $catalog['toHtml'])) {
//                $catalog['toHtml'][$emoji_entry['text']] = $html_code;
//            }
//        }
//
//        foreach ($emoji_entry['texts'] as $text) {
//            if (!array_key_exists($text, $catalog['toHtml'])) {
//                $catalog['toHtml'][$text] = $html_code;
//            }
//        }
    }
}

$output = "<?php\n\n";
$output .= '$catalog = ' . str_replace(["'", '\\\\'], ['"', '\\'], var_export($catalog, true)) . ';';

$parent = realpath('../');
print($parent);
//
// Write Catalog.php file
$file = fopen($parent . '/catalog.php', 'w+');
fwrite($file, $output);
fclose($file);

//
// Write emojies.json for autocomplete
$file = fopen($parent . '/js/autocomplete.json', 'w+');
fwrite($file, json_encode($autocomplete_json));
fclose($file);