<?php
/**
 * Generador de Reporte Excel Profesional
 * Produce un archivo .xlsx con múltiples hojas, estilos y formato ejecutivo.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mci_functions.php';

// ── Parámetros ─────────────────────────────────────────────────────────────
$periodo = $_GET['periodo'] ?? date('Y-m');
$anio    = substr($periodo, 0, 4);
$mes     = substr($periodo, 5, 2);

// ── Datos ──────────────────────────────────────────────────────────────────
$kpis         = getDashboardKPIs($pdo, $periodo);
$transacciones= getTransaccionesRecientes($pdo, 500);
$gastosCat    = getGastosPorCategoria($pdo, $periodo);
$balanceAnual = getBalanceAnual($pdo, $anio);
$proyecciones = getProyeccionPorCategoria($pdo, $periodo);
$mci          = getDesviacionCategoriasMCI($pdo, $periodo, 'mes', 'acumulado');

$nombreMeses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril',
                '05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto',
                '09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
$nombreMes = $nombreMeses[$mes] ?? $mes;
$periodoLabel = "$nombreMes $anio";

// ── Helpers ────────────────────────────────────────────────────────────────
function fmt($v) {
    return '$' . number_format((float)$v, 0, '.', ',');
}
function pct($v) {
    return number_format((float)$v, 1) . '%';
}

// ── Construir XLSX manualmente (OpenXML) ─────────────────────────────────
// We build the ZIP structure of an xlsx file with proper styles

class SimpleXlsx {
    private $sheets = [];
    private $sharedStrings = [];
    private $ssIndex = [];

    private $styles = [
        // 0: default
        // 1: header (dark bg, white bold)
        // 2: subheader (medium blue)
        // 3: money (currency format, right)
        // 4: pct
        // 5: row-alt (light gray)
        // 6: title (large bold)
        // 7: red money
        // 8: green money
        // 9: total row
    ];

    public function addSheet(string $name, array $rows): void {
        $this->sheets[] = ['name' => $name, 'rows' => $rows];
    }

    private function ss(string $s): int {
        if (!isset($this->ssIndex[$s])) {
            $this->ssIndex[$s] = count($this->sharedStrings);
            $this->sharedStrings[] = $s;
        }
        return $this->ssIndex[$s];
    }

    private function colName(int $n): string {
        $s = '';
        while ($n >= 0) {
            $s = chr(65 + ($n % 26)) . $s;
            $n = intdiv($n, 26) - 1;
        }
        return $s;
    }

    private function buildStyles(): string {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="3">
    <numFmt numFmtId="164" formatCode="&quot;\$&quot;#,##0"/>
    <numFmt numFmtId="165" formatCode="0.0&quot;%&quot;"/>
    <numFmt numFmtId="166" formatCode="&quot;\$&quot;#,##0;[Red]-&quot;\$&quot;#,##0"/>
  </numFmts>
  <fonts count="6">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="14"/><name val="Calibri"/><color rgb="FF1A1A2E"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
    <font><sz val="10"/><name val="Calibri"/><color rgb="FF64748B"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF10B981"/></font>
  </fonts>
  <fills count="6">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1E293B"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF334155"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFE8F5E9"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFE2E8F0"/></left>
      <right style="thin"><color rgb="FFE2E8F0"/></right>
      <top style="thin"><color rgb="FFE2E8F0"/></top>
      <bottom style="thin"><color rgb="FFE2E8F0"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="12">
    <xf numFmtId="0"   fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0"   fontId="3" fillId="2" borderId="1" xfId="0" applyFill="1" applyFont="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>
    <xf numFmtId="0"   fontId="3" fillId="3" borderId="1" xfId="0" applyFill="1" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center"/></xf>
    <xf numFmtId="0"   fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>
    <xf numFmtId="164" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="0"   fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    <xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyFill="1" applyFont="1" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="0"   fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    <xf numFmtId="164" fontId="5" fillId="5" borderId="1" xfId="0" applyFill="1" applyFont="1" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="0"   fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>
  </cellXfs>
</styleSheet>
XML;
    }

    private function buildSheet(array $rows): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Collect col widths from first row metadata if present
        $colWidths = [];
        if (isset($rows[0]['__cols'])) {
            $colWidths = $rows[0]['__cols'];
            array_shift($rows);
        }
        if ($colWidths) {
            $xml .= '<cols>';
            foreach ($colWidths as $i => $w) {
                $xml .= sprintf('<col min="%d" max="%d" width="%.1f" customWidth="1"/>',
                    $i+1, $i+1, $w);
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        $rowIdx = 1;
        foreach ($rows as $row) {
            if (isset($row['__merge'])) continue;
            $height = $row['__h'] ?? null;
            $xml .= '<row r="' . $rowIdx . '"' . ($height ? " ht=\"$height\" customHeight=\"1\"" : '') . '>';
            $cells = $row['cells'] ?? $row;
            $colIdx = 0;
            foreach ($cells as $cell) {
                $addr = $this->colName($colIdx) . $rowIdx;
                $s    = $cell['s'] ?? 0;
                $v    = $cell['v'] ?? '';
                $t    = $cell['t'] ?? 's'; // s=string, n=number, b=bool

                if ($t === 'n' && $v !== '') {
                    $xml .= "<c r=\"$addr\" s=\"$s\"><v>" . htmlspecialchars((string)$v, ENT_XML1) . "</v></c>";
                } elseif ($v !== '') {
                    $si = $this->ss((string)$v);
                    $xml .= "<c r=\"$addr\" t=\"s\" s=\"$s\"><v>$si</v></c>";
                } else {
                    $xml .= "<c r=\"$addr\" s=\"$s\"/>";
                }
                $colIdx++;
            }
            $xml .= '</row>';
            $rowIdx++;
        }

        $xml .= '</sheetData>';

        // Merges
        $merges = [];
        $rowIdx2 = 1;
        foreach ($rows as $row) {
            if (isset($row['__merge'])) {
                foreach ($row['__merge'] as $m) {
                    $merges[] = $m;
                }
                $rowIdx2--;
            }
            $rowIdx2++;
        }
        if ($merges) {
            $xml .= '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $m) $xml .= "<mergeCell ref=\"$m\"/>";
            $xml .= '</mergeCells>';
        }

        $xml .= '</worksheet>';
        return $xml;
    }

    private function buildSharedStrings(): string {
        $count = count($this->sharedStrings);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= "<sst xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" count=\"$count\" uniqueCount=\"$count\">";
        foreach ($this->sharedStrings as $s) {
            $xml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
        }
        $xml .= '</sst>';
        return $xml;
    }

    private function buildWorkbook(): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheets>';
        foreach ($this->sheets as $i => $sh) {
            $id = $i + 1;
            $name = htmlspecialchars($sh['name'], ENT_XML1);
            $xml .= "<sheet name=\"$name\" sheetId=\"$id\" r:id=\"rId$id\"/>";
        }
        $xml .= '</sheets></workbook>';
        return $xml;
    }

    private function buildWorkbookRels(): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $sh) {
            $id = $i + 1;
            $xml .= "<Relationship Id=\"rId$id\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet$id.xml\"/>";
        }
        $xml .= '<Relationship Id="rIdSS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $xml .= '<Relationship Id="rIdSt" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    public function output(string $filename): void {
        // Build all sheet XMLs first (this populates shared strings)
        $sheetXmls = [];
        foreach ($this->sheets as $sh) {
            $sheetXmls[] = $this->buildSheet($sh['rows']);
        }

        $contentTypes  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $contentTypes .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $contentTypes .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $contentTypes .= '<Default Extension="xml" ContentType="application/xml"/>';
        $contentTypes .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $contentTypes .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $contentTypes .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
        foreach ($this->sheets as $i => $sh) {
            $n = $i + 1;
            $contentTypes .= "<Override PartName=\"/xl/worksheets/sheet$n.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }
        $contentTypes .= '</Types>';

        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',      $contentTypes);
        $zip->addFromString('_rels/.rels',              $rootRels);
        $zip->addFromString('xl/workbook.xml',          $this->buildWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRels());
        $zip->addFromString('xl/styles.xml',            $this->buildStyles());
        $zip->addFromString('xl/sharedStrings.xml',     $this->buildSharedStrings());

        foreach ($sheetXmls as $i => $xml) {
            $zip->addFromString("xl/worksheets/sheet" . ($i+1) . ".xml", $xml);
        }

        $zip->close();
        readfile($tmp);
        unlink($tmp);
    }
}

// ─── STYLE CONSTANTS ──────────────────────────────────────────────────────
// s=0 default | s=1 header-dark | s=2 header-medium | s=3 money | s=4 pct
// s=5 alt-row  | s=6 alt-money  | s=7 title         | s=8 total-money
// s=9 muted    | s=10 green-money| s=11 bold-text

function cell(string $v, int $s=0): array { return ['v'=>$v,'t'=>'s','s'=>$s]; }
function num(float $v, int $s=3):  array { return ['v'=>$v,'t'=>'n','s'=>$s]; }
function empty_cell(int $s=0):     array { return ['v'=>'','t'=>'s','s'=>$s]; }

function hdr(array $cells): array  { return ['cells'=>$cells]; }
function alt(array $cells, bool $isAlt): array {
    return ['cells' => array_map(fn($c) => array_merge($c, ['s'=>$isAlt?($c['s']===3?6:5):$c['s']]), $cells)];
}

// ─── SHEET 1: DASHBOARD EJECUTIVO ────────────────────────────────────────
$semaforo = $kpis['semaforo'] ?? 'verde';
$semaforoLabel = $semaforo === 'rojo' ? '🔴 ALERTA' : ($semaforo === 'amarillo' ? '🟡 PRECAUCIÓN' : '🟢 ÓPTIMO');

$sheet1 = [
    ['__cols' => [42, 22, 22, 18]],
    ['__h'=>28, 'cells' => [cell("REPORTE FINANCIERO EJECUTIVO — $periodoLabel", 7), empty_cell(7), empty_cell(7), empty_cell(7)]],
    ['cells' => [cell('Generado: ' . date('d/M/Y H:i'), 9), empty_cell(), empty_cell(), empty_cell()]],
    ['cells' => array_fill(0, 4, empty_cell())],
    ['__h'=>20, 'cells' => [cell('📊 INDICADORES CLAVE DE DESEMPEÑO (KPIs)', 1), empty_cell(1), empty_cell(1), empty_cell(1)]],
    hdr([cell('Indicador',1), cell('Valor Actual',2), cell('Estado',2), empty_cell(1)]),
    ['cells' => [cell('Presupuesto Mensual Asignado'),    num($kpis['presupuesto'] ?? 0, 3),   cell(fmt($kpis['presupuesto']??0)),              empty_cell()]],
    ['cells' => [cell('Total Gastos del Mes'),            num($kpis['gasto_total'] ?? 0, 3),   cell(pct($kpis['porcentaje_usado']??0).' del pres.'), empty_cell()]],
    ['cells' => [cell('Ingresos del Mes'),                num($kpis['ingreso_total'] ?? 0, 10),cell('Periodo: '.$periodoLabel),                 empty_cell()]],
    ['cells' => [cell('Fondo Disponible'),                num($kpis['dinero_restante'] ?? 0, 3),cell(''),                                      empty_cell()]],
    ['cells' => [cell('Proyección Fin de Mes'),           num($kpis['proyeccion_fin_mes'] ?? 0, 3), cell(''),                                   empty_cell()]],
    ['cells' => [cell('Eficiencia (Ingresos / Gastos)'),  cell(($kpis['eficiencia']??0) . 'x'), cell(''),                                      empty_cell()]],
    ['cells' => [cell('Estado Semáforo'),                 cell($semaforoLabel,11),              cell(''),                                      empty_cell()]],
    ['cells' => array_fill(0, 4, empty_cell())],
    ['__h'=>20, 'cells' => [cell('📅 BALANCE ANUAL '.$anio, 1), empty_cell(1), empty_cell(1), empty_cell(1)]],
    hdr([cell('Concepto',1), cell('Monto',2), empty_cell(1), empty_cell(1)]),
    ['cells' => [cell('Ingresos Totales del Año'),  num($balanceAnual['ingresos_total']??0, 10), empty_cell(), empty_cell()]],
    ['cells' => [cell('Gastos Totales del Año'),    num($balanceAnual['gastos_total']??0, 3),   empty_cell(), empty_cell()]],
    ['__h'=>18, 'cells' => [cell('Balance Neto del Año', 11), num($balanceAnual['balance']??0, ($balanceAnual['balance']??0)>=0?10:3), empty_cell(2), empty_cell()]],
];

$xlsx = new SimpleXlsx();
$xlsx->addSheet('Dashboard KPIs', $sheet1);

// ─── SHEET 2: TRANSACCIONES DETALLADAS ───────────────────────────────────
$sheet2 = [
    ['__cols' => [13, 10, 38, 22, 16]],
    ['__h'=>24, 'cells' => [cell("DETALLE DE TRANSACCIONES — $periodoLabel", 7), empty_cell(7), empty_cell(7), empty_cell(7), empty_cell(7)]],
    ['cells' => array_fill(0, 5, empty_cell())],
    hdr([cell('Fecha',1), cell('Tipo',1), cell('Concepto',1), cell('Categoría',1), cell('Monto',1)]),
];

$totalG = 0; $totalI = 0; $alt = false;
foreach ($transacciones as $tx) {
    $monto = (float)$tx['monto'];
    $isGasto = $tx['tipo'] === 'gasto';
    if ($isGasto) $totalG += $monto; else $totalI += $monto;
    $montoStyle = $alt ? 6 : 3;
    $txtStyle   = $alt ? 5 : 0;
    $sheet2[] = ['cells' => [
        cell($tx['fecha'], $txtStyle),
        cell($isGasto ? 'GASTO' : 'INGRESO', $txtStyle),
        cell($tx['concepto'], $txtStyle),
        cell($tx['categoria'] ?? 'Ingreso', $txtStyle),
        num($isGasto ? -$monto : $monto, $montoStyle),
    ]];
    $alt = !$alt;
}
$sheet2[] = ['cells' => array_fill(0, 5, empty_cell())];
$sheet2[] = ['__h'=>18, 'cells' => [empty_cell(1), empty_cell(1), empty_cell(1), cell('Total Gastos:', 1), num(-$totalG, 8)]];
$sheet2[] = ['cells' => [empty_cell(1), empty_cell(1), empty_cell(1), cell('Total Ingresos:', 1), num($totalI, 10)]];
$sheet2[] = ['cells' => [empty_cell(1), empty_cell(1), empty_cell(1), cell('Balance Neto:', 1), num($totalI - $totalG, ($totalI-$totalG)>=0?10:8)]];

$xlsx->addSheet('Transacciones', $sheet2);

// ─── SHEET 3: PROYECCIÓN POR CATEGORÍA ───────────────────────────────────
$sheet3 = [
    ['__cols' => [28, 18, 16, 18, 12]],
    ['__h'=>24, 'cells' => [cell("PROYECCIÓN POR CATEGORÍA — $periodoLabel", 7), empty_cell(7), empty_cell(7), empty_cell(7), empty_cell(7)]],
    ['cells' => array_fill(0, 5, empty_cell())],
    hdr([cell('Categoría',1), cell('Ejecutado',1), cell('Prom. Diario',1), cell('Proyección Fin Mes',1), cell('% Ejecución',1)]),
];
$alt2 = false;
foreach ($proyecciones as $p) {
    $pctVal = $p['proyeccion'] > 0 ? round($p['ejecutado'] / $p['proyeccion'] * 100, 1) : 0;
    $ts = $alt2 ? 5 : 0; $ms = $alt2 ? 6 : 3;
    $semStr = $pctVal > 100 ? '🔴' : ($pctVal > 80 ? '🟡' : '🟢');
    $sheet3[] = ['cells' => [
        cell($semStr . ' ' . $p['nombre'], $ts),
        num($p['ejecutado'], $ms),
        num($p['prom_diario'], $ms),
        num($p['proyeccion'], $ms),
        cell(number_format($pctVal,1).'%', $alt2 ? 5 : 0),
    ]];
    $alt2 = !$alt2;
}
$xlsx->addSheet('Proyeccion Categorias', $sheet3);

// ─── SHEET 4: COMPARATIVO MENSUAL ────────────────────────────────────────
$sheet4 = [
    ['__cols' => [14, 18, 18, 18]],
    ['__h'=>24, 'cells' => [cell("COMPARATIVO MENSUAL — AÑO $anio", 7), empty_cell(7), empty_cell(7), empty_cell(7)]],
    ['cells' => array_fill(0, 4, empty_cell())],
    hdr([cell('Mes',1), cell('Ingresos',1), cell('Gastos',1), cell('Balance',1)]),
];
$alt3 = false;
foreach (($balanceAnual['meses'] ?? []) as $m) {
    $bal = $m['ingresos'] - $m['gastos'];
    $ts = $alt3 ? 5 : 0; $ms = $alt3 ? 6 : 3;
    $sheet4[] = ['cells' => [
        cell($m['mes'], $ts),
        num($m['ingresos'], $alt3 ? 6 : 10),
        num($m['gastos'], $ms),
        num($bal, $bal >= 0 ? ($alt3 ? 6 : 10) : $ms),
    ]];
    $alt3 = !$alt3;
}
$xlsx->addSheet('Comparativo Mensual', $sheet4);

// ─── SHEET 5: BITÁCORA MCI 2026 ──────────────────────────────────────────
$sheet5 = [
    ['__cols' => [25, 20, 20, 20, 20]],
    ['__h'=>24, 'cells' => [cell("BITÁCORA MCI 2026 — ACUMULADO A $nombreMes", 7), empty_cell(7), empty_cell(7), empty_cell(7), empty_cell(7)]],
    ['cells' => array_fill(0, 5, empty_cell())],
    hdr([cell('Categoría',1), cell('Meta Anual',1), cell('Estimado',1), cell('Ejecutado',1), cell('Diferencia',1)]),
];

$alt4 = false;
foreach (($mci['detalles'] ?? []) as $d) {
    $ts = $alt4 ? 5 : 0; 
    $ms = $alt4 ? 6 : 3;
    $sheet5[] = ['cells' => [
        cell($d['nombre'], $ts),
        num($d['mci_anual'], $ms),
        num($d['estimado'], $ms),
        num($d['ejecutado'], $ms),
        num(abs($d['diferencia']), $ms)
    ]];
    $alt4 = !$alt4;
}
$sheet5[] = ['cells' => array_fill(0, 5, empty_cell())];
$totM = $mci['totales'] ?? [];
$sheet5[] = ['__h'=>18, 'cells' => [
    cell('TOTAL ACUMULADO', 1), 
    num($totM['mci_anual'] ?? 0, 8), 
    num($totM['estimado'] ?? 0, 8), 
    num($totM['ejecutado'] ?? 0, 8), 
    num(abs($totM['diferencia'] ?? 0), 10)
]];
$xlsx->addSheet('Bitacora MCI', $sheet5);

// ─── SALIDA ───────────────────────────────────────────────────────────────
$filename = "Reporte_Financiero_{$anio}-{$mes}.xlsx";
$xlsx->output($filename);
