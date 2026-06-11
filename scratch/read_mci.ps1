$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false
$workbook = $excel.Workbooks.Open("C:\xampp\htdocs\grafica\MCI 2026.xlsx")

$sheetsToRead = @("Base.", "Base. (2)", "Base. (3)")

foreach ($sheetName in $sheetsToRead) {
    Write-Host "=== Hoja: $sheetName ==="
    $sheet = $workbook.Sheets.Item($sheetName)
    $range = $sheet.UsedRange
    $maxRow = [math]::Min($range.Rows.Count, 20)
    $maxCol = [math]::Min($range.Columns.Count, 10)
    
    for($row=1; $row -le $maxRow; $row++) {
        $line = ''
        for($col=1; $col -le $maxCol; $col++) {
            $cellText = $range.Cells.Item($row, $col).Text
            if ($cellText) { $line += "$cellText | " }
        }
        if ($line) { Write-Host $line }
    }
}

$workbook.Close($false)
$excel.Quit()
[System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
