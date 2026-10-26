<?php
// Imali Smart - Professional Report Generation Engine
// Generates PDF statements, Excel-compatible spreadsheets, and CSV exports from user ledger records.
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Escapes strings for PDF text objects.
 */
function pdf_escape_text(string $text): string
{
    $clean = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    // Replace non-ASCII / UTF-8 characters with standard readable equivalents
    $clean = str_replace(
        ['’', '‘', '“', '”', '–', '—', '·', '…', 'R ', 'ZAR '],
        ["'", "'", '"', '"', '-', '-', '*', '...', 'R ', 'R '],
        $clean
    );
    return preg_replace('/[^\x20-\x7E]/', '', $clean) ?: '';
}

/**
 * Generates an authentic binary PDF 1.4 document in pure PHP without external dependencies.
 */
function generate_pure_pdf_document(string $title, array $meta, array $summary, array $columns, array $rows, string $scopeLabel = 'Personal'): string
{
    $objects = [];
    $pdf = "%PDF-1.4\n";

    $addObject = function (string $content) use (&$objects, &$pdf): int {
        $id = count($objects) + 1;
        $objects[$id] = strlen($pdf);
        $pdf .= "$id 0 obj\n$content\nendobj\n";
        return $id;
    };

    $catalogId = $addObject('<< /Type /Catalog /Pages 2 0 R >>');
    $fontNormId = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
    $fontBoldId = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');

    // Build the page drawing stream
    $s = "";

    // Brand Banner (top dark emerald rectangle)
    $s .= "q\n";
    $s .= "0.07 0.22 0.18 rg\n"; // Emerald #12382e
    $s .= "40 760 515 50 re f\n";
    $s .= "Q\n";

    // Brand Title in White
    $s .= "BT\n";
    $s .= "/F2 16 Tf\n";
    $s .= "1 1 1 rg\n"; // White text
    $s .= "55 788 Td (" . pdf_escape_text("IMALI SMART - " . strtoupper($title)) . ") Tj\n";
    $s .= "/F1 9 Tf\n";
    $s .= "55 772 Td (" . pdf_escape_text("SOVEREIGN FINANCIAL WORKSPACE - SOUTH AFRICA - POPIA COMPLIANT") . ") Tj\n";
    $s .= "ET\n";

    // Metadata section (User, Period, Scope, Timestamp)
    $s .= "BT\n";
    $s .= "0.15 0.15 0.15 rg\n";
    $s .= "/F2 10 Tf\n";
    $s .= "42 735 Td (Account: ) Tj\n";
    $s .= "/F1 10 Tf\n";
    $s .= "(" . pdf_escape_text($meta['user_name'] ?? 'Authorized User') . " <" . pdf_escape_text($meta['email'] ?? '') . ">) Tj\n";
    $s .= "ET\n";

    $s .= "BT\n";
    $s .= "0.15 0.15 0.15 rg\n";
    $s .= "/F2 10 Tf\n";
    $s .= "340 735 Td (Period: ) Tj\n";
    $s .= "/F1 10 Tf\n";
    $s .= "(" . pdf_escape_text(($meta['from'] ?? '') . " to " . ($meta['to'] ?? '')) . ") Tj\n";
    $s .= "ET\n";

    $s .= "BT\n";
    $s .= "0.3 0.3 0.3 rg\n";
    $s .= "/F1 9 Tf\n";
    $s .= "42 720 Td (Scope: " . pdf_escape_text($scopeLabel) . "   |   Currency: ZAR (R)   |   Generated: " . pdf_escape_text(date('Y-m-d H:i')) . ") Tj\n";
    $s .= "ET\n";

    // Summary Metric Cards Box
    if (!empty($summary)) {
        $s .= "q\n";
        $s .= "0.94 0.96 0.95 rg\n"; // Light panel background
        $s .= "40 655 515 50 re f\n";
        $s .= "0.80 0.85 0.82 RG 1 w\n";
        $s .= "40 655 515 50 re s\n";
        $s .= "Q\n";

        $cardCount = count($summary);
        $cardWidth = 515 / max(1, $cardCount);
        $idx = 0;
        foreach ($summary as $label => $val) {
            $x = 45 + ($idx * $cardWidth);
            $s .= "BT\n";
            $s .= "0.4 0.45 0.4 rg /F1 8.5 Tf\n";
            $s .= "$x 690 Td (" . pdf_escape_text(strtoupper($label)) . ") Tj\n";
            $s .= "0.08 0.15 0.12 rg /F2 12 Tf\n";
            $s .= "$x 670 Td (" . pdf_escape_text($val) . ") Tj\n";
            $s .= "ET\n";
            $idx++;
        }
    }

    // Table Header
    $tableTop = !empty($summary) ? 630 : 680;
    $s .= "q\n";
    $s .= "0.12 0.30 0.25 rg\n"; // Header row color
    $s .= "40 " . ($tableTop - 18) . " 515 20 re f\n";
    $s .= "Q\n";

    // Calculate column positions
    $colCount = count($columns);
    $totalWidth = 515;
    $colWidths = [];
    if ($colCount === 4) {
        $colWidths = [85, 230, 90, 110];
    } elseif ($colCount === 5) {
        $colWidths = [75, 175, 85, 85, 95];
    } else {
        $w = (int) floor($totalWidth / max(1, $colCount));
        for ($i = 0; $i < $colCount; $i++) {
            $colWidths[$i] = $w;
        }
    }

    // Draw Column Headers
    $s .= "BT\n1 1 1 rg /F2 9 Tf\n";
    $cx = 48;
    foreach ($columns as $ci => $colName) {
        $s .= "$cx " . ($tableTop - 13) . " Td (" . pdf_escape_text(strtoupper($colName)) . ") Tj\n";
        $cx += $colWidths[$ci] ?? 100;
        $s .= ($cx - ($colWidths[$ci] ?? 100)) . " 0 Td\n"; // reset X relative offset
    }
    $s .= "ET\n";

    // Draw Rows
    $y = $tableTop - 34;
    $maxRowsOnPage = 25;
    $rowCount = 0;

    foreach ($rows as $row) {
        if ($rowCount >= $maxRowsOnPage) {
            $s .= "BT 0.5 0.5 0.5 rg /F1 8 Tf 48 $y Td (... Additional records truncated in single-page executive view ...) Tj ET\n";
            break;
        }

        // Alternating row background
        if ($rowCount % 2 === 1) {
            $s .= "q 0.97 0.98 0.98 rg 40 " . ($y - 4) . " 515 16 re f Q\n";
        }
        $s .= "q 0.88 0.90 0.89 RG 0.5 w 40 " . ($y - 4) . " 515 16 re s Q\n";

        $s .= "BT\n0.15 0.15 0.15 rg /F1 8.5 Tf\n";
        $cx = 48;
        $vals = array_values($row);
        foreach ($vals as $ci => $cellVal) {
            $valStr = (string)$cellVal;
            // Shorten if too wide
            if (strlen($valStr) > 38 && ($colWidths[$ci] ?? 100) < 180) {
                $valStr = substr($valStr, 0, 35) . '...';
            }
            $s .= "$cx $y Td (" . pdf_escape_text($valStr) . ") Tj\n";
            $cx += $colWidths[$ci] ?? 100;
            $s .= ($cx - ($colWidths[$ci] ?? 100)) . " 0 Td\n";
        }
        $s .= "ET\n";

        $y -= 17;
        $rowCount++;
    }

    if (empty($rows)) {
        $s .= "BT 0.45 0.45 0.45 rg /F1 9.5 Tf 50 " . ($tableTop - 45) . " Td (No stored financial transactions recorded for this period and scope.) Tj ET\n";
    }

    // Footer with legal & verification note
    $s .= "q 0.8 0.85 0.82 RG 1 w 40 45 515 0.5 re s Q\n";
    $s .= "BT\n";
    $s .= "0.45 0.45 0.45 rg /F1 7.5 Tf\n";
    $s .= "42 32 Td (" . pdf_escape_text("Official Imali Smart Ledger Statement. Reconciled from authenticated user records. Protection of Personal Information Act compliant.") . ") Tj\n";
    $s .= "440 32 Td (" . pdf_escape_text("Page 1 of 1") . ") Tj\n";
    $s .= "ET\n";

    $streamLen = strlen($s);
    $contentsId = $addObject("<< /Length $streamLen >>\nstream\n$s\nendstream");

    $pageId = $addObject("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents $contentsId 0 R /Resources << /Font << /F1 $fontNormId 0 R /F2 $fontBoldId 0 R >> >> >>");
    $pagesId = $addObject("<< /Type /Pages /Kids [$pageId 0 R] /Count 1 >>");

    // XRef table
    $xrefPos = strlen($pdf);
    $count = count($objects) + 1;
    $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
    foreach ($objects as $pos) {
        $pdf .= sprintf("%010d 00000 n \n", $pos);
    }
    $pdf .= "trailer\n<< /Size $count /Root $catalogId 0 R >>\nstartxref\n$xrefPos\n%%EOF";

    return $pdf;
}

