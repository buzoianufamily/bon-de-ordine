<?php
/**
 * Generator XLSX nativ (zero dependinte) — produce un fisier .xlsx real
 * (arhiva OOXML) cu mai multe foi si GRAFICE native Excel (linie, coloane,
 * bare, placinta). Foloseste doar ZipArchive (extensie standard PHP).
 *
 * Folosit de raportul de statistici (Export Excel cu grafice).
 *
 * Utilizare:
 *   $xl = new Xlsx(['title'=>'Raport', 'author'=>'...', 'accent'=>'#2563eb']);
 *   $s  = $xl->add_sheet('Rezumat');
 *   $s->row(['Coloana A', 'Coloana B'], Xlsx::S_HEAD);
 *   $s->row(['text', 123]);
 *   $s->chart([...]);
 *   $xl->download('raport.xlsx');
 */

class Xlsx {
    // indecsi de stil (vezi styles.xml din build_styles)
    const S_DEFAULT = 0;
    const S_BOLD    = 1;
    const S_TITLE   = 2;
    const S_HEAD    = 3;   // antet tabel (fundal accent, text alb)
    const S_NUM1    = 4;   // numar cu o zecimala
    const S_MUTED   = 5;   // text gri mic
    const S_BIGNUM  = 6;   // KPI: numar mare

    /** @var XlsxSheet[] */
    public array $sheets = [];
    public array $meta;
    private int $chartCount = 0;

    public function __construct(array $meta = []) {
        $this->meta = $meta + ['title' => 'Raport', 'author' => 'Bon de ordine', 'accent' => '#2563eb'];
    }

    public function add_sheet(string $name): XlsxSheet {
        $s = new XlsxSheet($this, $name);
        $this->sheets[] = $s;
        return $s;
    }

    /** Aloca un index global de grafic (pentru numele partilor). */
    public function next_chart_index(): int { return ++$this->chartCount; }

    /* ---------- helpers statice ---------- */
    public static function col_letter(int $n): string { // 1=A
        $s = '';
        while ($n > 0) { $m = ($n - 1) % 26; $s = chr(65 + $m) . $s; $n = intdiv($n - 1, 26); }
        return $s;
    }
    public static function x(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
    public static function hex(string $c): string { return strtoupper(ltrim(trim($c), '#')) ?: '2563EB'; }

    /** Construieste binarul .xlsx in memorie (returneaza string). */
    public function build(): string {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        // colecteaza partile de desen/grafic
        $drawings = []; // sheetIdx => ['file'=>.., 'charts'=>[chartXml,...], 'anchors'=>[..]]
        foreach ($this->sheets as $i => $sheet) {
            if ($sheet->charts) $drawings[$i] = $sheet;
        }

        // ---- [Content_Types].xml ----
        $ov = '';
        foreach ($this->sheets as $i => $s) {
            $n = $i + 1;
            $ov .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        foreach (array_keys($drawings) as $i) {
            $n = $i + 1;
            $ov .= '<Override PartName="/xl/drawings/drawing'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
        }
        foreach ($drawings as $sheet) {
            foreach ($sheet->charts as $c) {
                $ov .= '<Override PartName="/xl/charts/chart'.$c['_idx'].'.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>';
            }
        }
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $ov
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
        $zip->addFromString('[Content_Types].xml', $ct);

        // ---- _rels/.rels ----
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
          . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
          . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
          . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
          . '</Relationships>');

        // ---- docProps ----
        $date = gmdate('Y-m-d\TH:i:s\Z');
        $zip->addFromString('docProps/core.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
          . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
          . '<dc:title>'.self::x($this->meta['title']).'</dc:title>'
          . '<dc:creator>'.self::x($this->meta['author']).'</dc:creator>'
          . '<cp:lastModifiedBy>'.self::x($this->meta['author']).'</cp:lastModifiedBy>'
          . '<dcterms:created xsi:type="dcterms:W3CDTF">'.$date.'</dcterms:created>'
          . '<dcterms:modified xsi:type="dcterms:W3CDTF">'.$date.'</dcterms:modified>'
          . '</cp:coreProperties>');
        $zip->addFromString('docProps/app.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
          . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
          . '<Application>Bon de ordine</Application></Properties>');

        // ---- xl/workbook.xml + rels ----
        $sheetsXml = ''; $wbRels = '';
        foreach ($this->sheets as $i => $s) {
            $n = $i + 1;
            $sheetsXml .= '<sheet name="'.self::x($s->name).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $wbRels   .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        }
        $stylesRid = count($this->sheets) + 1;
        $wbRels .= '<Relationship Id="rId'.$stylesRid.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
          . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
          . '<sheets>'.$sheetsXml.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$wbRels.'</Relationships>');

        // ---- xl/styles.xml ----
        $zip->addFromString('xl/styles.xml', $this->build_styles());

        // ---- foile + desene + grafice ----
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $hasDrawing = isset($drawings[$i]);
            $zip->addFromString("xl/worksheets/sheet$n.xml", $sheet->build_sheet_xml($hasDrawing));
            if ($hasDrawing) {
                $zip->addFromString("xl/worksheets/_rels/sheet$n.xml.rels",
                    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                  . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                  . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing'.$n.'.xml"/>'
                  . '</Relationships>');
                // drawing + rels
                [$drawXml, $drawRels] = $sheet->build_drawing_xml($n);
                $zip->addFromString("xl/drawings/drawing$n.xml", $drawXml);
                $zip->addFromString("xl/drawings/_rels/drawing$n.xml.rels", $drawRels);
                // grafice
                foreach ($sheet->charts as $c) {
                    $zip->addFromString('xl/charts/chart'.$c['_idx'].'.xml', $this->build_chart_xml($sheet, $c));
                }
            }
        }

