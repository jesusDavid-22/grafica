$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false
$workbook = $excel.Workbooks.Open("C:\xampp\htdocs\grafica\MCI 2026.xlsx")
$sheet = $workbook.Sheets.Item("Base")
$sheet.SaveAs("C:\xampp\htdocs\grafica\scratch\base.csv", 6) # 6 is CSV
$workbook.Close($false)
$excel.Quit()
[System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
