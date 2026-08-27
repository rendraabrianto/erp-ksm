<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;
use App\Models\InventoryReconciliationHistory;

class InventoryReconciliationControllerTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        Permission::firstOrCreate([
            'name' =>
                'inventory.reconciliation.view',

            'guard_name' =>
                'web',
        ]);

        Permission::firstOrCreate([
            'name' =>
                'inventory.reconciliation.preview',

            'guard_name' =>
                'web',
        ]);

        Permission::firstOrCreate([
            'name' =>
                'inventory.reconciliation.apply',

            'guard_name' =>
                'web',
        ]);

        app(
            \Spatie\Permission\PermissionRegistrar::class
        )->forgetCachedPermissions();
    }

    private function user(): User
    {
        return User::findOrFail(
            $this->data['user_id']
        );
    }

    private function payload(): array
    {
        return [
            'warehouse_id' =>
                $this->data['warehouse_id'],

            'item_id' =>
                $this->data['item_id'],

            'date_from' =>
                '2026-08-01',

            'date_to' =>
                '2026-08-31',
        ];
    }

    public function test_guest_cannot_access_reconciliation_index():
        void
    {
        $response =
            $this->get(
                route(
                    'erp.inventory.reconciliation.index'
                )
            );

        $response->assertRedirect(
            route('login')
        );
    }

    public function test_user_without_permission_cannot_access_index():
        void
    {
        $response =
            $this
                ->actingAs(
                    $this->user()
                )
                ->get(
                    route(
                        'erp.inventory.reconciliation.index'
                    )
                );

        $response->assertForbidden();
    }

    public function test_user_with_view_permission_can_access_index():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.reconciliation.view'
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.reconciliation.index'
                    )
                );

        $response->assertOk();

        $response->assertViewIs(
            'erp.inventory.reconciliation.index'
        );
    }

    public function test_user_with_view_permission_can_run_reconciliation():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.reconciliation.view'
        );

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.reconciliation.run'
                    ),
                    $this->payload()
                );

        $response->assertOk();

        $response->assertViewIs(
            'erp.inventory.reconciliation.index'
        );

        $response->assertViewHas(
            'result'
        );
    }

    public function test_user_without_preview_permission_cannot_preview():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.reconciliation.view'
        );

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.reconciliation.preview'
                    ),
                    $this->payload()
                );

        $response->assertForbidden();
    }

    public function test_user_with_preview_permission_can_preview():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo([
            'inventory.reconciliation.view',
            'inventory.reconciliation.preview',
        ]);

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.reconciliation.preview'
                    ),
                    $this->payload()
                );

        $response->assertOk();

        $response->assertViewIs(
            'erp.inventory.reconciliation.preview'
        );

        $response->assertViewHas(
            'preview'
        );
    }

    public function test_user_without_apply_permission_cannot_apply():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo([
            'inventory.reconciliation.view',
            'inventory.reconciliation.preview',
        ]);

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.reconciliation.apply'
                    ),
                    $this->payload()
                );

        $response->assertForbidden();
    }

    public function test_user_with_apply_permission_can_apply():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo([
            'inventory.reconciliation.view',
            'inventory.reconciliation.preview',
            'inventory.reconciliation.apply',
        ]);

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.reconciliation.apply'
                    ),
                    $this->payload()
                );

        $response->assertRedirect(
            route(
                'erp.inventory.reconciliation.index'
            )
        );

        $response->assertSessionHas(
            'success'
        );
    }

    public function test_invalid_date_range_is_rejected():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.reconciliation.view'
        );

        $payload =
            $this->payload();

        $payload['date_from'] =
            '2026-08-31';

        $payload['date_to'] =
            '2026-08-01';

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.reconciliation.run'
                    ),
                    $payload
                );

        $response->assertSessionHasErrors(
            'date_to'
        );
    }

    public function test_invalid_item_is_rejected():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.reconciliation.view'
        );

        $payload =
            $this->payload();

        $payload['item_id'] =
            999999;

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.reconciliation.run'
                    ),
                    $payload
                );

        $response->assertSessionHasErrors(
            'item_id'
        );
    }

    public function test_user_without_view_permission_cannot_access_history():
        void
    {
        $response =
            $this
                ->actingAs(
                    $this->user()
                )
                ->get(
                    route(
                        'erp.inventory.reconciliation.history'
                    )
                );

        $response->assertForbidden();
    }

    public function test_user_with_view_permission_can_access_history():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo(
            'inventory.reconciliation.view'
        );

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.reconciliation.history'
                    )
                );

        $response->assertOk();

        $response->assertViewIs(
            'erp.inventory.reconciliation.history'
        );

        $response->assertViewHas(
            'histories'
        );
    }

    public function test_apply_through_controller_creates_reconciliation_history():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo([
            'inventory.reconciliation.view',
            'inventory.reconciliation.preview',
            'inventory.reconciliation.apply',
        ]);

        $this->assertSame(
            0,
            InventoryReconciliationHistory::count()
        );

        $response =
            $this
                ->actingAs($user)
                ->post(
                    route(
                        'erp.inventory.reconciliation.apply'
                    ),
                    $this->payload()
                );

        $response->assertRedirect(
            route(
                'erp.inventory.reconciliation.index'
            )
        );

        $response->assertSessionHas(
            'success'
        );

        $this->assertSame(
            1,
            InventoryReconciliationHistory::count()
        );

        $history =
            InventoryReconciliationHistory::firstOrFail();

        $this->assertSame(
            3,
            $history->missing_before
        );

        $this->assertSame(
            2,
            $history->mismatch_before
        );

        $this->assertSame(
            3,
            $history->orphan_before
        );

        $this->assertSame(
            8,
            $history->journal_posted_count
        );

        $this->assertSame(
            0,
            $history->missing_after
        );

        $this->assertSame(
            0,
            $history->mismatch_after
        );

        $this->assertSame(
            0,
            $history->orphan_after
        );

        $this->assertTrue(
            $history->is_reconciled_after
        );
    }

    public function test_user_with_view_permission_can_access_history_detail():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo([
            'inventory.reconciliation.view',
            'inventory.reconciliation.preview',
            'inventory.reconciliation.apply',
        ]);

        $this
            ->actingAs($user)
            ->post(
                route(
                    'erp.inventory.reconciliation.apply'
                ),
                $this->payload()
            );

        $history =
            InventoryReconciliationHistory::firstOrFail();

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.reconciliation.history.detail',
                        $history
                    )
                );

        $response->assertOk();

        $response->assertViewIs(
            'erp.inventory.reconciliation.history-detail'
        );

        $response->assertViewHas(
            'history'
        );
    }

    public function test_user_without_view_permission_cannot_access_history_detail():
        void
    {
        $user =
            $this->user();

        $user->givePermissionTo([
            'inventory.reconciliation.view',
            'inventory.reconciliation.preview',
            'inventory.reconciliation.apply',
        ]);

        $this
            ->actingAs($user)
            ->post(
                route(
                    'erp.inventory.reconciliation.apply'
                ),
                $this->payload()
            );

        $history =
            InventoryReconciliationHistory::firstOrFail();

        $user->revokePermissionTo(
            'inventory.reconciliation.view'
        );

        app(
            \Spatie\Permission\PermissionRegistrar::class
        )->forgetCachedPermissions();

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.reconciliation.history.detail',
                        $history
                    )
                );

        $response->assertForbidden();
    }
}