/**
 * Generates an Excel-compatible HTML/XML Spreadsheet (.xls) that opens natively in Microsoft Excel.
 */
function generate_excel_spreadsheet(string $title, array $meta, array $summary, array $columns, array $rows, string $scopeLabel = 'Personal'): string
{
    ob_start();
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="utf-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>' . htmlspecialchars($title) . '</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>';
    echo 'body { font-family: Calibri, Arial, sans-serif; }';
    echo '.title-cell { font-size: 16pt; font-weight: bold; color: #12382e; }';
    echo '.meta-label { font-weight: bold; color: #555555; }';
    echo '.summary-card { background: #eef5f1; border: 1px solid #c2dbcd; font-size: 11pt; padding: 6px; }';
    echo '.table-header { background: #12382e; color: #ffffff; font-weight: bold; font-size: 11pt; border: 1px solid #000; text-align: left; }';
    echo '.table-cell { font-size: 10pt; border: 1px solid #d4d4d4; }';
    echo '.table-cell-money { font-size: 10pt; border: 1px solid #d4d4d4; text-align: right; }';
    echo '</style></head><body>';

    echo '<table>';
    echo '<tr><td colspan="' . count($columns) . '" class="title-cell">IMALI SMART - ' . htmlspecialchars(strtoupper($title)) . '</td></tr>';
    echo '<tr><td class="meta-label">Account:</td><td colspan="' . (count($columns) - 1) . '">' . htmlspecialchars(($meta['user_name'] ?? '') . ' (' . ($meta['email'] ?? '') . ')') . '</td></tr>';
    echo '<tr><td class="meta-label">Period:</td><td colspan="' . (count($columns) - 1) . '">' . htmlspecialchars(($meta['from'] ?? '') . ' to ' . ($meta['to'] ?? '')) . '</td></tr>';
    echo '<tr><td class="meta-label">Scope:</td><td colspan="' . (count($columns) - 1) . '">' . htmlspecialchars($scopeLabel) . '</td></tr>';
    echo '<tr><td class="meta-label">Generated:</td><td colspan="' . (count($columns) - 1) . '">' . htmlspecialchars(date('Y-m-d H:i:s')) . '</td></tr>';
    echo '<tr><td></td></tr>';

    if (!empty($summary)) {
        echo '<tr>';
        foreach ($summary as $sLabel => $sVal) {
            echo '<td class="summary-card"><b>' . htmlspecialchars(strtoupper($sLabel)) . ':</b><br>' . htmlspecialchars($sVal) . '</td>';
        }
        echo '</tr>';
        echo '<tr><td></td></tr>';
    }

    // Table Header
    echo '<tr>';
    foreach ($columns as $col) {
        echo '<th class="table-header">' . htmlspecialchars(strtoupper($col)) . '</th>';
    }
    echo '</tr>';

    // Rows
    foreach ($rows as $row) {
        echo '<tr>';
        foreach (array_values($row) as $ci => $val) {
            $isMoney = (strpos((string)$val, 'R ') !== false || is_numeric($val));
            echo '<td class="' . ($isMoney ? 'table-cell-money' : 'table-cell') . '">' . htmlspecialchars((string)$val) . '</td>';
        }
        echo '</tr>';
    }

    echo '</table>';
    echo '</body></html>';
    return ob_get_clean();
}

