<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\ItemController;

use App\Http\Controllers\ERP\InventoryReconciliationController;
use App\Http\Controllers\ERP\CurrentStockController;
use App\Http\Controllers\ERP\StockLedgerController;
use App\Http\Controllers\ERP\InventoryValuationController;
use App\Http\Controllers\ERP\InventoryAdjustmentController;
use App\Http\Controllers\ERP\JournalController;

/*
|--------------------------------------------------------------------------
| Root
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('dashboard');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/dashboard',
        [DashboardController::class, 'index']
    )->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/profile',
        [ProfileController::class, 'edit']
    )->name('profile.edit');

    Route::patch(
        '/profile',
        [ProfileController::class, 'update']
    )->name('profile.update');

    Route::delete(
        '/profile',
        [ProfileController::class, 'destroy']
    )->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | Master Data
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'companies',
        CompanyController::class
    );

    Route::resource(
        'users',
        UserController::class
    );

    Route::resource(
        'branches',
        BranchController::class
    );

    Route::get(
        '/roles',
        [RoleController::class, 'index']
    )->name('roles.index');

    Route::resource(
        'warehouses',
        WarehouseController::class
    );

    Route::resource(
        'items',
        ItemController::class
    );

    /*
    |--------------------------------------------------------------------------
    | Inventory - Current Stock
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/erp/inventory/current-stock',
        [
            CurrentStockController::class,
            'index'
        ]
    )
        ->middleware(
            'permission:inventory.current-stock.view'
        )
        ->name(
            'erp.inventory.current-stock.index'
        );

    /*
    |--------------------------------------------------------------------------
    | Inventory - Stock Ledger
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/erp/inventory/stock-ledger',
        [
            StockLedgerController::class,
            'index'
        ]
    )
        ->middleware(
            'permission:inventory.stock-ledger.view'
        )
        ->name(
            'erp.inventory.stock-ledger.index'
        );

    /*
    |--------------------------------------------------------------------------
    | Inventory - Valuation
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/erp/inventory/valuation',
        [
            InventoryValuationController::class,
            'index'
        ]
    )
        ->middleware(
            'permission:inventory.valuation.view'
        )
        ->name(
            'erp.inventory.valuation.index'
        );

    /*
    |--------------------------------------------------------------------------
    | Inventory - Adjustments
    |--------------------------------------------------------------------------
    */

    Route::prefix(
        'erp/inventory/adjustments'
    )
        ->name(
            'erp.inventory.adjustment.'
        )
        ->group(function () {

            Route::get(
                '/',
                [
                    InventoryAdjustmentController::class,
                    'index',
                ]
            )
                ->middleware(
                    'permission:inventory.adjustment.view'
                )
                ->name('index');

            Route::get(
                '/create',
                [
                    InventoryAdjustmentController::class,
                    'create',
                ]
            )
                ->middleware(
                    'permission:inventory.adjustment.create'
                )
                ->name('create');

            Route::post(
                '/',
                [
                    InventoryAdjustmentController::class,
                    'store',
                ]
            )
                ->middleware(
                    'permission:inventory.adjustment.create'
                )
                ->name('store');

            Route::get(
                '/{adjustment}',
                [
                    InventoryAdjustmentController::class,
                    'show',
                ]
            )
                ->middleware(
                    'permission:inventory.adjustment.view'
                )
                ->name('show');

            Route::post(
                '/{adjustment}/post',
                [
                    InventoryAdjustmentController::class,
                    'post',
                ]
            )
                ->middleware(
                    'permission:inventory.adjustment.post'
                )
                ->name('post');
        });

    /*
    |--------------------------------------------------------------------------
    | Accounting - Journal Detail
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Route ini HARUS berada di luar group
    | erp/inventory/adjustments.
    |
    */

    Route::get(
        '/erp/accounting/journals/{journal}',
        [
            JournalController::class,
            'show'
        ]
    )
        ->middleware(
            'permission:accounting.journal.view'
        )
        ->name(
            'erp.accounting.journals.show'
        );

    /*
    |--------------------------------------------------------------------------
    | Inventory - Reconciliation
    |--------------------------------------------------------------------------
    */

    Route::prefix(
        'erp/inventory/reconciliation'
    )
        ->name(
            'erp.inventory.reconciliation.'
        )
        ->group(function () {

            Route::get(
                '/',
                [
                    InventoryReconciliationController::class,
                    'index',
                ]
            )
                ->middleware(
                    'permission:inventory.reconciliation.view'
                )
                ->name('index');

            Route::post(
                '/run',
                [
                    InventoryReconciliationController::class,
                    'reconcile',
                ]
            )
                ->middleware(
                    'permission:inventory.reconciliation.view'
                )
                ->name('run');

            Route::post(
                '/preview',
                [
                    InventoryReconciliationController::class,
                    'preview',
                ]
            )
                ->middleware(
                    'permission:inventory.reconciliation.preview'
                )
                ->name('preview');

            Route::post(
                '/apply',
                [
                    InventoryReconciliationController::class,
                    'apply',
                ]
            )
                ->middleware(
                    'permission:inventory.reconciliation.apply'
                )
                ->name('apply');

            Route::get(
                '/history',
                [
                    InventoryReconciliationController::class,
                    'history',
                ]
            )
                ->middleware(
                    'permission:inventory.reconciliation.view'
                )
                ->name('history');

            Route::get(
                '/history/{history}',
                [
                    InventoryReconciliationController::class,
                    'historyDetail',
                ]
            )
                ->middleware(
                    'permission:inventory.reconciliation.view'
                )
                ->name('history.detail');
        });
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';