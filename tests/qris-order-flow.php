<?php
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/payment-settings.php';
$instructionSettings=payment_defaults();
$instructionSettings['qris_image']='assets/images/payments/example-qris.png';
$instructionMethod='QRIS';
foreach (['manual','midtrans'] as $provider) {
    $instructionSettings['gateway_provider']=$provider;
    ob_start(); include __DIR__.'/../includes/payment-instructions.php'; $html=ob_get_clean();
    if (str_contains($html,'<img') || str_contains($html,'download') || !str_contains($html,'Buat Pesanan')) throw new RuntimeException('QRIS visible before order creation');
}
$instructionOrder=['order_code'=>'TEST-QRIS','payment_provider'=>'manual','total'=>11000,'created_at'=>'2026-10-15 10:00:00'];
ob_start(); include __DIR__.'/../includes/payment-instructions.php'; $html=ob_get_clean();
if (!str_contains($html,'<img') || !str_contains($html,'TEST-QRIS') || !str_contains($html,'Rp 11.000')) throw new RuntimeException('Order QRIS instructions missing');
echo "PASS: QRIS hidden before order creation; QRIS and amount visible for created order.\n";
