$ErrorActionPreference='Stop'
$root=Split-Path $PSScriptRoot -Parent
$sessionName='finance-test-'+[guid]::NewGuid().ToString('N')
$env:FINANCE_TEST_SESSION=$sessionName
$setup=@'
<?php
require 'includes/db.php';
$u=db()->query("SELECT id,name,email,phone,role FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
session_save_path('tests/.test-sessions');session_id(getenv('FINANCE_TEST_SESSION'));session_start();$_SESSION=['user'=>$u];session_write_close();echo 'OK';
'@
try {
    $result=$setup | & C:/xampp/php/php.exe
    if($result -ne 'OK'){throw 'Unable to create isolated test session'}
    $client=New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $client.Cookies.Add((New-Object Net.Cookie('PHPSESSID',$sessionName,'/','127.0.0.1')))
    $base='http://127.0.0.1:8123/?page=admin&tab=reports'
    $html=(Invoke-WebRequest -Uri $base -WebSession $client -UseBasicParsing).Content
    foreach($label in @('Total Pendapatan','Total HPP','Laba Kotor','Total Biaya Operasional','Laba Bersih','Margin Laba','finance-trend','finance-costs','finance_expense_save')){if(!$html.Contains($label)){throw "Missing finance UI: $label"}}
    if($html -match 'Fatal error:|Warning:|Parse error:'){throw 'PHP error on report'}
    $pdf=Join-Path $root '.mobile-audit/finance-test.pdf'
    Invoke-WebRequest -Uri ($base+'&export=pdf') -WebSession $client -UseBasicParsing -OutFile $pdf
    $bytes=[IO.File]::ReadAllBytes($pdf)
    if([Text.Encoding]::ASCII.GetString($bytes,0,8) -ne '%PDF-1.4'){throw 'Invalid PDF export'}
    $csv=(Invoke-WebRequest -Uri ($base+'&export=csv') -WebSession $client -UseBasicParsing).Content
    if(!$csv.Contains('Total Pendapatan') -or !$csv.Contains('Tanggal')){throw 'Invalid CSV export'}
    $guest=Invoke-WebRequest -Uri $base -UseBasicParsing
    if($guest.Content.Contains('id="finance-report"')){throw 'Guest can access report'}
    $printable=$html.Replace('<head>','<head><base href="http://127.0.0.1:8123/">')
    [IO.File]::WriteAllText((Join-Path $root '.mobile-audit/finance-preview.html'),$printable)
    Write-Output 'PASS: authenticated report, six cards, charts, form, PDF/CSV exports, guest access denied.'
} finally {
    $sessionPath=Join-Path $PSScriptRoot ('.test-sessions/sess_'+$sessionName)
    if(Test-Path -LiteralPath $sessionPath){Remove-Item -LiteralPath $sessionPath}
    Remove-Item Env:FINANCE_TEST_SESSION -ErrorAction SilentlyContinue
}
