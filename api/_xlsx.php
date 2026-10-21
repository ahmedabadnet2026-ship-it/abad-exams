<?php
// Minimal dependency-free .xlsx reader/writer (an .xlsx is a ZIP of XML parts). Needs only zlib (gzinflate).
// Reader: first worksheet → array of rows (string cells; date-formatted numbers stay as Excel serials, see abd_imp_date()).
// Writer: tiny "stored" (uncompressed) workbook with one sheet of inline strings — used for the import template.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }

const ABD_XLSX_MAX_PART = 8 * 1024 * 1024;   // max uncompressed size of one XML part (zip-bomb guard)

/** Read the ZIP central directory → [name => contents] for the requested names only. */
function abd_zip_read(string $bin, array $want): array {
  $eocd = strrpos($bin, "PK\x05\x06");
  if ($eocd === false || strlen($bin) < $eocd + 22) throw new RuntimeException('not a zip');
  $e = unpack('vdisk/vcd_disk/ventries_disk/ventries/Vcd_size/Vcd_off', substr($bin, $eocd + 4, 16));
  $p = $e['cd_off']; $out = [];
  for ($i = 0; $i < $e['entries']; $i++) {
    if (substr($bin, $p, 4) !== "PK\x01\x02") throw new RuntimeException('bad zip directory');
    $h = unpack('vver/vneed/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen/vclen/vdisk/vint/Vext/Voff', substr($bin, $p + 4, 42));
    $name = substr($bin, $p + 46, $h['nlen']);
    $p += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
    if (!in_array($name, $want, true)) continue;
    if ($h['usize'] > ABD_XLSX_MAX_PART || $h['flag'] & 1) throw new RuntimeException('zip part too large or encrypted');
    $lh = unpack('vnlen/velen', substr($bin, $h['off'] + 26, 4));
    $data = substr($bin, $h['off'] + 30 + $lh['nlen'] + $lh['elen'], $h['csize']);
    if ($h['method'] === 8) $data = @gzinflate($data, ABD_XLSX_MAX_PART);
    elseif ($h['method'] !== 0) throw new RuntimeException('unsupported zip method');
    if ($data === false) throw new RuntimeException('zip inflate failed');
    $out[$name] = $data;
  }
  return $out;
}

function abd_xml_text(string $s): string { return html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_XML1, 'UTF-8'); }

/** First worksheet of an .xlsx as rows of strings (cell gaps filled with ''). */
function abd_xlsx_rows(string $bin, int $maxRows = 2000): array {
  $parts = abd_zip_read($bin, ['xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/sharedStrings.xml']);
  $sheet = 'xl/worksheets/sheet1.xml';
  if (isset($parts['xl/workbook.xml'], $parts['xl/_rels/workbook.xml.rels'])
      && preg_match('/<(?:\w+:)?sheet\b[^>]*\br:id="([^"]+)"/', $parts['xl/workbook.xml'], $m)
      && preg_match_all('/<(?:\w+:)?Relationship\b[^>]*>/', $parts['xl/_rels/workbook.xml.rels'], $rels))
    foreach ($rels[0] as $tag) if (str_contains($tag, 'Id="' . $m[1] . '"') && preg_match('/Target="([^"]+)"/', $tag, $t)) {
      $sheet = str_starts_with($t[1], '/') ? ltrim($t[1], '/') : 'xl/' . $t[1]; break;
    }
  $ss = [];
  if (isset($parts['xl/sharedStrings.xml']) && preg_match_all('~<(?:\w+:)?si>(.*?)</(?:\w+:)?si>~s', $parts['xl/sharedStrings.xml'], $mm))
    foreach ($mm[1] as $si) { preg_match_all('~<(?:\w+:)?t\b[^>]*>(.*?)</(?:\w+:)?t>~s', preg_replace('~<(?:\w+:)?rPh\b.*?</(?:\w+:)?rPh>~s', '', $si), $tt); $ss[] = abd_xml_text(implode('', $tt[1])); }
  $xml = abd_zip_read($bin, [$sheet])[$sheet] ?? null;
  if ($xml === null) throw new RuntimeException('no worksheet');
  $rows = [];
  preg_match_all('~<(?:\w+:)?row\b[^>]*>(.*?)</(?:\w+:)?row>~s', $xml, $rr);
  foreach ($rr[1] as $row) {
    if (count($rows) >= $maxRows) break;
    $cells = [];
    preg_match_all('~<(?:\w+:)?c\b([^>]*?)(?:/>|>(.*?)</(?:\w+:)?c>)~s', $row, $cc, PREG_SET_ORDER);
    foreach ($cc as $c) {
      $attr = $c[1]; $in = $c[2] ?? '';
      $col = preg_match('/\br="([A-Z]+)\d+"/', $attr, $a) ? $a[1] : null;
      $type = preg_match('/\bt="(\w+)"/', $attr, $a) ? $a[1] : 'n';
      if ($type === 'inlineStr') { preg_match_all('~<(?:\w+:)?t\b[^>]*>(.*?)</(?:\w+:)?t>~s', $in, $tt); $v = abd_xml_text(implode('', $tt[1])); }
      else { $v = preg_match('~<(?:\w+:)?v>(.*?)</(?:\w+:)?v>~s', $in, $vv) ? abd_xml_text($vv[1]) : ''; if ($type === 's') $v = $ss[(int)$v] ?? ''; }
      $idx = $col !== null ? array_reduce(str_split($col), fn($n, $ch) => $n * 26 + ord($ch) - 64, 0) - 1 : count($cells);
      if ($idx > 50) continue;
      $cells[$idx] = trim($v);
    }
    if (!$cells) { $rows[] = []; continue; }
    $r = array_fill(0, max(array_keys($cells)) + 1, ''); foreach ($cells as $k => $v) $r[$k] = $v; $rows[] = $r;
  }
  return $rows;
}

/** Build a one-sheet .xlsx (stored ZIP, inline strings). $rows = array of string arrays; first row bold-free header. */
function abd_xlsx_build(array $rows, string $sheetName = 'Sheet1'): string {
  $x = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
  $sd = '';
  foreach (array_values($rows) as $i => $r) {
    $sd .= '<row r="' . ($i + 1) . '">';
    foreach (array_values($r) as $j => $v) { $col = ''; $n = $j + 1; while ($n > 0) { $m = ($n - 1) % 26; $col = chr(65 + $m) . $col; $n = intdiv($n - 1, 26); }
      $sd .= '<c r="' . $col . ($i + 1) . '" t="inlineStr"><is><t>' . $x((string)$v) . '</t></is></c>'; }
    $sd .= '</row>';
  }
  $files = [
    '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
    '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
    'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView rightToLeft="1"/></bookViews><sheets><sheet name="' . $x($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
    'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
    'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews><cols><col min="1" max="4" width="28" customWidth="1"/></cols><sheetData>' . $sd . '</sheetData></worksheet>',
  ];
  $zip = ''; $cd = ''; $dt = [0, 0x21];   // 1980-01-01 00:00
  foreach ($files as $name => $data) {
    $crc = crc32($data); $len = strlen($data); $off = strlen($zip);
    $zip .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $dt[0], $dt[1], $crc, $len, $len, strlen($name), 0) . $name . $data;
    $cd .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $dt[0], $dt[1], $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, $off) . $name;
  }
  return $zip . $cd . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($cd), strlen($zip), 0);
}