        $zip->close();
        $bin = file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    /** Trimite fisierul ca download si opreste executia. */
    public function download(string $filename): void {
        $bin = $this->build();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $filename).'"');
        header('Content-Length: '.strlen($bin));
        header('Cache-Control: no-store');
        echo $bin;
        exit;
    }

    /* ---------------- styles.xml ---------------- */
    private function build_styles(): string {
        $acc = self::hex($this->meta['accent']);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0"/></numFmts>'
        . '<fonts count="7">'
        .   '<font><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/></font>'                         // 0 default
        .   '<font><b/><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/></font>'                     // 1 bold
        .   '<font><b/><sz val="18"/><color rgb="FF'.$acc.'"/><name val="Calibri"/></font>'                   // 2 title
        .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'                     // 3 header (alb)
        .   '<font><sz val="10"/><color rgb="FF6B7280"/><name val="Calibri"/></font>'                         // 4 muted
        .   '<font><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/></font>'                         // 5 (num) = default
        .   '<font><b/><sz val="22"/><color rgb="FF111827"/><name val="Calibri"/></font>'                     // 6 big num
        . '</fonts>'
        . '<fills count="3">'
        .   '<fill><patternFill patternType="none"/></fill>'
        .   '<fill><patternFill patternType="gray125"/></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FF'.$acc.'"/><bgColor indexed="64"/></patternFill></fill>'  // 2 accent
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="7">'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                          // 0
        .   '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                            // 1
        .   '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                            // 2
        .   '<xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf>' // 3
        .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                                  // 4
        .   '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                            // 5
        .   '<xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                            // 6
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
    }

    /* ---------------- chart XML ---------------- */
    private function build_chart_xml(XlsxSheet $sheet, array $c): string {
        $C = 'http://schemas.openxmlformats.org/drawingml/2006/chart';
        $A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
        $R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $sn = $sheet->ref_name();
        $type = $c['type'] ?? 'col';

        // cache categorii
        $catVals = $c['cat']['vals'] ?? [];
        $strCache = '<c:strCache><c:ptCount val="'.count($catVals).'"/>';
        foreach ($catVals as $k => $v) $strCache .= '<c:pt idx="'.$k.'"><c:v>'.self::x((string)$v).'</c:v></c:pt>';
        $strCache .= '</c:strCache>';
        $catXml = '<c:cat><c:strRef><c:f>'.self::x($sn.'!'.$c['cat']['ref']).'</c:f>'.$strCache.'</c:strRef></c:cat>';

        $axId1 = 111111111; $axId2 = 222222222;
        $body = '';

        if ($type === 'pie') {
            $ser = $c['series'][0];
            $vals = $ser['vals'];
            $numCache = '<c:numCache><c:formatCode>General</c:formatCode><c:ptCount val="'.count($vals).'"/>';
            foreach ($vals as $k => $v) $numCache .= '<c:pt idx="'.$k.'"><c:v>'.(0 + $v).'</c:v></c:pt>';
            $numCache .= '</c:numCache>';
            $dPts = '';
            foreach (($ser['colors'] ?? []) as $k => $col) {
                $dPts .= '<c:dPt><c:idx val="'.$k.'"/><c:bubble3D val="0"/><c:spPr><a:solidFill><a:srgbClr val="'.self::hex($col).'"/></a:solidFill></c:spPr></c:dPt>';
            }
            $body = '<c:pieChart><c:varyColors val="1"/>'
                  . '<c:ser><c:idx val="0"/><c:order val="0"/>'
                  . $dPts
                  . '<c:dLbls><c:showLegendKey val="0"/><c:showVal val="1"/><c:showCatName val="0"/><c:showSerName val="0"/><c:showPercent val="0"/><c:showBubbleSize val="0"/></c:dLbls>'
                  . $catXml
                  . '<c:val><c:numRef><c:f>'.self::x($sn.'!'.$ser['ref']).'</c:f>'.$numCache.'</c:numRef></c:val>'
                  . '</c:ser><c:firstSliceAng val="0"/></c:pieChart>';
        } else {
            // bar / col / line — pot avea mai multe serii
            $serXml = '';
            foreach ($c['series'] as $si => $ser) {
                $vals = $ser['vals'];
                $numCache = '<c:numCache><c:formatCode>General</c:formatCode><c:ptCount val="'.count($vals).'"/>';
                foreach ($vals as $k => $v) $numCache .= '<c:pt idx="'.$k.'"><c:v>'.(0 + $v).'</c:v></c:pt>';
                $numCache .= '</c:numCache>';

                $tx = '';
                if (!empty($ser['name'])) {
                    $tx = '<c:tx><c:strRef><c:f>'.self::x($sn.'!'.($ser['name_ref'] ?? 'A1')).'</c:f>'
                        . '<c:strCache><c:ptCount val="1"/><c:pt idx="0"><c:v>'.self::x($ser['name']).'</c:v></c:pt></c:strCache></c:strRef></c:tx>';
                }
                $spPr = '';
                if ($type === 'line') {
                    $col = self::hex($ser['color'] ?? $this->meta['accent']);
                    $spPr = '<c:spPr><a:ln w="28575"><a:solidFill><a:srgbClr val="'.$col.'"/></a:solidFill></a:ln></c:spPr>';
                } elseif (!empty($ser['color'])) {
                    $spPr = '<c:spPr><a:solidFill><a:srgbClr val="'.self::hex($ser['color']).'"/></a:solidFill></c:spPr>';
                }
                $dPts = '';
                if ($type !== 'line') {
                    foreach (($ser['colors'] ?? []) as $k => $col) {
                        $dPts .= '<c:dPt><c:idx val="'.$k.'"/><c:invertIfNegative val="0"/><c:bubble3D val="0"/><c:spPr><a:solidFill><a:srgbClr val="'.self::hex($col).'"/></a:solidFill></c:spPr></c:dPt>';
                    }
                }
                $marker = ($type === 'line') ? '<c:marker><c:symbol val="circle"/><c:size val="5"/></c:marker>' : '';
                $smooth = ($type === 'line') ? '<c:smooth val="0"/>' : '';
                $serXml .= '<c:ser><c:idx val="'.$si.'"/><c:order val="'.$si.'"/>'
                    . $tx . $spPr . $marker . $dPts . $catXml
                    . '<c:val><c:numRef><c:f>'.self::x($sn.'!'.$ser['ref']).'</c:f>'.$numCache.'</c:numRef></c:val>'
                    . $smooth . '</c:ser>';
            }
            if ($type === 'line') {
                $body = '<c:lineChart><c:grouping val="standard"/><c:varyColors val="0"/>'.$serXml
                      . '<c:marker val="1"/><c:axId val="'.$axId1.'"/><c:axId val="'.$axId2.'"/></c:lineChart>';
            } else {
                $dir = ($type === 'bar') ? 'bar' : 'col';
                $body = '<c:barChart><c:barDir val="'.$dir.'"/><c:grouping val="clustered"/><c:varyColors val="0"/>'.$serXml
                      . '<c:gapWidth val="60"/><c:axId val="'.$axId1.'"/><c:axId val="'.$axId2.'"/></c:barChart>';
            }
            // axe (pentru bar, axa categoriilor e in stanga jos; e ok asa)
            $body .= '<c:catAx><c:axId val="'.$axId1.'"/><c:scaling><c:orientation val="minMax"/></c:scaling>'
                  . '<c:delete val="0"/><c:axPos val="'.($type === 'bar' ? 'l' : 'b').'"/>'
                  . '<c:crossAx val="'.$axId2.'"/></c:catAx>'
                  . '<c:valAx><c:axId val="'.$axId2.'"/><c:scaling><c:orientation val="minMax"/></c:scaling>'
                  . '<c:delete val="0"/><c:axPos val="'.($type === 'bar' ? 'b' : 'l').'"/>'
                  . '<c:majorGridlines/><c:crossAx val="'.$axId1.'"/></c:valAx>';
        }

        $title = '';
        if (!empty($c['title'])) {
            $title = '<c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/>'
                   . '<a:p><a:pPr><a:defRPr sz="1100" b="1"/></a:pPr><a:r><a:rPr lang="ro-RO" sz="1100" b="1"/>'
                   . '<a:t>'.self::x($c['title']).'</a:t></a:r></a:p></c:rich></c:tx><c:overlay val="0"/></c:title>';
        }
        $legend = ($type === 'pie' || (isset($c['legend']) && $c['legend']))
            ? '<c:legend><c:legendPos val="'.($c['legend_pos'] ?? 'b').'"/><c:overlay val="0"/></c:legend>' : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<c:chartSpace xmlns:c="'.$C.'" xmlns:a="'.$A.'" xmlns:r="'.$R.'">'
            . '<c:chart>'.$title.'<c:autoTitleDeleted val="'.($title ? '0' : '1').'"/>'
            . '<c:plotArea><c:layout/>'.$body.'</c:plotArea>'.$legend
            . '<c:plotVisOnly val="1"/><c:dispBlanksAs val="gap"/></c:chart></c:chartSpace>';
    }
}

