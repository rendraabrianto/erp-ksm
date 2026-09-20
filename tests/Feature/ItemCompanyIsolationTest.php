<?php

namespace Tests\Feature;

use App\DTO\ItemDTO;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Services\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class ItemCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private ItemService $service;

    private ItemRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $this->service =
            app(ItemService::class);

        $this->repository =
            app(ItemRepositoryInterface::class);
    }

    public function test_item_service_creates_item_for_explicit_company(): void
    {
        $sourceItem =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        $item =
            $this->service->create(
                new ItemDTO(
                    companyId:
                        $this->data['company_id'],

                    itemCategoryId:
                        $sourceItem->item_category_id,

                    uomId:
                        $sourceItem->uom_id,

                    code:
                        'ITEM-COMPANY-A',

                    name:
                        'Item Company A',

                    description:
                        'Company ownership test item',

                    minimumStock:
                        0,

                    maximumStock:
                        100,

                    isActive:
                        true,
                )
            );

        $this->assertSame(
            $this->data['company_id'],
            (int) $item->company_id
        );

        $this->assertSame(
            $sourceItem->item_category_id,
            (int) $item->item_category_id
        );

        $this->assertDatabaseHas(
            'items',
            [
                'id' =>
                    $item->id,

                'company_id' =>
                    $this->data['company_id'],

                'code' =>
                    'ITEM-COMPANY-A',
            ]
        );
    }

    public function test_item_service_rejects_category_from_another_company(): void
    {
        $sourceItem =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        $companyB =
            $this->createCompanyB();

        $categoryB =
            ItemCategory::query()
                ->create([
                    'company_id' =>
                        $companyB->id,

                    'code' =>
                        'CAT-COMPANY-B',

                    'name' =>
                        'Category Company B',

                    'description' =>
                        'Cross-company category attack test',

                    'is_active' =>
                        true,

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
                ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            "Item category does not belong to company {$this->data['company_id']}."
        );

        $this->service->create(
            new ItemDTO(
                companyId:
                    $this->data['company_id'],

                itemCategoryId:
                    $categoryB->id,

                uomId:
                    $sourceItem->uom_id,

                code:
                    'ITEM-CROSS-COMPANY',

                name:
                    'Cross Company Item',

                description:
                    'Must be rejected',

                minimumStock:
                    0,

                maximumStock:
                    0,

                isActive:
                    true,
            )
        );
    }

    public function test_same_item_code_is_allowed_for_different_companies(): void
    {
        $sourceItem =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        $companyB =
            $this->createCompanyB();

        $categoryB =
            ItemCategory::query()
                ->create([
                    'company_id' =>
                        $companyB->id,

                    'code' =>
                        'CAT-DUP-B',

                    'name' =>
                        'Duplicate Code Category B',

                    'description' =>
                        null,

                    'is_active' =>
                        true,

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
                ]);

        $itemA =
            $this->service->create(
                new ItemDTO(
                    companyId:
                        $this->data['company_id'],

                    itemCategoryId:
                        $sourceItem->item_category_id,

                    uomId:
                        $sourceItem->uom_id,

                    code:
                        'ITEM-SAME-CODE',

                    name:
                        'Same Code Company A',

                    description:
                        null,

                    minimumStock:
                        0,

                    maximumStock:
                        0,

                    isActive:
                        true,
                )
            );

        $itemB =
            $this->service->create(
                new ItemDTO(
                    companyId:
                        $companyB->id,

                    itemCategoryId:
                        $categoryB->id,

                    uomId:
                        $sourceItem->uom_id,

                    code:
                        'ITEM-SAME-CODE',

                    name:
                        'Same Code Company B',

                    description:
                        null,

                    minimumStock:
                        0,

                    maximumStock:
                        0,

                    isActive:
                        true,
                )
            );

        $this->assertNotSame(
            (int) $itemA->company_id,
            (int) $itemB->company_id
        );

        $this->assertSame(
            'ITEM-SAME-CODE',
            $itemA->code
        );

        $this->assertSame(
            'ITEM-SAME-CODE',
            $itemB->code
        );

        $this->assertSame(
            2,
            Item::query()
                ->where(
                    'code',
                    'ITEM-SAME-CODE'
                )
                ->count()
        );
    }

    public function test_repository_reads_are_isolated_by_company(): void
    {
        $sourceItem =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        $companyB =
            $this->createCompanyB();

        $categoryB =
            ItemCategory::query()
                ->create([
                    'company_id' =>
                        $companyB->id,

                    'code' =>
                        'CAT-READ-B',

                    'name' =>
                        'Read Isolation Category B',

                    'description' =>
                        null,

                    'is_active' =>
                        true,

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
                ]);

        $itemB =
            $this->service->create(
                new ItemDTO(
                    companyId:
                        $companyB->id,

                    itemCategoryId:
                        $categoryB->id,

                    uomId:
                        $sourceItem->uom_id,

                    code:
                        'ITEM-READ-B',

                    name:
                        'Read Isolation Item B',

                    description:
                        null,

                    minimumStock:
                        0,

                    maximumStock:
                        0,

                    isActive:
                        true,
                )
            );

        /*
        |--------------------------------------------------------------------------
        | find()
        |--------------------------------------------------------------------------
        */

        $this->assertNull(
            $this->repository->find(
                $itemB->id,
                $this->data['company_id']
            )
        );

        $foundByCompanyB =
            $this->repository->find(
                $itemB->id,
                $companyB->id
            );

        $this->assertNotNull(
            $foundByCompanyB
        );

        $this->assertSame(
            $itemB->id,
            $foundByCompanyB->id
        );

        /*
        |--------------------------------------------------------------------------
        | paginate()
        |--------------------------------------------------------------------------
        */

        $companyAItems =
            $this->repository->paginate(
                $this->data['company_id'],
                100
            );

        $companyBItems =
            $this->repository->paginate(
                $companyB->id,
                100
            );

        $this->assertFalse(
            $companyAItems
                ->getCollection()
                ->contains(
                    'id',
                    $itemB->id
                )
        );

        $this->assertTrue(
            $companyBItems
                ->getCollection()
                ->contains(
                    'id',
                    $itemB->id
                )
        );

        $this->assertTrue(
            $companyAItems
                ->getCollection()
                ->contains(
                    'id',
                    $this->data['item_id']
                )
        );
    }

    private function createCompanyB(): Company
    {
        return Company::query()
            ->create([
                'code' =>
                    'COMP-B-' . uniqid(),

                'name' =>
                    'Company B',

                'phone' =>
                    null,

                'email' =>
                    null,

                'address' =>
                    null,

                'is_active' =>
                    true,
            ]);
    }
}