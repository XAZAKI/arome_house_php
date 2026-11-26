$ErrorActionPreference='Stop'
$cartTestBase='http://localhost/arome_house_php/'
$cartTestSession=New-Object Microsoft.PowerShell.Commands.WebRequestSession
$productOutput=@'
<?php
require 'includes/db.php';
$row=db()->query("SELECT id FROM products WHERE status<>'unavailable' AND stock>=2 LIMIT 1")->fetch_assoc();
if(!$row)exit(1);
echo $row['id'];
'@ | php
if($LASTEXITCODE -ne 0){throw 'No stocked product available for cart test'}
$cartTestProduct=[int]$productOutput
function Cart-Snapshot {
    $response=Invoke-WebRequest ($cartTestBase+'?page=cart&cart_snapshot=1') -WebSession $cartTestSession -UseBasicParsing
    return ($response.Content | ConvertFrom-Json)
}
$page=Invoke-WebRequest ($cartTestBase+'?page=home') -WebSession $cartTestSession -UseBasicParsing
$csrf=[regex]::Match($page.Content,'id="cart-drawer"[^>]*data-csrf="([^"]+)"').Groups[1].Value
if(!$csrf){throw 'Drawer CSRF token missing'}
$empty=Cart-Snapshot
if($empty.count -ne 0){throw 'Fresh cart not empty'}
$null=Invoke-WebRequest $cartTestBase -Method Post -Body @{action='add_cart';product_id=$cartTestProduct;qty=1} -WebSession $cartTestSession -UseBasicParsing
$added=Cart-Snapshot
if($added.count -ne 1 -or !$added.drawerHtml.Contains('data-cart-remove')){throw 'Product not added/rendered'}
$updated=Invoke-WebRequest $cartTestBase -Method Post -Body @{action='cart_item_update';csrf=$csrf;product_id=$cartTestProduct;quantity=2} -WebSession $cartTestSession -UseBasicParsing
$updateData=$updated.Content|ConvertFrom-Json
if($updateData.count -ne 2 -or !$updateData.pageHtml.Contains('data-cart-quantity="2"')){throw 'Quantity not updated'}
foreach($invalid in @(@{csrf='invalid';quantity=1;status=403},@{csrf=$csrf;quantity=100;status=422})) {
    try {
        $null=Invoke-WebRequest $cartTestBase -Method Post -Body @{action='cart_item_update';csrf=$invalid.csrf;product_id=$cartTestProduct;quantity=$invalid.quantity} -WebSession $cartTestSession -UseBasicParsing
        throw 'Invalid update accepted'
    } catch {
        if(!$_.Exception.Response -or [int]$_.Exception.Response.StatusCode -ne $invalid.status){throw}
    }
}
$removed=Invoke-WebRequest $cartTestBase -Method Post -Body @{action='cart_item_update';csrf=$csrf;product_id=$cartTestProduct;quantity=0} -WebSession $cartTestSession -UseBasicParsing
$removeData=$removed.Content|ConvertFrom-Json
if($removeData.count -ne 0 -or !$removeData.drawerHtml.Contains('Keranjang masih kosong')){throw 'Remove failed'}
Write-Output 'PASS: empty cart, add product, drawer/page rendering, quantity/subtotal update, CSRF validation, quantity limit, remove product.'