/** O foaie din workbook. */
class XlsxSheet {
    public Xlsx $book;
    public string $name;
    public array $rows = [];      // index 0-based de randuri; fiecare = ['cells'=>[col=>cell]]
    public array $colWidths = []; // col(1-based) => latime
    public array $merges = [];    // ['A1:C1', ...]
    public array $charts = [];    // specuri grafice
    private int $r = 0;           // numarul de randuri scrise

    public function __construct(Xlsx $book, string $name) {
        $this->book = $book;
        // numele foii: max 31 car, fara caractere interzise
        $this->name = mb_substr(str_replace(['\\','/','?','*','[',']',':'], ' ', $name), 0, 31);
    }

    /** Scrie un rand. $cells = valori scalare sau ['v'=>,'s'=>,'t'=>]. Returneaza randul (1-based). */
    public function row(array $cells, ?int $style = null): int {
        $this->r++;
        $rowCells = [];
        $col = 0;
        foreach ($cells as $cell) {
            $col++;
            if ($cell === null || $cell === '') { continue; } // celula goala
            if (!is_array($cell)) $cell = ['v' => $cell];
            if ($style !== null && !isset($cell['s'])) $cell['s'] = $style;
            $rowCells[$col] = $cell;
        }
        $this->rows[$this->r] = $rowCells;
        return $this->r;
    }

