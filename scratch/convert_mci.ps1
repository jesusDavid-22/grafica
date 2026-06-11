$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false
$workbook = $excel.Workbooks.Open("C:\xampp\htdocs\grafica\MCI 2026.xlsx")

$sheet2 = $workbook.Sheets.Item("Base. (2)")
$sheet2.SaveAs("C:\xampp\htdocs\grafica\scratch\base2.csv", 6)

$sheet3 = $workbook.Sheets.Item("Base. (3)")
$sheet3.SaveAs("C:\xampp\htdocs\grafica\scratch\base3.csv", 6)

$workbook.Close($false)
$excel.Quit()
[System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
