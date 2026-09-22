<?php

namespace Tests\Feature;

use App\DTO\PurchaseOrderDTO;
use App\DTO\PurchaseOrderLineDTO;
use App\DTO\PurchaseRequestDTO;
use App\DTO\PurchaseRequestLineDTO;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

use App\DTO\GoodsReceiptDTO;
use App\DTO\GoodsReceiptLineDTO;
use App\Models\Warehouse;
use App\Services\GoodsReceiptService;

class PurchaseCycleCompanyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        foreach (['PR', 'PO', 'GR'] as $documentType) {
            DB::table('document_sequences')->insert([
                'company_id' =>
                    $this->data['company_id'],

                'document_type' =>
                    $documentType,

                'prefix' =>
                    $documentType,

                'description' =>
                    "{$documentType} Company Ownership Test",

                'current_number' =>
                    0,

                'padding' =>
                    5,

                'is_active' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
        }
    }

    public function test_purchase_request_inherits_company_from_warehouse(): void
    {
        $service = app(
            PurchaseRequestService::class
        );

        $pr = $service->create(
            new PurchaseRequestDTO(
                warehouseId:
                    $this->data['warehouse_id'],

                remarks:
                    'PR company ownership test',

                createdBy:
                    $this->data['user_id'],

                lines: [
                    new PurchaseRequestLineDTO(
                        itemId:
                            $this->data['item_id'],

                        qty:
                            10,

                        remarks:
                            'Test line',
                    ),
                ],
            )
        );

        $this->assertSame(
            $this->data['company_id'],
            (int) $pr->company_id
        );

        $this->assertDatabaseHas(
            'purchase_requests',
            [
                'id' => $pr->id,
                'company_id' =>
                    $this->data['company_id'],
                'warehouse_id' =>
                    $this->data['warehouse_id'],
            ]
        );
    }

    public function test_purchase_request_rejects_item_from_another_company():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange - Company A Context
        |--------------------------------------------------------------------------
        */

        $itemA =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        /*
        |--------------------------------------------------------------------------
        | Arrange - Company B
        |--------------------------------------------------------------------------
        */

        $companyB =
            Company::query()
                ->create([
                    'code' =>
                        'COMP-PR-B',

                    'name' =>
                        'Purchase Request Company B',

                    'phone' =>
                        null,

                    'email' =>
                        null,

                    'address' =>
                        null,

                    'is_active' =>
                        true,
                ]);

        $categoryB =
            ItemCategory::query()
                ->create([
                    'company_id' =>
                        $companyB->id,

                    'code' =>
                        'CAT-PR-B',

                    'name' =>
                        'Purchase Request Category B',

                    'description' =>
                        'Cross-company PR item attack',

                    'inventory_account_id' =>
                        null,

                    'cogs_account_id' =>
                        null,

                    'sales_account_id' =>
                        null,

                    'adjustment_gain_account_id' =>
                        null,

                    'adjustment_loss_account_id' =>
                        null,

                    'is_active' =>
                        true,
                ]);

        $itemB =
            Item::query()
                ->create([
                    'company_id' =>
                        $companyB->id,

                    'item_category_id' =>
                        $categoryB->id,

                    'uom_id' =>
                        $itemA->uom_id,

                    'code' =>
                        'ITEM-PR-B',

                    'name' =>
                        'Purchase Request Item Company B',

                    'description' =>
                        'Must not enter Company A purchase request',

                    'minimum_stock' =>
                        0,

                    'maximum_stock' =>
                        0,

                    'average_cost' =>
                        0,

                    'last_purchase_price' =>
                        0,

                    'is_active' =>
                        true,
                ]);

        /*
        |--------------------------------------------------------------------------
        | Snapshot Before Attack
        |--------------------------------------------------------------------------
        */

        $purchaseRequestCountBefore =
            DB::table(
                'purchase_requests'
            )->count();

        $purchaseRequestDetailCountBefore =
            DB::table(
                'purchase_request_details'
            )->count();

        $auditLogCountBefore =
            DB::table(
                'audit_logs'
            )->count();