    public function blank(): int { return $this->row([]); }
    public function set_col_widths(array $w): void { $this->colWidths = $w; }   // [1=>20, 2=>14,...]
    public function merge(string $range): void { $this->merges[] = $range; }
    public function row_count(): int { return $this->r; }

    /** Adauga un grafic ancorat in foaie. */
    public function chart(array $spec): void {
        $spec['_idx'] = $this->book->next_chart_index();
        $this->charts[] = $spec;
    }

    /** Numele foii asa cum apare in referintele de grafic (mereu citat). */
    public function ref_name(): string {
        return "'" . str_replace("'", "''", $this->name) . "'";
    }

    public function build_sheet_xml(bool $hasDrawing): string {
        $maxCol = 1;
        foreach ($this->rows as $cells) foreach ($cells as $col => $_) $maxCol = max($maxCol, $col);
        $dimEnd = Xlsx::col_letter($maxCol) . max(1, $this->r);

        $cols = '';
        if ($this->colWidths) {
            $cols = '<cols>';
            foreach ($this->colWidths as $c => $w) $cols .= '<col min="'.$c.'" max="'.$c.'" width="'.(0 + $w).'" customWidth="1"/>';
            $cols .= '</cols>';
        }

        $data = '';
        foreach ($this->rows as $ri => $cells) {
            if (!$cells) { $data .= '<row r="'.$ri.'"/>'; continue; }
            $cellXml = '';
            foreach ($cells as $col => $cell) {
                $ref = Xlsx::col_letter($col) . $ri;
                $s = isset($cell['s']) ? ' s="'.(int)$cell['s'].'"' : '';
                $v = $cell['v'];
                $isNum = (isset($cell['t']) && $cell['t'] === 'n') || (!isset($cell['t']) && is_numeric($v) && !is_string($v));
                if ($isNum) {
                    $cellXml .= '<c r="'.$ref.'"'.$s.'><v>'.(0 + $v).'</v></c>';
                } else {
                    $cellXml .= '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.Xlsx::x((string)$v).'</t></is></c>';
                }
            }
            $data .= '<row r="'.$ri.'">'.$cellXml.'</row>';
        }

        $mergeXml = '';
        if ($this->merges) {
            $mergeXml = '<mergeCells count="'.count($this->merges).'">';
            foreach ($this->merges as $m) $mergeXml .= '<mergeCell ref="'.Xlsx::x($m).'"/>';
            $mergeXml .= '</mergeCells>';
        }

        $drawing = $hasDrawing ? '<drawing r:id="rId1"/>' : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:'.$dimEnd.'"/>'
            . '<sheetViews><sheetView workbookViewId="0"'.($this->name === 'Rezumat' ? ' tabSelected="1"' : '').'/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . $cols
            . '<sheetData>'.$data.'</sheetData>'
            . $mergeXml
            . $drawing
            . '</worksheet>';
    }

