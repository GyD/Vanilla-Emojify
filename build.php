<?php

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
$json = json_decode($content, true);

// build catalog
$catalog = array('toShort' => array(), 'toHtml' => array());

$json_processed = array();

foreach ($json as $row) {
    $current = array(
      'unified' => $row['unified'],
      'short_name' => $row['short_name'],
      'short_names' => $row['short_names'],
    );
    if (!empty($row['variations'])) {
        foreach ($row['variations'] as $variation_code) {
            $variation = $current;
            $variation['unified'] = $variation_code;

            $json_processed[] = $variation;
        }
    }

    $json_processed[] = $current;
}

/*
 *
 */
foreach ($json_processed as $row) {

    $bytes = array(
      'unified' => format_string(unicode_bytes($row['unified'])),
        #'docomo' => format_string(unicode_bytes($row['docomo'])),
    );


    foreach ($bytes as $bytetype => $byte) {
        if (!isset($catalog[$bytetype][$byte])) {
            $catalog['toShort'][$bytetype][$byte] = ':' . $row['short_name'] . ':';
        }

        foreach ($row['short_names'] as $shortName) {
            $short = ':' . $shortName . ':';

            if (!array_key_exists($short, $catalog['toHtml'])) {
                $codes = explode('-', $row['unified']);

                $html_code = '&#x' . implode(';&#x', $codes) . ';';

                $catalog['toHtml'][$short] = $html_code;
            }

        }
    }
}

$output = "<?php\n\n";
$output .= '$catalog = ' . str_replace(["'", '\\\\'], ['"', '\\'], var_export($catalog, true)) . ';';

$file = fopen('catalog.php', 'w+');
fwrite($file, $output);
fclose($file);