        $sequenceBefore =
            DB::table(
                'document_sequences'
            )
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->where(
                    'document_type',
                    'PR'
                )
                ->value(
                    'current_number'
                );

        /*
        |--------------------------------------------------------------------------
        | Execute Attack
        |--------------------------------------------------------------------------
        */

        try {

            app(PurchaseRequestService::class)
                ->create(
                    new PurchaseRequestDTO(
                        warehouseId:
                            $this->data['warehouse_id'],

                        remarks:
                            'Cross-company item attack',

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new PurchaseRequestLineDTO(
                                itemId:
                                    $itemB->id,

                                qty:
                                    10,

                                remarks:
                                    'Foreign company item',
                            ),
                        ],
                    )
                );

            $this->fail(
                'Purchase Request must reject an item from another company.'
            );

        } catch (\RuntimeException $exception) {

            $this->assertSame(
                'Item does not belong to transaction company.',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Assert - Attack Setup
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            (int) $this->data['company_id'],
            (int) DB::table('warehouses')
                ->where(
                    'id',
                    $this->data['warehouse_id']
                )
                ->value(
                    'company_id'
                )
        );

        $this->assertNotSame(
            (int) $this->data['company_id'],
            (int) $itemB->company_id
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Transaction Fully Rolled Back
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $purchaseRequestCountBefore,
            DB::table(
                'purchase_requests'
            )->count()
        );

        $this->assertSame(
            $purchaseRequestDetailCountBefore,
            DB::table(
                'purchase_request_details'
            )->count()
        );

        $this->assertSame(
            $auditLogCountBefore,
            DB::table(
                'audit_logs'
            )->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Document Sequence Rolled Back
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $sequenceBefore,
            DB::table(
                'document_sequences'
            )
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->where(
                    'document_type',
                    'PR'
                )
                ->value(
                    'current_number'
                )
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Foreign Item Never Reaches PR Detail
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseMissing(
            'purchase_request_details',
            [
                'item_id' =>
                    $itemB->id,
            ]
        );
    }

    public function test_purchase_order_from_purchase_request_inherits_parent_company(): void
    {
        $prService = app(
            PurchaseRequestService::class
        );

        $poService = app(
            PurchaseOrderService::class
        );

        $pr = $prService->create(
            new PurchaseRequestDTO(
                warehouseId:
                    $this->data['warehouse_id'],

                remarks:
                    'Parent PR',

                createdBy:
                    $this->data['user_id'],

                lines: [
                    new PurchaseRequestLineDTO(
                        itemId:
                            $this->data['item_id'],

                        qty:
                            10,
                    ),
                ],
            )
        );

        $po = $poService->create(
            new PurchaseOrderDTO(
                purchaseRequestId:
                    $pr->id,

                supplierName:
                    'Supplier Company Test',

                remarks:
                    'PO from PR',

                createdBy:
                    $this->data['user_id'],

                lines: [
                    new PurchaseOrderLineDTO(
                        itemId:
                            $this->data['item_id'],

                        qty:
                            10,

                        unitPrice:
                            7000,
                    ),
                ],
            )
        );

        $this->assertSame(
            (int) $pr->company_id,
            (int) $po->company_id
        );

        $this->assertDatabaseHas(
            'purchase_orders',
            [
                'id' => $po->id,
                'purchase_request_id' =>
                    $pr->id,
                'company_id' =>
                    $this->data['company_id'],
            ]
        );
    }

    public function test_manual_purchase_order_inherits_company_from_creator(): void
    {
        $poService = app(
            PurchaseOrderService::class
        );

        $po = $poService->create(
            new PurchaseOrderDTO(
                purchaseRequestId:
                    null,

                supplierName:
                    'Manual Supplier',

                remarks:
                    'Manual PO company test',

                createdBy:
                    $this->data['user_id'],

                lines: [
                    new PurchaseOrderLineDTO(
                        itemId:
                            $this->data['item_id'],

                        qty:
                            5,

                        unitPrice:
                            7500,
                    ),
                ],
            )
        );

        $this->assertSame(
            $this->data['company_id'],
            (int) $po->company_id
        );

        $this->assertDatabaseHas(
            'purchase_orders',
            [
                'id' => $po->id,
                'purchase_request_id' => null,
                'company_id' =>
                    $this->data['company_id'],
            ]
        );
    }

    public function test_purchase_order_from_pr_rejects_creator_from_different_company(): void
    {
        $prService = app(
            PurchaseRequestService::class
        );

        $poService = app(
            PurchaseOrderService::class
        );

        $pr = $prService->create(
            new PurchaseRequestDTO(
                warehouseId:
                    $this->data['warehouse_id'],

                remarks:
                    'Company A PR',

                createdBy:
                    $this->data['user_id'],

                lines: [
                    new PurchaseRequestLineDTO(
                        itemId:
                            $this->data['item_id'],

                        qty:
                            10,
                    ),
                ],
            )
        );

        $companyBId =
            DB::table('companies')
                ->insertGetId([
                    'code' =>
                        'COMP-B',

                    'name' =>
                        'Company B',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $branchBId =
            DB::table('branches')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'code' =>
                        'BR-B',

                    'name' =>
                        'Branch B',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $userBId =
            DB::table('users')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'branch_id' =>
                        $branchBId,

                    'name' =>
                        'User Company B',

                    'email' =>
                        'company-b@example.test',

                    'password' =>
                        Hash::make('password'),

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $beforePoCount =
            DB::table('purchase_orders')
                ->count();

        $beforePoDetailCount =
            DB::table('purchase_order_details')
                ->count();

        try {
            $poService->create(
                new PurchaseOrderDTO(
                    purchaseRequestId:
                        $pr->id,

                    supplierName:
                        'Supplier Test',

                    remarks:
                        'Must reject foreign creator',

                    createdBy:
                        $userBId,

                    lines: [
                        new PurchaseOrderLineDTO(
                            itemId:
                                $this->data['item_id'],

                            qty:
                                10,

                            unitPrice:
                                7000,
                        ),
                    ],
                )
            );

            $this->fail(
                'Expected cross-company actor rejection.'
            );
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'Actor does not belong to transaction company.',
                $e->getMessage()
            );
        }

        $this->assertSame(
            $beforePoCount,
            DB::table('purchase_orders')
                ->count()
        );

        $this->assertSame(
            $beforePoDetailCount,
            DB::table('purchase_order_details')
                ->count()
        );
    }

    public function test_goods_receipt_inherits_company_from_purchase_order(): void
    {
        $prService = app(
            PurchaseRequestService::class
        );

        $poService = app(
            PurchaseOrderService::class
        );

        $grService = app(
            GoodsReceiptService::class
        );

        $pr = $prService->create(
            new PurchaseRequestDTO(
                warehouseId:
                    $this->data['warehouse_id'],

                remarks:
                    'PR for GR ownership',

                createdBy:
                    $this->data['user_id'],

                lines: [
                    new PurchaseRequestLineDTO(
                        itemId:
                            $this->data['item_id'],

                        qty:
                            10,
                    ),
                ],
            )
        );

        $po = $poService->create(
            new PurchaseOrderDTO(
                purchaseRequestId:
                    $pr->id,

                supplierName:
                    'Supplier GR Ownership',

                remarks:
                    'PO for GR ownership',

                createdBy:
                    $this->data['user_id'],

                lines: [
                    new PurchaseOrderLineDTO(
                        itemId:
                            $this->data['item_id'],

                        qty:
                            10,

                        unitPrice:
                            7000,
                    ),
                ],
            )
        );

        $poDetail =
            $po->details->first();

        $gr = $grService->create(
            new GoodsReceiptDTO(
                purchaseOrderId:
                    $po->id,

                supplierName:
                    'Supplier GR Ownership',

                remarks:
                    'GR company ownership',

                warehouseId:
                    $this->data['warehouse_id'],

                createdBy:
                    $this->data['user_id'],

                lines: [
                    new GoodsReceiptLineDTO(
                        itemId:
                            $this->data['item_id'],

                        qty:
                            10,

                        unitPrice:
                            7000,

                        purchaseOrderDetailId:
                            $poDetail->id,
                    ),
                ],

                receiptDate:
                    '2026-08-08',
            )
        );

        $this->assertSame(
            (int) $po->company_id,
            (int) $gr->company_id
        );

        $this->assertDatabaseHas(
            'goods_receipts',
            [
                'id' =>
                    $gr->id,

                'purchase_order_id' =>
                    $po->id,

                'company_id' =>
                    $this->data['company_id'],
            ]
        );
    }

    public function test_goods_receipt_rejects_warehouse_from_different_company(): void
    {
        $prService = app(
            PurchaseRequestService::class
        );

        $poService = app(
            PurchaseOrderService::class
        );

        $grService = app(
            GoodsReceiptService::class
        );

        $pr = $prService->create(
            new PurchaseRequestDTO(
                warehouseId:
                    $this->data['warehouse_id'],

                remarks:
                    'Company A PR',

                createdBy:
                    $this->data['user_id'],

                lines: [
                    new PurchaseRequestLineDTO(
                        itemId:
                            $this->data['item_id'],

                        qty:
                            10,
                    ),
                ],
            )
        );

        $po = $poService->create(
            new PurchaseOrderDTO(
                purchaseRequestId:
                    $pr->id,

                supplierName:
                    'Supplier Company A',

                remarks:
                    'Company A PO',

                createdBy:
                    $this->data['user_id'],

                lines: [
                    new PurchaseOrderLineDTO(
                        itemId:
                            $this->data['item_id'],

                        qty:
                            10,

                        unitPrice:
                            7000,
                    ),
                ],
            )
        );

        $companyBId =
            DB::table('companies')
                ->insertGetId([
                    'code' =>
                        'COMP-GR-B',

                    'name' =>
                        'Company GR B',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $branchBId =
            DB::table('branches')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'code' =>
                        'BR-GR-B',

                    'name' =>
                        'Branch GR B',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $warehouseBId =
            DB::table('warehouses')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'branch_id' =>
                        $branchBId,

                    'code' =>
                        'WH-GR-B',

                    'name' =>
                        'Warehouse GR B',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $poDetail =
            $po->details->first();

        $beforeReceivedQty =
            (float) $poDetail->received_qty;

        $beforeGrCount =
            DB::table('goods_receipts')
                ->count();

        $beforeLedgerCount =
            DB::table('stock_ledgers')
                ->count();

        $beforeJournalCount =
            DB::table('journals')
                ->count();

        try {
            $grService->create(
                new GoodsReceiptDTO(
                    purchaseOrderId:
                        $po->id,

                    supplierName:
                        'Supplier Company A',

                    remarks:
                        'Must reject foreign warehouse',

                    warehouseId:
                        $warehouseBId,

                    createdBy:
                        $this->data['user_id'],

                    lines: [
                        new GoodsReceiptLineDTO(
                            itemId:
                                $this->data['item_id'],

                            qty:
                                10,

                            unitPrice:
                                7000,

                            purchaseOrderDetailId:
                                $poDetail->id,
                        ),
                    ],

                    receiptDate:
                        '2026-08-08',
                )
            );

            $this->fail(
                'Expected cross-company warehouse rejection.'
            );
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'Goods receipt warehouse does not belong to purchase order company.',
                $e->getMessage()
            );
        }

        $this->assertSame(
            $beforeGrCount,
            DB::table('goods_receipts')
                ->count()
        );

        $this->assertSame(
            $beforeLedgerCount,
            DB::table('stock_ledgers')
                ->count()
        );

        $this->assertSame(
            $beforeJournalCount,
            DB::table('journals')
                ->count()
        );

        $this->assertSame(
            $beforeReceivedQty,
            (float)
            DB::table('purchase_order_details')
                ->where(
                    'id',
                    $poDetail->id
                )
                ->value('received_qty')
        );
    }

    public function test_purchase_request_rejects_creator_from_different_company(): void
    {
        $service = app(
            PurchaseRequestService::class
        );

        $companyBId =
            DB::table('companies')
                ->insertGetId([
                    'code' =>
                        'COMP-PR-B',

                    'name' =>
                        'Company PR B',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $branchBId =
            DB::table('branches')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'code' =>
                        'BR-PR-B',

                    'name' =>
                        'Branch PR B',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $userBId =
            DB::table('users')
                ->insertGetId([
                    'company_id' =>
                        $companyBId,

                    'branch_id' =>
                        $branchBId,

                    'name' =>
                        'User PR Company B',

                    'email' =>
                        'pr-company-b@example.test',

                    'password' =>
                        Hash::make('password'),

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $beforePrCount =
            DB::table('purchase_requests')
                ->count();

        $beforePrDetailCount =
            DB::table('purchase_request_details')
                ->count();

        try {
            $service->create(
                new PurchaseRequestDTO(
                    warehouseId:
                        $this->data['warehouse_id'],

                    remarks:
                        'Must reject foreign creator',

                    createdBy:
                        $userBId,

                    lines: [
                        new PurchaseRequestLineDTO(
                            itemId:
                                $this->data['item_id'],

                            qty:
                                10,
                        ),
                    ],
                )
            );

            $this->fail(
                'Expected cross-company actor rejection.'
            );
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'Actor does not belong to transaction company.',
                $e->getMessage()
            );
        }

        $this->assertSame(
            $beforePrCount,
            DB::table('purchase_requests')
                ->count()
        );

        $this->assertSame(
            $beforePrDetailCount,
            DB::table('purchase_request_details')
                ->count()
        );
    }

    private function createForeignCompanyItem(
        string $suffix
    ): Item {
        $itemA =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        $companyB =
            Company::query()
                ->create([
                    'code' =>
                        'COMP-PO-B-' . $suffix,

                    'name' =>
                        'Purchase Order Company B ' . $suffix,

                    'phone' =>
                        null,

                    'email' =>
                        null,

                    'address' =>
                        null,

                    'is_active' =>
                        true,
                ]);

        $categoryB =
            ItemCategory::query()
                ->create([
                    'company_id' =>
                        $companyB->id,

                    'code' =>
                        'CAT-PO-B-' . $suffix,

                    'name' =>
                        'Purchase Order Category B ' . $suffix,

                    'description' =>
                        'Cross-company PO item attack',

                    'inventory_account_id' =>
                        null,

                    'cogs_account_id' =>
                        null,

                    'sales_account_id' =>
                        null,

                    'adjustment_gain_account_id' =>
                        null,

                    'adjustment_loss_account_id' =>
                        null,

                    'is_active' =>
                        true,
                ]);

        return Item::query()
            ->create([
                'company_id' =>
                    $companyB->id,

                'item_category_id' =>
                    $categoryB->id,

                'uom_id' =>
                    $itemA->uom_id,

                'code' =>
                    'ITEM-PO-B-' . $suffix,

                'name' =>
                    'Purchase Order Item Company B ' . $suffix,

                'description' =>
                    'Must not enter Company A purchase order',

                'minimum_stock' =>
                    0,

                'maximum_stock' =>
                    0,

                'average_cost' =>
                    0,

                'last_purchase_price' =>
                    0,

                'is_active' =>
                    true,
            ]);
    }

    public function test_purchase_order_from_pr_rejects_item_from_another_company():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange - Company A Purchase Request
        |--------------------------------------------------------------------------
        */

        $pr =
            app(PurchaseRequestService::class)
                ->create(
                    new PurchaseRequestDTO(
                        warehouseId:
                            $this->data['warehouse_id'],

                        remarks:
                            'Company A PR for foreign item attack',

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new PurchaseRequestLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                remarks:
                                    'Company A item',
                            ),
                        ],
                    )
                );

        $itemB =
            $this->createForeignCompanyItem(
                'FROM-PR'
            );

        /*
        |--------------------------------------------------------------------------
        | Snapshot
        |--------------------------------------------------------------------------
        */

        $poCountBefore =
            DB::table(
                'purchase_orders'
            )->count();

        $poDetailCountBefore =
            DB::table(
                'purchase_order_details'
            )->count();

        $auditLogCountBefore =
            DB::table(
                'audit_logs'
            )->count();

        $sequenceBefore =
            DB::table(
                'document_sequences'
            )
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->where(
                    'document_type',
                    'PO'
                )
                ->value(
                    'current_number'
                );

        /*
        |--------------------------------------------------------------------------
        | Execute Attack
        |--------------------------------------------------------------------------
        */

        try {

            app(PurchaseOrderService::class)
                ->create(
                    new PurchaseOrderDTO(
                        purchaseRequestId:
                            $pr->id,

                        supplierName:
                            'Cross Company Supplier',

                        remarks:
                            'PO from PR foreign item attack',

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new PurchaseOrderLineDTO(
                                itemId:
                                    $itemB->id,

                                qty:
                                    10,

                                unitPrice:
                                    7000,

                                remarks:
                                    'Foreign company item',
                            ),
                        ],
                    )
                );

            $this->fail(
                'Purchase Order from PR must reject an item from another company.'
            );

        } catch (\RuntimeException $exception) {

            $this->assertSame(
                'Item does not belong to transaction company.',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Ownership Setup
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            (int) $this->data['company_id'],
            (int) $pr->company_id
        );

        $this->assertNotSame(
            (int) $pr->company_id,
            (int) $itemB->company_id
        );

        /*
        |--------------------------------------------------------------------------
        | Full Rollback
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $poCountBefore,
            DB::table(
                'purchase_orders'
            )->count()
        );

        $this->assertSame(
            $poDetailCountBefore,
            DB::table(
                'purchase_order_details'
            )->count()
        );

        $this->assertSame(
            $auditLogCountBefore,
            DB::table(
                'audit_logs'
            )->count()
        );

        $this->assertSame(
            $sequenceBefore,
            DB::table(
                'document_sequences'
            )
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->where(
                    'document_type',
                    'PO'
                )
                ->value(
                    'current_number'
                )
        );

        $this->assertDatabaseMissing(
            'purchase_order_details',
            [
                'item_id' =>
                    $itemB->id,
            ]
        );
    }

    public function test_manual_purchase_order_rejects_item_from_another_company():
        void
    {
        $itemB =
            $this->createForeignCompanyItem(
                'MANUAL'
            );

        $poCountBefore =
            DB::table(
                'purchase_orders'
            )->count();

        $poDetailCountBefore =
            DB::table(
                'purchase_order_details'
            )->count();

        $auditLogCountBefore =
            DB::table(
                'audit_logs'
            )->count();

        $sequenceBefore =
            DB::table(
                'document_sequences'
            )
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->where(
                    'document_type',
                    'PO'
                )
                ->value(
                    'current_number'
                );

        try {

            app(PurchaseOrderService::class)
                ->create(
                    new PurchaseOrderDTO(
                        purchaseRequestId:
                            null,

                        supplierName:
                            'Manual Cross Company Supplier',

                        remarks:
                            'Manual PO foreign item attack',

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new PurchaseOrderLineDTO(
                                itemId:
                                    $itemB->id,

                                qty:
                                    5,

                                unitPrice:
                                    7500,

                                remarks:
                                    'Foreign company item',
                            ),
                        ],
                    )
                );

            $this->fail(
                'Manual Purchase Order must reject an item from another company.'
            );

        } catch (\RuntimeException $exception) {

            $this->assertSame(
                'Item does not belong to transaction company.',
                $exception->getMessage()
            );
        }

        $this->assertNotSame(
            (int) $this->data['company_id'],
            (int) $itemB->company_id
        );

        $this->assertSame(
            $poCountBefore,
            DB::table(
                'purchase_orders'
            )->count()
        );

        $this->assertSame(
            $poDetailCountBefore,
            DB::table(
                'purchase_order_details'
            )->count()
        );

        $this->assertSame(
            $auditLogCountBefore,
            DB::table(
                'audit_logs'
            )->count()
        );

        $this->assertSame(
            $sequenceBefore,
            DB::table(
                'document_sequences'
            )
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->where(
                    'document_type',
                    'PO'
                )
                ->value(
                    'current_number'
                )
        );

        $this->assertDatabaseMissing(
            'purchase_order_details',
            [
                'item_id' =>
                    $itemB->id,
            ]
        );
    }
}