    /** Construieste drawingN.xml + rels (toate graficele din foaie). */
    public function build_drawing_xml(int $sheetNo): array {
        $anchors = ''; $rels = ''; $rid = 0; $shapeId = 1;
        foreach ($this->charts as $c) {
            $rid++; $shapeId++;
            $a = $c['anchor'] ?? ['col' => 0, 'row' => 0, 'col2' => 8, 'row2' => 16];
            $anchors .= '<xdr:twoCellAnchor>'
                . '<xdr:from><xdr:col>'.$a['col'].'</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>'.$a['row'].'</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
                . '<xdr:to><xdr:col>'.$a['col2'].'</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>'.$a['row2'].'</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>'
                . '<xdr:graphicFrame macro="">'
                . '<xdr:nvGraphicFramePr><xdr:cNvPr id="'.$shapeId.'" name="Grafic '.$rid.'"/><xdr:cNvGraphicFramePr/></xdr:nvGraphicFramePr>'
                . '<xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm>'
                . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">'
                . '<c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" '
                . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="rId'.$rid.'"/>'
                . '</a:graphicData></a:graphic></xdr:graphicFrame><xdr:clientData/></xdr:twoCellAnchor>';
            $rels .= '<Relationship Id="rId'.$rid.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart'.$c['_idx'].'.xml"/>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" '
            . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'.$anchors.'</xdr:wsDr>';
        $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
        return [$xml, $relsXml];
    }
}
