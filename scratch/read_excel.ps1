$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false
$workbook = $excel.Workbooks.Open("C:\xampp\htdocs\grafica\MCI 2026.xlsx")
foreach ($sheet in $workbook.Sheets) {
    Write-Host $sheet.Name
}
$workbook.Close($false)
$excel.Quit()
[System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