/**
 * Generates an RFC-4180 CSV spreadsheet.
 */
function generate_csv_spreadsheet(array $columns, array $rows): string
{
    $fp = fopen('php://temp', 'r+');
    // Write UTF-8 BOM for Microsoft Excel auto-detection
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, $columns);
    foreach ($rows as $row) {
        fputcsv($fp, array_values($row));
    }
    rewind($fp);
    $csv = stream_get_contents($fp);
    fclose($fp);
    return $csv;
}


/**
 * Generates a readable, paginated PDF statement. Unlike the legacy single-page
 * renderer, this keeps all rows and repeats the table header on every page.
 */
function generate_readable_pdf_document(string $title, array $meta, array $summary, array $columns, array $rows, string $scopeLabel = 'Personal'): string
{
    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 3 0 R >>';
    $fontNormalId = 2;
    $fontBoldId = 3;
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

    $columnCount = max(1, count($columns));
    if ($columnCount === 4) {
        $widths = [78, 245, 92, 100];
    } elseif ($columnCount === 5) {
        $widths = [72, 190, 82, 82, 89];
    } else {
        $widths = array_fill(0, $columnCount, (int) floor(515 / $columnCount));
    }
    $sumWidth = array_sum($widths);
    if ($sumWidth < 515) $widths[$columnCount - 1] += 515 - $sumWidth;

    $text = static function (float $x, float $y, float $size, int $font, string $color, string $value): string {
        return "BT\n{$color} rg\n/F{$font} {$size} Tf\n{$x} {$y} Td (" . pdf_escape_text($value) . ") Tj\nET\n";
    };

    $pages = [];
    $perPage = 34;
    $rowPages = $rows ? array_chunk($rows, $perPage) : [[]];
    foreach ($rowPages as $pageIndex => $pageRows) {
        $stream = "q\n0.07 0.22 0.18 rg\n40 760 515 50 re f\nQ\n";
        $stream .= $text(55, 788, 16, 3, '1 1 1', 'IMALI SMART - ' . strtoupper($title));
        $stream .= $text(55, 772, 9, 2, '1 1 1', 'READABLE FINANCIAL STATEMENT - SOUTH AFRICA');
        $stream .= $text(42, 735, 10, 3, '0.15 0.15 0.15', 'Account: ' . ($meta['user_name'] ?? 'Authorized User') . ' <' . ($meta['email'] ?? '') . '>');
        $stream .= $text(340, 735, 10, 3, '0.15 0.15 0.15', 'Period: ' . ($meta['from'] ?? '') . ' to ' . ($meta['to'] ?? ''));
        $stream .= $text(42, 718, 9, 2, '0.30 0.30 0.30', 'Scope: ' . $scopeLabel . ' | Currency: ZAR (R) | Generated: ' . date('Y-m-d H:i'));

        if ($pageIndex === 0 && $summary) {
            $stream .= "q\n0.94 0.96 0.95 rg\n40 650 515 52 re f\n0.80 0.85 0.82 RG 1 w\n40 650 515 52 re s\nQ\n";
            $cardWidth = 515 / max(1, count($summary));
            $summaryIndex = 0;
            foreach ($summary as $label => $value) {
                $x = 48 + ($summaryIndex * $cardWidth);
                $stream .= $text($x, 682, 8.5, 2, '0.35 0.40 0.36', strtoupper((string) $label));
                $stream .= $text($x, 662, 12, 3, '0.08 0.15 0.12', (string) $value);
                $summaryIndex++;
            }
            $tableTop = 625;
        } else {
            $tableTop = 680;
        }

        $stream .= "q\n0.12 0.30 0.25 rg\n40 " . ($tableTop - 20) . " 515 22 re f\nQ\n";
        $x = 48;
        foreach ($columns as $index => $column) {
            $stream .= $text($x, $tableTop - 14, 9, 3, '1 1 1', strtoupper((string) $column));
            $x += $widths[$index] ?? 100;
        }

        $y = $tableTop - 40;
        foreach ($pageRows as $rowIndex => $row) {
            if ($rowIndex % 2 === 1) {
                $stream .= "q\n0.97 0.98 0.98 rg\n40 " . ($y - 5) . " 515 19 re f\nQ\n";
            }
            $stream .= "q\n0.86 0.89 0.87 RG 0.5 w\n40 " . ($y - 5) . " 515 19 re s\nQ\n";
            $x = 48;
            foreach (array_values($row) as $index => $value) {
                $cell = trim((string) $value);
                $maxChars = max(8, (int) floor(($widths[$index] ?? 100) / 5.4));
                if (strlen($cell) > $maxChars) $cell = substr($cell, 0, max(1, $maxChars - 3)) . '...';
                $stream .= $text($x, $y, 9, 2, '0.15 0.15 0.15', $cell);
                $x += $widths[$index] ?? 100;
            }
            $y -= 19;
        }
        if (!$pageRows) {
            $stream .= $text(50, $tableTop - 48, 10, 2, '0.35 0.35 0.35', 'No stored financial transactions recorded for this period and scope.');
        }
        $stream .= "q\n0.80 0.85 0.82 RG 1 w\n40 45 515 0.5 re s\nQ\n";
        $stream .= $text(42, 30, 8, 2, '0.40 0.40 0.40', 'Official Imali Smart statement. Reconciled from authenticated user records.');
        $stream .= $text(475, 30, 8, 2, '0.40 0.40 0.40', 'Page ' . ($pageIndex + 1) . ' of ' . count($rowPages));
        $pages[] = $stream;
    }

    $pageIds = [];
    foreach ($pages as $stream) {
        $contentId = count($objects) + 1;
        $objects[] = '<< /Length ' . strlen($stream) . " >>\nstream\n{$stream}endstream";
        $pageId = count($objects) + 1;
        $objects[] = '<< /Type /Page /Parent 3 0 R /MediaBox [0 0 595 842] /Contents ' . $contentId . ' 0 R /Resources << /Font << /F1 ' . $fontNormalId . ' 0 R /F2 ' . $fontBoldId . ' 0 R >> >> >>';
        $pageIds[] = $pageId;
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' 0 R ', $pageIds) . ' 0 R] /Count ' . count($pageIds) . ' >>';

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $id = $index + 1;
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= 'xref\n0 ' . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($id = 1; $id <= count($objects); $id++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
    $pdf .= 'trailer\n<< /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>\nstartxref\n' . $xref . "\n%%EOF";
    return $pdf;
}
