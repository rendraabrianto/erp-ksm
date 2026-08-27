<?php

namespace App\DTO;

class PurchaseInvoiceDTO
{
    public function __construct(

        public int $goodsReceiptId,

        public string $supplierName,

        public string $supplierInvoiceNo,

        public float $subtotal,

        public float $taxAmount,

        public float $grandTotal,

        public int $createdBy,

        public array $lines = [],

    ) {}
}