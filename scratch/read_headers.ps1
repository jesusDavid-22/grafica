$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false
$workbook = $excel.Workbooks.Open("C:\xampp\htdocs\grafica\MCI 2026.xlsx")
foreach ($sheet in $workbook.Sheets) {
    Write-Host "--- $($sheet.Name) ---"
    $range = $sheet.UsedRange
    $line = ''
    for($col=1; $col -le $range.Columns.Count; $col++) {
        $cellText = $range.Cells.Item(1, $col).Text
        if ($cellText) { $line += "$cellText | " }
    }
    Write-Host $line
}
$workbook.Close($false)
$excel.Quit()
[System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
