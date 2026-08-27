<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Eloquent\DashboardRepository;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Eloquent\CompanyRepository;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\UserRepository;

use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Eloquent\BranchRepository;

use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Eloquent\RoleRepository;

use App\Repositories\Contracts\WarehouseRepositoryInterface;
use App\Repositories\Eloquent\WarehouseRepository;

use App\Repositories\Contracts\JournalRepositoryInterface;
use App\Repositories\Eloquent\JournalRepository;

use App\Repositories\Contracts\DocumentSequenceRepositoryInterface;
use App\Repositories\Eloquent\DocumentSequenceRepository;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Eloquent\AuditLogRepository;

use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Eloquent\ItemRepository;

use App\Repositories\Contracts\StockLedgerRepositoryInterface;
use App\Repositories\Eloquent\StockLedgerRepository;

use App\Repositories\Contracts\PurchaseRequestRepositoryInterface;
use App\Repositories\Eloquent\PurchaseRequestRepository;

use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Eloquent\PurchaseOrderRepository;

use App\Repositories\Contracts\GoodsReceiptRepositoryInterface;
use App\Repositories\Eloquent\GoodsReceiptRepository;

use App\Repositories\Contracts\AccountPayableRepositoryInterface;
use App\Repositories\Eloquent\AccountPayableRepository;

use App\Repositories\Contracts\PurchaseInvoiceRepositoryInterface;
use App\Repositories\Eloquent\PurchaseInvoiceRepository;

use App\Repositories\Contracts\PaymentVoucherRepositoryInterface;
use App\Repositories\Eloquent\PaymentVoucherRepository;

use App\Repositories\CustomerRepository;
use App\Repositories\Contracts\CustomerRepositoryInterface;

use App\Repositories\SalesOrderRepository;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;

use App\Repositories\Contracts\DeliveryOrderRepositoryInterface;
use App\Repositories\Eloquent\DeliveryOrderRepository;

use  App\Repositories\Contracts\SalesInvoiceRepositoryInterface;
use  App\Repositories\Eloquent\SalesInvoiceRepository;

use App\Repositories\Contracts\CustomerReceiptRepositoryInterface;
use App\Repositories\Eloquent\CustomerReceiptRepository;

use App\Repositories\Contracts\GeneralLedgerRepositoryInterface;
use App\Repositories\Eloquent\GeneralLedgerRepository;

use App\Repositories\Contracts\TrialBalanceRepositoryInterface;
use App\Repositories\Eloquent\TrialBalanceRepository;

use App\Repositories\Contracts\ProfitLossRepositoryInterface;
use App\Repositories\Eloquent\ProfitLossRepository;

use App\Repositories\Contracts\BalanceSheetRepositoryInterface;
use App\Repositories\Eloquent\BalanceSheetRepository;

use App\Repositories\Contracts\CashFlowRepositoryInterface;
use App\Repositories\Eloquent\CashFlowRepository;

use App\Repositories\Contracts\ARAgingRepositoryInterface;
use App\Repositories\Eloquent\ARAgingRepository;

use App\Repositories\Contracts\APAgingRepositoryInterface;
use App\Repositories\Eloquent\APAgingRepository;

use App\Repositories\Contracts\FinanceDashboardRepositoryInterface;
use App\Repositories\Eloquent\FinanceDashboardRepository;

use App\Repositories\Contracts\InventoryReconciliationRepositoryInterface;
use App\Repositories\Eloquent\InventoryReconciliationRepository;

use App\Repositories\Contracts\InventoryHistoricalReconciliationRepositoryInterface;
use App\Repositories\Eloquent\InventoryHistoricalReconciliationRepository;

use App\Repositories\Contracts\CurrentStockRepositoryInterface;
use App\Repositories\Eloquent\CurrentStockRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            DashboardRepositoryInterface::class,
            DashboardRepository::class
        );
        $this->app->bind(
            CompanyRepositoryInterface::class,
            CompanyRepository::class
        );
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );
        $this->app->bind(
            BranchRepositoryInterface::class,
            BranchRepository::class
        );
        $this->app->bind(
            RoleRepositoryInterface::class,
            RoleRepository::class
        );
        $this->app->bind(
            WarehouseRepositoryInterface::class,
            WarehouseRepository::class
        );        
        $this->app->bind(
            JournalRepositoryInterface::class,
            JournalRepository::class
        );
        $this->app->bind(
            DocumentSequenceRepositoryInterface::class,
            DocumentSequenceRepository::class
        );
        $this->app->bind(
            AuditLogRepositoryInterface::class,
            AuditLogRepository::class
        );
        $this->app->bind(
            ItemRepositoryInterface::class,
            ItemRepository::class
        );
        $this->app->bind(
            StockLedgerRepositoryInterface::class,
            StockLedgerRepository::class
        );
        $this->app->bind(
            PurchaseRequestRepositoryInterface::class,
            PurchaseRequestRepository::class
        );
        $this->app->bind(
            PurchaseOrderRepositoryInterface::class,
            PurchaseOrderRepository::class
        );
        $this->app->bind(
            GoodsReceiptRepositoryInterface::class,
            GoodsReceiptRepository::class
        );
        $this->app->bind(
            AccountPayableRepositoryInterface::class,
            AccountPayableRepository::class
        );
        $this->app->bind(
            PurchaseInvoiceRepositoryInterface::class,
            PurchaseInvoiceRepository::class
        );
        $this->app->bind(
            PaymentVoucherRepositoryInterface::class,
            PaymentVoucherRepository::class
        );
        $this->app->bind(
            CustomerRepositoryInterface::class,
            CustomerRepository::class
        );
        $this->app->bind(
            SalesOrderRepositoryInterface::class,
            SalesOrderRepository::class
        );
        $this->app->bind(
            DeliveryOrderRepositoryInterface::class,
            DeliveryOrderRepository::class
        );
        $this->app->bind(
            SalesInvoiceRepositoryInterface::class,
            SalesInvoiceRepository::class
        );
        $this->app->bind(
            CustomerReceiptRepositoryInterface::class,
            CustomerReceiptRepository::class
        );
        $this->app->bind(
            GeneralLedgerRepositoryInterface::class,
            GeneralLedgerRepository::class
        );
        $this->app->bind(
            TrialBalanceRepositoryInterface::class,
            TrialBalanceRepository::class
        );
        $this->app->bind(
            ProfitLossRepositoryInterface::class,
            ProfitLossRepository::class
        );
        $this->app->bind(
            BalanceSheetRepositoryInterface::class,
            BalanceSheetRepository::class
        );
        $this->app->bind(
            CashFlowRepositoryInterface::class,
            CashFlowRepository::class
        );
        $this->app->bind(
            ARAgingRepositoryInterface::class,
            ARAgingRepository::class
        );
        $this->app->bind(
            APAgingRepositoryInterface::class,
            APAgingRepository::class
        );
        $this->app->bind(
            FinanceDashboardRepositoryInterface::class,
            FinanceDashboardRepository::class
        );
        $this->app->bind(
            InventoryReconciliationRepositoryInterface::class,
            InventoryReconciliationRepository::class
        );
        $this->app->bind(
            InventoryHistoricalReconciliationRepositoryInterface::class,
            InventoryHistoricalReconciliationRepository::class
        );
        $this->app->bind(
            CurrentStockRepositoryInterface::class,
            CurrentStockRepository::class
        );
    }
}