# Host: localhost  (Version 8.0.30)
# Date: 2026-08-19 15:52:17
# Generator: MySQL-Front 6.1  (Build 1.26)


#
# Structure for table "account_groups"
#

DROP TABLE IF EXISTS `account_groups`;
CREATE TABLE `account_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_groups_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "account_groups"
#

INSERT INTO `account_groups` VALUES (1,'1','Asset',1,'2026-08-04 09:20:03','2026-08-04 09:20:03',NULL),(2,'2','Liability',1,'2026-08-04 09:20:03','2026-08-04 09:20:03',NULL),(3,'3','Equity',1,'2026-08-04 09:20:03','2026-08-04 09:20:03',NULL),(4,'4','Revenue',1,'2026-08-04 09:20:03','2026-08-04 09:20:03',NULL),(5,'5','Cost Of Goods Sold',1,'2026-08-04 09:20:03','2026-08-04 09:20:03',NULL),(6,'6','Expense',1,'2026-08-04 09:20:03','2026-08-04 09:20:03',NULL);

#
# Structure for table "account_payables"
#

DROP TABLE IF EXISTS `account_payables`;
CREATE TABLE `account_payables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_id` bigint unsigned NOT NULL,
  `supplier_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `paid_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(18,2) NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_payables_reference_type_reference_id_index` (`reference_type`,`reference_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "account_payables"
#

INSERT INTO `account_payables` VALUES (2,'PURCHASE_INVOICE',2,'PT Pakan Jaya','2026-08-13','2026-09-12',700000.00,700000.00,0.00,'PAID','2026-08-13 04:32:59','2026-08-13 14:16:12');

#
# Structure for table "account_receivables"
#

DROP TABLE IF EXISTS `account_receivables`;
CREATE TABLE `account_receivables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `sales_invoice_id` bigint unsigned NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `paid_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `balance_amount` decimal(18,2) NOT NULL,
  `status` enum('OPEN','PARTIAL','PAID') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "account_receivables"
#

INSERT INTO `account_receivables` VALUES (1,1,2,'2026-08-15','2026-09-14',9000.00,5000.00,4000.00,'PARTIAL','Invoice Test AR','2026-08-15 09:49:27','2026-08-15 10:39:14',NULL),(2,1,1,'2026-08-15','2026-09-14',18000.00,0.00,18000.00,'OPEN','Reconcile AR Invoice #1','2026-08-17 03:35:21','2026-08-17 03:35:21',NULL);

#
# Structure for table "accounts"
#

DROP TABLE IF EXISTS `accounts`;
CREATE TABLE `accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_group_id` bigint unsigned NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normal_balance` enum('DEBIT','CREDIT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_header` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounts_code_unique` (`code`),
  KEY `accounts_account_group_id_index` (`account_group_id`),
  CONSTRAINT `accounts_account_group_id_foreign` FOREIGN KEY (`account_group_id`) REFERENCES `account_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "accounts"
#

INSERT INTO `accounts` VALUES (1,1,'1001','Kas','DEBIT',0,1,'2026-08-04 09:24:24','2026-08-04 09:24:24',NULL),(2,1,'1002','Bank BCA','DEBIT',0,1,'2026-08-04 09:24:24','2026-08-04 09:24:24',NULL),(3,1,'1101','Piutang Dagang','DEBIT',0,1,'2026-08-04 09:24:24','2026-08-04 09:24:24',NULL),(4,1,'1201','Persediaan Pakan','DEBIT',0,1,'2026-08-04 09:24:24','2026-08-04 09:24:24',NULL),(5,1,'1202','Persediaan OVK','DEBIT',0,1,'2026-08-04 09:24:24','2026-08-04 09:24:24',NULL),(6,1,'1203','Persediaan DOC','DEBIT',0,1,'2026-08-04 09:24:24','2026-08-04 09:24:24',NULL),(7,2,'2001','Hutang Dagang','CREDIT',0,1,'2026-08-04 09:24:24','2026-08-04 09:24:24',NULL),(8,3,'3001','Modal Pemilik','CREDIT',0,1,'2026-08-04 09:24:24','2026-08-04 09:24:24',NULL),(9,4,'4001','Penjualan Pakan','CREDIT',0,1,'2026-08-04 09:24:24','2026-08-04 09:24:24',NULL),(10,4,'4002','Penjualan DOC','CREDIT',0,1,'2026-08-04 09:24:24','2026-08-04 09:24:24',NULL),(11,5,'5001','HPP Pakan','DEBIT',0,1,'2026-08-04 09:24:25','2026-08-04 09:24:25',NULL),(12,5,'5002','HPP DOC','DEBIT',0,1,'2026-08-04 09:24:25','2026-08-04 09:24:25',NULL),(13,6,'6001','Biaya Gaji','DEBIT',0,1,'2026-08-04 09:24:25','2026-08-04 09:24:25',NULL),(14,6,'6002','Biaya Listrik','DEBIT',0,1,'2026-08-04 09:24:25','2026-08-04 09:24:25',NULL),(15,2,'2101','GRNI','CREDIT',0,1,'2026-08-11 13:49:23','2026-08-11 13:49:23',NULL);

#
# Structure for table "cache"
#

DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "cache"
#


#
# Structure for table "cache_locks"
#

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "cache_locks"
#


#
# Structure for table "companies"
#

DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `companies_code_unique` (`code`),
  KEY `companies_code_index` (`code`),
  KEY `companies_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "companies"
#

INSERT INTO `companies` VALUES (1,'KSM','KSM GROUP',NULL,NULL,NULL,1,'2026-08-04 05:06:23','2026-08-04 05:06:23',NULL);

#
# Structure for table "branches"
#

DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branches_company_id_code_unique` (`company_id`,`code`),
  KEY `branches_company_id_index` (`company_id`),
  KEY `branches_name_index` (`name`),
  KEY `branches_is_active_index` (`is_active`),
  CONSTRAINT `branches_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "branches"
#

INSERT INTO `branches` VALUES (1,1,'HO','Head Office',NULL,NULL,NULL,1,'2026-08-04 05:06:23','2026-08-04 05:06:23');

#
# Structure for table "customer_receipts"
#

DROP TABLE IF EXISTS `customer_receipts`;
CREATE TABLE `customer_receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `account_receivable_id` bigint unsigned NOT NULL,
  `cash_bank_account_id` bigint unsigned NOT NULL,
  `receipt_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_receipts_receipt_no_unique` (`receipt_no`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "customer_receipts"
#

INSERT INTO `customer_receipts` VALUES (1,'CR-20260815-00001',1,1,1,'2026-08-15',5000.00,'Pembayaran Invoice',1,'2026-08-15 10:39:14','2026-08-15 10:39:14',NULL);

#
# Structure for table "customers"
#

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `credit_limit` decimal(18,2) NOT NULL DEFAULT '0.00',
  `credit_days` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "customers"
#

INSERT INTO `customers` VALUES (1,'CUST001','CV Mitra Jaya','08123456789','mitra@jaya.com','Makassar',50000000.00,30,1,'2026-08-13 15:08:33','2026-08-13 15:08:33',NULL);

#
# Structure for table "delivery_order_details"
#

DROP TABLE IF EXISTS `delivery_order_details`;
CREATE TABLE `delivery_order_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_order_id` bigint unsigned NOT NULL,
  `sales_order_detail_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL,
  `unit_cost` decimal(18,2) NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "delivery_order_details"
#

INSERT INTO `delivery_order_details` VALUES (1,2,1,1,10.0000,0.00,'Pakan Starter','2026-08-14 10:38:32','2026-08-14 10:38:32'),(2,3,1,1,5.0000,0.00,'Pakan Starter','2026-08-14 10:47:49','2026-08-14 10:47:49'),(3,4,1,1,5.0000,0.00,'Pakan Starter','2026-08-14 10:50:00','2026-08-14 10:50:00'),(4,5,1,1,5.0000,0.00,'Pakan Starter','2026-08-15 03:44:12','2026-08-15 03:44:12'),(5,6,1,1,2.0000,4000.00,'Pakan Starter','2026-08-15 04:23:58','2026-08-15 04:23:58');

#
# Structure for table "delivery_orders"
#

DROP TABLE IF EXISTS `delivery_orders`;
CREATE TABLE `delivery_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `do_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sales_order_id` bigint unsigned NOT NULL,
  `delivery_date` date NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POSTED',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_orders_do_no_unique` (`do_no`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "delivery_orders"
#

INSERT INTO `delivery_orders` VALUES (2,'DO-20260814-00001',1,'2026-08-14','POSTED','Pengiriman pertama',1,'2026-08-14 10:38:32','2026-08-14 10:38:32',NULL),(3,'DO-20260814-00002',1,'2026-08-14','POSTED','Pengiriman kedua',1,'2026-08-14 10:47:49','2026-08-14 10:47:49',NULL),(4,'DO-20260814-00003',1,'2026-08-14','POSTED','Pengiriman kedua',1,'2026-08-14 10:50:00','2026-08-14 10:50:00',NULL),(5,'DO-20260815-00004',1,'2026-08-15','POSTED','Pengiriman kedua',1,'2026-08-15 03:44:12','2026-08-15 03:44:12',NULL),(6,'DO-20260815-00005',1,'2026-08-15','POSTED','Tes HPP Baru',1,'2026-08-15 04:23:58','2026-08-15 04:23:58',NULL);

#
# Structure for table "document_sequences"
#

DROP TABLE IF EXISTS `document_sequences`;
CREATE TABLE `document_sequences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_number` int NOT NULL DEFAULT '0',
  `padding` int NOT NULL DEFAULT '5',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_sequences_document_type_unique` (`document_type`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "document_sequences"
#

INSERT INTO `document_sequences` VALUES (1,'JV','JV','Journal Voucher',24,5,1,'2026-08-05 04:23:16','2026-08-19 07:26:39'),(2,'PO','PO','Purchase Order',1,5,1,'2026-08-05 04:23:16','2026-08-08 08:46:10'),(3,'GR','GR','Goods Receipt',1,5,1,'2026-08-05 04:23:16','2026-08-12 12:09:59'),(4,'PR','PR','Purchase Request',1,5,1,'2026-08-05 04:23:16','2026-08-08 08:17:01'),(5,'SO','SO','Sales Order',1,5,1,'2026-08-05 04:23:16','2026-08-14 08:11:44'),(6,'DO','DO','Delivery Order',5,5,1,'2026-08-05 04:23:16','2026-08-15 04:23:58'),(7,'INV','INV','Invoice',4,5,1,'2026-08-05 04:23:16','2026-08-15 09:49:27'),(8,'PV','PV','Payment Voucher',1,5,1,'2026-08-12 10:10:47','2026-08-13 14:09:13'),(9,'CR','CR',NULL,1,5,1,'2026-08-15 10:37:36','2026-08-15 10:39:14');

#
# Structure for table "failed_jobs"
#

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "failed_jobs"
#


#
# Structure for table "item_categories"
#

DROP TABLE IF EXISTS `item_categories`;
CREATE TABLE `item_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `inventory_account_id` bigint unsigned DEFAULT NULL,
  `cogs_account_id` bigint unsigned DEFAULT NULL,
  `sales_account_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_categories_code_unique` (`code`),
  KEY `item_categories_inventory_account_id_foreign` (`inventory_account_id`),
  KEY `item_categories_cogs_account_id_foreign` (`cogs_account_id`),
  KEY `item_categories_sales_account_id_foreign` (`sales_account_id`),
  CONSTRAINT `item_categories_cogs_account_id_foreign` FOREIGN KEY (`cogs_account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `item_categories_inventory_account_id_foreign` FOREIGN KEY (`inventory_account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `item_categories_sales_account_id_foreign` FOREIGN KEY (`sales_account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "item_categories"
#

INSERT INTO `item_categories` VALUES (1,'PAKAN','Pakan',NULL,1,'2026-08-04 08:27:17','2026-08-14 09:50:01',NULL,4,11,9),(2,'OVK','OVK',NULL,1,'2026-08-04 08:27:17','2026-08-04 08:27:17',NULL,NULL,NULL,NULL),(3,'DOC','DOC',NULL,1,'2026-08-04 08:27:17','2026-08-04 08:27:17',NULL,NULL,NULL,NULL),(4,'PULLET','Pullet',NULL,1,'2026-08-04 08:27:17','2026-08-04 08:27:17',NULL,NULL,NULL,NULL),(5,'TELUR','Telur',NULL,1,'2026-08-04 08:27:17','2026-08-04 08:27:17',NULL,NULL,NULL,NULL),(6,'AYAM','Ayam Hidup',NULL,1,'2026-08-04 08:27:17','2026-08-04 08:27:17',NULL,NULL,NULL,NULL),(7,'AFKIR','Ayam Afkir',NULL,1,'2026-08-04 08:27:17','2026-08-04 08:27:17',NULL,NULL,NULL,NULL),(8,'PERALATAN','Peralatan',NULL,1,'2026-08-04 08:27:17','2026-08-04 08:27:17',NULL,NULL,NULL,NULL),(9,'SPAREPART','Sparepart',NULL,1,'2026-08-04 08:27:17','2026-08-04 08:27:17',NULL,NULL,NULL,NULL);

#
# Structure for table "job_batches"
#

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "job_batches"
#


#
# Structure for table "jobs"
#

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "jobs"
#


#
# Structure for table "migrations"
#

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "migrations"
#

INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_07_28_142753_create_permission_tables',1),(5,'2026_07_28_142921_create_companies_table',1),(6,'2026_07_28_143214_create_branches_table',1),(7,'2026_07_28_143227_add_company_branch_to_users_table',1),(8,'2026_07_28_143246_create_settings_table',1),(9,'2026_07_28_143304_create_document_sequences_table',1),(10,'2026_07_28_143315_create_audit_logs_table',1),(11,'2026_08_04_044857_add_soft_delete_to_companies_table',1),(12,'2026_08_04_071006_create_warehouses_table',2),(13,'2026_08_04_081457_create_uoms_table',3),(14,'2026_08_04_082105_create_item_categories_table',4),(15,'2026_08_04_091741_create_account_groups_table',5),(16,'2026_08_04_092118_create_accounts_table',6),(17,'2026_08_04_093405_create_journals_table',7),(18,'2026_08_04_093515_create_journal_details_table',7),(19,'2026_08_04_100217_add_quantity_and_unit_price_to_journal_details_table',8),(20,'2026_08_08_041628_create_items_table',9),(21,'2026_08_08_052528_create_stock_ledgers_table',10),(22,'2026_08_08_075743_create_purchase_requests_table',11),(23,'2026_08_08_075856_create_purchase_request_details_table',11),(24,'2026_08_08_082338_create_purchase_orders_table',12),(25,'2026_08_08_082505_create_purchase_order_details_table',12),(26,'2026_08_11_054251_create_goods_receipts_table',13),(27,'2026_08_11_054324_create_goods_receipt_details_table',13),(28,'2026_08_11_135556_add_cost_fields_to_items_table',14),(29,'2026_08_11_142034_create_purchase_invoices_table',14),(30,'2026_08_11_142036_create_purchase_invoice_details_table',14),(31,'2026_08_11_144200_create_account_payables_table',15),(32,'2026_08_12_095255_create_payment_vouchers_table',16),(33,'2026_08_13_144715_create_customers_table',17),(34,'2026_08_14_075155_create_sales_orders',18),(35,'2026_08_14_075218_create_sales_order_details',18),(36,'2026_08_14_081416_create_sales_deliveries',19),(37,'2026_08_14_081502_create_sales_delivery_details',19),(38,'2026_08_14_082439_create_delivery_orders',19),(39,'2026_08_14_082441_create_delivery_order_details',19),(40,'2026_08_14_094639_add_account_mapping_to_item_categories_table',19),(41,'2026_08_15_052043_create_sales_invoices',20),(42,'2026_08_15_052113_create_sales_invoice_details',20),(43,'2026_08_15_092843_create_account_receivables_table',21),(44,'2026_08_15_095829_create_customer_receipts_table',22),(45,'2026_08_19_065159_add_reconciliation_fields_to_journals_table',23);

#
# Structure for table "password_reset_tokens"
#

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "password_reset_tokens"
#


#
# Structure for table "permissions"
#

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "permissions"
#


#
# Structure for table "model_has_permissions"
#

DROP TABLE IF EXISTS `model_has_permissions`;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "model_has_permissions"
#


#
# Structure for table "roles"
#

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "roles"
#

INSERT INTO `roles` VALUES (1,'Super Admin','web','2026-08-04 05:06:23','2026-08-04 05:06:23'),(2,'Director','web','2026-08-04 05:06:23','2026-08-04 05:06:23'),(3,'Branch Manager','web','2026-08-04 05:06:23','2026-08-04 05:06:23'),(4,'Accounting Manager','web','2026-08-04 05:06:23','2026-08-04 05:06:23'),(5,'Inventory Manager','web','2026-08-04 05:06:23','2026-08-04 05:06:23'),(6,'Purchasing Manager','web','2026-08-04 05:06:23','2026-08-04 05:06:23'),(7,'Sales Manager','web','2026-08-04 05:06:23','2026-08-04 05:06:23');

#
# Structure for table "role_has_permissions"
#

DROP TABLE IF EXISTS `role_has_permissions`;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "role_has_permissions"
#


#
# Structure for table "model_has_roles"
#

DROP TABLE IF EXISTS `model_has_roles`;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "model_has_roles"
#

INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\User',1);

#
# Structure for table "sales_deliveries"
#

DROP TABLE IF EXISTS `sales_deliveries`;
CREATE TABLE `sales_deliveries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `do_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sales_order_id` bigint unsigned NOT NULL,
  `delivery_date` date NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POSTED',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_deliveries_do_no_unique` (`do_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "sales_deliveries"
#


#
# Structure for table "sales_delivery_details"
#

DROP TABLE IF EXISTS `sales_delivery_details`;
CREATE TABLE `sales_delivery_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_delivery_id` bigint unsigned NOT NULL,
  `sales_order_detail_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `qty_delivered` decimal(18,4) NOT NULL,
  `unit_cost` decimal(18,2) NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "sales_delivery_details"
#


#
# Structure for table "sales_invoice_details"
#

DROP TABLE IF EXISTS `sales_invoice_details`;
CREATE TABLE `sales_invoice_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_invoice_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL,
  `discount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(18,2) NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "sales_invoice_details"
#

INSERT INTO `sales_invoice_details` VALUES (1,1,1,2.0000,9000.00,0.00,18000.00,'Pakan Starter','2026-08-15 09:16:31','2026-08-15 09:16:31'),(2,2,1,1.0000,9000.00,0.00,9000.00,'Pakan Starter','2026-08-15 09:49:27','2026-08-15 09:49:27');

#
# Structure for table "sales_invoices"
#

DROP TABLE IF EXISTS `sales_invoices`;
CREATE TABLE `sales_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `delivery_order_id` bigint unsigned NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` enum('DRAFT','POSTED','PAID') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POSTED',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_invoices_invoice_no_unique` (`invoice_no`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "sales_invoices"
#

INSERT INTO `sales_invoices` VALUES (1,'INV-20260815-00003',1,6,'2026-08-15','2026-09-14',18000.00,0.00,0.00,18000.00,'POSTED','Invoice DO-00005',1,'2026-08-15 09:16:31','2026-08-15 09:16:31',NULL),(2,'INV-20260815-00004',1,6,'2026-08-15','2026-09-14',9000.00,0.00,0.00,9000.00,'POSTED','Invoice Test AR',1,'2026-08-15 09:49:27','2026-08-15 09:49:27',NULL);

#
# Structure for table "sessions"
#

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "sessions"
#

INSERT INTO `sessions` VALUES ('0A0K5fgkwjXLYPbAhtb5ZuFZdnch5XsMlBe5cMSg',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiI4RzVxOGVvSkFQSE1RRmpGc2w4R3UzdlhhTng4Q0Y1NGVqNEx1eE9PIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWNcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786899857),('0kk8M5nFFiOriQUEprknM9jhUPPq55Z17g7Nbs4g',NULL,'209.38.64.200','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJaU09lNGc1RXZEUWFkSHBIRTJpNWFReGhRNnliMnJZNERjaUxoUms5IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzM2LjY3LjE5Mi4yNTRcL0VSUC1LU01cL3B1YmxpYyIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1787099542),('1ajFlxnN3VyWPM3GnTY14uIlumSKAQNnKPUg3t0o',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJJaHV2Q1N1aENxb25tR09UZDc0Z0ZIazZpZldoeGNlS0xCY05DR1dBIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzM2LjY3LjE5Mi4yNTQ6ODA4MFwvRVJQLUtTTVwvcHVibGljIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786871344),('1tqg1KmytVW5F3qa9tIHGNIwINC6OIpQo6YA7qnU',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJSajdBemtpbjlVOFpVQWI3RFlibk1yMENwUUNCa1ZwUnJVMWVsQXYyIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWNcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786885332),('344XwNvqrBNH0wtk3zDVS2TtdsdiqbihqO3t8BvM',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJCVlVXR0lEVWZHcDNWY1BQMjBNTWREOHhIZ0l1S1Jjck4zT2lqN2RYIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC93YXJlaG91c2VzIiwicm91dGUiOiJ3YXJlaG91c2VzLmluZGV4In0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfSwidXJsIjpbXSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjF9',1786167835),('7g38k4izoqah43oh5ewXPRVmq0VSDZnqYiV1ZD7f',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJxUmtyOGlySk5CeHNvQ0pIWW1GS0t3QUU4MUFSdktjSW55SGdFVjZLIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWMiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1786885331),('7S4yfdfMK7YbAGbcZogT7Eg4ZoB1FnThSBtvRa0Z',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJ6Zk1aMUtMZ3dpUUt6UW9jSHhLMHRQZlJGS3pmb1dSb1VGNkcwelBlIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWNcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786871336),('8cMZFELIAZQzZa9oofX4oba6HXRnZWnPAwKWeZij',NULL,'66.132.186.186','Mozilla/5.0 (compatible; CensysInspect/1.1; +https://about.censys.io/)','eyJfdG9rZW4iOiJCdUlyOEM5OGxvSjNuRVJFT2pNQkpCeTZUQU9odnZtaHRlaTVZVUxsIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWMiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1786859460),('8qhBoJXheLOx8Q344G9xTZX7hyHRK51sgUKAIkW5',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJTcEtWOXFsMGJOazlYUmVBNzd0aTRveFdRVWhMazlXM3haanhISzhvIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cHM6XC9cLzM2LjY3LjE5Mi4yNTRcL0VSUC1LU01cL3B1YmxpY1wvZGFzaGJvYXJkIn0sIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwczpcL1wvMzYuNjcuMTkyLjI1NFwvRVJQLUtTTVwvcHVibGljXC9kYXNoYm9hcmQiLCJyb3V0ZSI6ImRhc2hib2FyZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1787116022),('apgExraVQDUT2JuQE2t7F58ouyLjJdAwqWMrHguD',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJacXlZTk1vaFJkSlV2Q0NXcHgxTkI4S0kxVFJHTW9qZHA5eEtBUHVkIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzM2LjY3LjE5Mi4yNTQ6ODA4MFwvRVJQLUtTTVwvcHVibGljIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786860141),('BISA7dPLoAcUGvSDmsIX8Ud2scCDXB030OA8bTF3',NULL,'213.32.122.82','Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36','eyJfdG9rZW4iOiJXblFjQWQwVVFZc051Z1ZpWnBidENjVHZ5N0lFUG5WcThGNHZ6Q0JoIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWMiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1786827884),('dwObfKZVLkrmuFKZ1vrslrJD7S7GBOYkGwDarznY',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJ1alBsZkxub1BpYWdqaUx5ZzNOZzBaeW8xckRib3E3ZGlUYjVCMjJEIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWNcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1787116022),('h3TF6WTJgLKUAV3zU49CpaFS1KBZQH38WMbI8D3C',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJsNnNJcWlqVEpMRU5TaW1qazlhVVJ4SGlaVnBIWmhBMUk3MVFwUFR4IiwidXJsIjp7ImludGVuZGVkIjoiaHR0cHM6XC9cLzM2LjY3LjE5Mi4yNTRcL0VSUC1LU01cL3B1YmxpY1wvZGFzaGJvYXJkIn0sIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwczpcL1wvMzYuNjcuMTkyLjI1NFwvRVJQLUtTTVwvcHVibGljXC9kYXNoYm9hcmQiLCJyb3V0ZSI6ImRhc2hib2FyZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786885332),('ICrx9nLWqXNSwi5aw63EXJbjyYMBpbWQQuLrTa16',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJmTkQ1NTFhUW1QaFJuWUNZam1aTmV4TGZBUlVTcVREd2NYWWVudUYxIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWMiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1786871336),('ICTEZUaxYTDZhgf3w3cZBfqCZUeyLI9AsyNyksSv',NULL,'185.177.72.5','curl/8.7.1','eyJfdG9rZW4iOiJvYVNtR2JHZVViZkV6TjJ3VG9vd3lSYmZXWjFWNlIwUWJBTU5oa3FvIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzM2LjY3LjE5Mi4yNTQ6ODA4MFwvRVJQLUtTTVwvcHVibGljIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786833034),('JPHSn4V38I9XJKcP1PJFP9OKjtrk3Q2ZmXS2HaVU',NULL,'185.177.72.5','curl/8.7.1','eyJfdG9rZW4iOiJPSDNXTnRnbDNzOG1FaUtvSzc3WWVSRVZEMEJCRGg5M0Q4UEluSXZlIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzM2LjY3LjE5Mi4yNTQ6ODA4MFwvRVJQLUtTTVwvcHVibGljIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786832555),('Ktx347RAshCd0v9qliirk5tCMPEsHUUkiqOrCgfk',NULL,'213.32.122.82','Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36','eyJfdG9rZW4iOiJnZTJDV3E1M3E0VXVxWUw2MVR4YW0xQ05MenFySEo1WWVDUVU0b2xTIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cHM6XC9cLzM2LjY3LjE5Mi4yNTRcL0VSUC1LU01cL3B1YmxpY1wvZGFzaGJvYXJkIn0sIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwczpcL1wvMzYuNjcuMTkyLjI1NFwvRVJQLUtTTVwvcHVibGljXC9kYXNoYm9hcmQiLCJyb3V0ZSI6ImRhc2hib2FyZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786827885),('lgfWstveOuiCDQTrHUByRF3yt1LJ9e5FdCPugDIv',NULL,'134.199.233.151','Mozilla/5.0 (X11; Linux x86_64; rv:142.0) Gecko/20100101 Firefox/142.0','eyJfdG9rZW4iOiJTUE5FT2U3R3ZiZm1GSllrMFZ0UzV6NDg0Ym80cDZJQWQ0V2t2ckJ5IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWMiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1786897531),('OA97aQEcCsQJoNWNO74pujDlEQFDEWdL4wZMfPvN',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJ3U3ZEYnRrUERPTEx0QlA1dXl0MFZ1VlRNajI2SnNjQ09rSGxYT0tQIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWMiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1787116021),('oYYbkbRf8rHnWLzmRDW1NW8haA8Y42uZZ2ri9bmJ',NULL,'185.177.72.5','curl/8.7.1','eyJfdG9rZW4iOiI0bks1YWx0WlRPbVNYSnpIWDlydjZqNUVXRzI3ZnRlZ013Z0Zwb3gyIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzM2LjY3LjE5Mi4yNTQ6ODA4MFwvRVJQLUtTTVwvcHVibGljIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786830505),('P2o1n32BVznDgwlCYG6BBfAWCBgXxzhnpHDysgaY',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJ1Z0tlQWE5WmNhR2VpUnlVZEw5Rm5BQ3MyRDJjSEcwTVhzWkY3UWp2IiwidXJsIjp7ImludGVuZGVkIjoiaHR0cHM6XC9cLzM2LjY3LjE5Mi4yNTRcL0VSUC1LU01cL3B1YmxpY1wvZGFzaGJvYXJkIn0sIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwczpcL1wvMzYuNjcuMTkyLjI1NFwvRVJQLUtTTVwvcHVibGljXC9kYXNoYm9hcmQiLCJyb3V0ZSI6ImRhc2hib2FyZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786899857),('qNTmipSGFz8qmwKU7xq6rxaUz4VJqHZTyyNQbRYs',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJ4NEQweWc5djV4ck5OMGhGdWtSNUJWclU2U2o1MVBjQkJpdTdxOGV4IiwidXJsIjp7ImludGVuZGVkIjoiaHR0cHM6XC9cLzM2LjY3LjE5Mi4yNTRcL0VSUC1LU01cL3B1YmxpY1wvZGFzaGJvYXJkIn0sIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwczpcL1wvMzYuNjcuMTkyLjI1NFwvRVJQLUtTTVwvcHVibGljXC9kYXNoYm9hcmQiLCJyb3V0ZSI6ImRhc2hib2FyZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786871336),('rkIWJqDmb8e1uMIMR63cZGEa0acV3ZshZxOANnp1',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJYV1VtcXdQYlFicUxWRHhBSTIyMzlCamVBQVlaUW16dnpSQjZyMjV6IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWMiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1786957399),('SB9zaqJ2qMqogayDr1yjwh0phS0MTOxkEWmigoQ0',NULL,'213.32.122.82','Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36','eyJfdG9rZW4iOiI0YXhhTHdza0hFSVlRU0E5RWl2T2kwOXY3TkZybkZsSkVIcEpCMUVyIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWNcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786827887),('ShIbDeVlADdZbKDfHegrHb0d0zXi2NDEXqog1y9L',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJZREVVbTlLbGN5dWF6alBWZmpTakttdmJnT0xlVHJhT1U0aG04bXB1IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWNcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786957400),('TDXkfug86FmN06XWfG7R4bDGxQ6OVHgn2I7MFGlQ',NULL,'46.202.52.195','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJmOGpMZ1E1emlsZk1UU1ZpdTNucm90MEx2OVNZU3Z5TjZBR2dudlFQIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWNcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sInVybCI6eyJpbnRlbmRlZCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWNcL2Rhc2hib2FyZCJ9fQ==',1786869645),('VDwde5M7Us9ateokMfm9bH7875UY5GCIqSvvDJVT',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJabnBmaHphZmFSVlFtbHpCZTdIcWpaeG45YjN0UzQ2V2tkRXV0MUhyIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHBzOlwvXC8zNi42Ny4xOTIuMjU0XC9FUlAtS1NNXC9wdWJsaWMiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1786899857),('WnOiFMy3gBSaC1wUaZdUH6D2m2WP2WCFWtHkl2ur',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','eyJfdG9rZW4iOiI2WmRPTTQ3WHhITk5oMHY5WUVEY0FmVW5nd1hHYnozUFZKdEFLSEV0IiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9jb21wYW5pZXMiLCJyb3V0ZSI6ImNvbXBhbmllcy5pbmRleCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxfQ==',1785918474),('WYEaKK3WhSyPKieL2UEXNRUGvu5Id6P0oADE2SfH',NULL,'66.132.195.46','Mozilla/5.0 (compatible; CensysInspect/1.1; +https://about.censys.io/)','eyJfdG9rZW4iOiJ2bTZjM0RzTk02RWxTV3VoMlpYUGprMHFvbVpHOURLeks0a2t2N1FVIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzM2LjY3LjE5Mi4yNTQ6ODA4MFwvRVJQLUtTTVwvcHVibGljIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1787059988),('y3O47cZvwLihYNY3rZMIwruBbNBHzvl9Lbt7oMj1',NULL,'172.105.82.111','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJ1NUFiNjdjOGtkVGpkRllEWldUM2hSWDNqQ05ONjdyYkY1ZkNwTG9VIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cHM6XC9cLzM2LjY3LjE5Mi4yNTRcL0VSUC1LU01cL3B1YmxpY1wvZGFzaGJvYXJkIn0sIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwczpcL1wvMzYuNjcuMTkyLjI1NFwvRVJQLUtTTVwvcHVibGljXC9kYXNoYm9hcmQiLCJyb3V0ZSI6ImRhc2hib2FyZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786957399),('YwJMiO3URgGscwEUEhvBDIC5BjEbydhAjHCz8IsV',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','eyJfdG9rZW4iOiIyV0VvWmlETnJBbDJIdFJGUW55anBHSFNDQUtrRjd0UWNwMzJYeUdhIiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9kYXNoYm9hcmQiLCJyb3V0ZSI6ImRhc2hib2FyZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxfQ==',1785845855);

#
# Structure for table "settings"
#

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "settings"
#


#
# Structure for table "uoms"
#

DROP TABLE IF EXISTS `uoms`;
CREATE TABLE `uoms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uoms_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "uoms"
#

INSERT INTO `uoms` VALUES (1,'KG','Kilogram','Kg',1,'2026-08-04 08:19:19','2026-08-04 08:19:19',NULL),(2,'TON','Ton','Ton',1,'2026-08-04 08:19:19','2026-08-04 08:19:19',NULL),(3,'SAK','Sak','Sak',1,'2026-08-04 08:19:19','2026-08-04 08:19:19',NULL),(4,'BOX','Box','Box',1,'2026-08-04 08:19:19','2026-08-04 08:19:19',NULL),(5,'BOTOL','Botol','Btl',1,'2026-08-04 08:19:19','2026-08-04 08:19:19',NULL),(6,'TRAY','Tray','Tray',1,'2026-08-04 08:19:19','2026-08-04 08:19:19',NULL),(7,'EKOR','Ekor','Ekor',1,'2026-08-04 08:19:19','2026-08-04 08:19:19',NULL),(8,'PCS','Pieces','Pcs',1,'2026-08-04 08:19:19','2026-08-04 08:19:19',NULL);

#
# Structure for table "items"
#

DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_category_id` bigint unsigned NOT NULL,
  `uom_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `minimum_stock` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `maximum_stock` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `average_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `last_purchase_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `items_code_unique` (`code`),
  KEY `items_item_category_id_index` (`item_category_id`),
  KEY `items_uom_id_index` (`uom_id`),
  KEY `items_code_index` (`code`),
  CONSTRAINT `items_item_category_id_foreign` FOREIGN KEY (`item_category_id`) REFERENCES `item_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `items_uom_id_foreign` FOREIGN KEY (`uom_id`) REFERENCES `uoms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "items"
#

INSERT INTO `items` VALUES (1,1,1,'BRG001','Pakan Starter','Pakan Ayam Starter',10.0000,1000.0000,5815.11,7000.00,1,'2026-08-08 06:40:34','2026-08-18 07:23:01',NULL);

#
# Structure for table "users"
#

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned DEFAULT NULL,
  `branch_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_company_id_foreign` (`company_id`),
  KEY `users_branch_id_foreign` (`branch_id`),
  CONSTRAINT `users_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `users_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "users"
#

INSERT INTO `users` VALUES (1,1,1,'Super Admin','superadmin@ksm.local',NULL,'$2y$12$wYSOZqNOHDssT2b4GPXc.uNV4TmDlwuhec8Q3tCjIR5Jd51q/tKyS',NULL,1,NULL,'2026-08-04 05:06:24','2026-08-05 04:23:27');

#
# Structure for table "sales_orders"
#

DROP TABLE IF EXISTS `sales_orders`;
CREATE TABLE `sales_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `so_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `order_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_orders_so_no_unique` (`so_no`),
  KEY `sales_orders_customer_id_foreign` (`customer_id`),
  KEY `sales_orders_created_by_foreign` (`created_by`),
  CONSTRAINT `sales_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `sales_orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "sales_orders"
#

INSERT INTO `sales_orders` VALUES (1,'SO-20260814-00001',1,'2026-08-14','2026-08-17','APPROVED','Order Mitra Jaya',1,'2026-08-14 08:11:44','2026-08-14 08:11:44',NULL);

#
# Structure for table "sales_order_details"
#

DROP TABLE IF EXISTS `sales_order_details`;
CREATE TABLE `sales_order_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_order_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL,
  `discount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `delivered_qty` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_order_details_sales_order_id_foreign` (`sales_order_id`),
  KEY `sales_order_details_item_id_foreign` (`item_id`),
  CONSTRAINT `sales_order_details_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_order_details_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "sales_order_details"
#

INSERT INTO `sales_order_details` VALUES (1,1,1,50.0000,9000.00,0.00,27.0000,'Pakan Starter','2026-08-14 08:11:44','2026-08-15 04:23:58');

#
# Structure for table "payment_vouchers"
#

DROP TABLE IF EXISTS `payment_vouchers`;
CREATE TABLE `payment_vouchers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `voucher_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_date` date NOT NULL,
  `account_payable_id` bigint unsigned NOT NULL,
  `cash_bank_account_id` bigint unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `payment_method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_vouchers_voucher_no_unique` (`voucher_no`),
  KEY `payment_vouchers_account_payable_id_foreign` (`account_payable_id`),
  KEY `payment_vouchers_cash_bank_account_id_foreign` (`cash_bank_account_id`),
  KEY `payment_vouchers_created_by_foreign` (`created_by`),
  CONSTRAINT `payment_vouchers_account_payable_id_foreign` FOREIGN KEY (`account_payable_id`) REFERENCES `account_payables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_vouchers_cash_bank_account_id_foreign` FOREIGN KEY (`cash_bank_account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `payment_vouchers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "payment_vouchers"
#

INSERT INTO `payment_vouchers` VALUES (1,'PV-20260813-00001','2026-08-13',2,2,700000.00,'TRANSFER','Pelunasan Invoice PT Pakan Jaya',1,'2026-08-13 14:09:13','2026-08-13 14:09:13',NULL);

#
# Structure for table "journals"
#

DROP TABLE IF EXISTS `journals`;
CREATE TABLE `journals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `journal_date` date NOT NULL,
  `journal_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `journal_purpose` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NORMAL',
  `source_journal_id` bigint unsigned DEFAULT NULL,
  `reconciliation_key` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `journals_journal_no_unique` (`journal_no`),
  UNIQUE KEY `journals_reconciliation_key_unique` (`reconciliation_key`),
  KEY `journals_created_by_foreign` (`created_by`),
  KEY `journals_journal_date_index` (`journal_date`),
  KEY `journals_journal_no_index` (`journal_no`),
  KEY `journals_source_journal_id_foreign` (`source_journal_id`),
  CONSTRAINT `journals_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `journals_source_journal_id_foreign` FOREIGN KEY (`source_journal_id`) REFERENCES `journals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "journals"
#

INSERT INTO `journals` VALUES (1,'2026-08-04','JV-20260804102055','OPENING',1,'NORMAL',NULL,NULL,'Setoran Modal Awal',1,'2026-08-04 10:20:55','2026-08-04 10:20:55'),(2,'2026-08-05','JV-20260805-00003','OPENING',2,'NORMAL',NULL,NULL,'Modal Tambahan',1,'2026-08-05 07:01:10','2026-08-05 07:01:10'),(3,'2026-08-12','JV-20260812-00004','GOODS_RECEIPT',2,'NORMAL',NULL,NULL,'Auto Journal Goods Receipt',1,'2026-08-12 10:57:39','2026-08-12 10:57:39'),(4,'2026-08-12','JV-20260812-00005','GOODS_RECEIPT',3,'NORMAL',NULL,NULL,'Auto Journal Goods Receipt',1,'2026-08-12 12:09:59','2026-08-12 12:09:59'),(6,'2026-08-13','JV-20260813-00007','PURCHASE_INVOICE',2,'NORMAL',NULL,NULL,'Auto Journal Purchase Invoice',1,'2026-08-13 04:33:00','2026-08-13 04:33:00'),(7,'2026-08-13','JV-20260813-00008','PAYMENT_VOUCHER',1,'NORMAL',NULL,NULL,'Payment Voucher',1,'2026-08-13 14:09:13','2026-08-13 14:09:13'),(8,'2026-08-15','JV-20260815-00009','DELIVERY_ORDER',999,'NORMAL',NULL,NULL,'Auto Journal Delivery Order',1,'2026-08-15 03:30:07','2026-08-15 03:30:07'),(9,'2026-08-15','JV-20260815-00010','DELIVERY_ORDER',999,'NORMAL',NULL,NULL,'Auto Journal Delivery Order',1,'2026-08-15 03:30:21','2026-08-15 03:30:21'),(10,'2026-08-15','JV-20260815-00011','DELIVERY_ORDER',5,'NORMAL',NULL,NULL,'Auto Journal Delivery Order',1,'2026-08-15 03:44:13','2026-08-15 03:44:13'),(11,'2026-08-15','JV-20260815-00012','DELIVERY_ORDER',6,'NORMAL',NULL,NULL,'Auto Journal Delivery Order',1,'2026-08-15 04:23:58','2026-08-15 04:23:58'),(12,'2026-08-15','JV-20260815-00013','SALES_INVOICE',1,'NORMAL',NULL,NULL,'Auto Journal Sales Invoice',1,'2026-08-15 09:16:31','2026-08-15 09:16:31'),(13,'2026-08-15','JV-20260815-00014','SALES_INVOICE',2,'NORMAL',NULL,NULL,'Auto Journal Sales Invoice',1,'2026-08-15 09:49:27','2026-08-15 09:49:27'),(15,'2026-08-15','JV-20260815-00016','CUSTOMER_RECEIPT',1,'NORMAL',NULL,NULL,'Customer Receipt',1,'2026-08-15 10:39:14','2026-08-15 10:39:14'),(16,'2026-08-14','JV-20260819-00017','DELIVERY_ORDER',2,'RECOVERY',NULL,'INVREC:RECOVER_MISSING_JOURNAL:DELIVERY_ORDER:2:ITEM:1:WH:1','Recovery missing journal Delivery Order',1,'2026-08-19 07:26:39','2026-08-19 07:26:39'),(17,'2026-08-14','JV-20260819-00018','DELIVERY_ORDER',3,'RECOVERY',NULL,'INVREC:RECOVER_MISSING_JOURNAL:DELIVERY_ORDER:3:ITEM:1:WH:1','Recovery missing journal Delivery Order',1,'2026-08-19 07:26:39','2026-08-19 07:26:39'),(18,'2026-08-14','JV-20260819-00019','DELIVERY_ORDER',4,'RECOVERY',NULL,'INVREC:RECOVER_MISSING_JOURNAL:DELIVERY_ORDER:4:ITEM:1:WH:1','Recovery missing journal Delivery Order',1,'2026-08-19 07:26:39','2026-08-19 07:26:39'),(19,'2026-08-15','JV-20260819-00020','DELIVERY_ORDER',5,'COST_CORRECTION',NULL,'INVREC:CORRECT_COST_MISMATCH:DELIVERY_ORDER:5:ITEM:1:WH:1','Correction inventory costing mismatch',1,'2026-08-19 07:26:39','2026-08-19 07:26:39'),(20,'2026-08-15','JV-20260819-00021','DELIVERY_ORDER',6,'COST_CORRECTION',NULL,'INVREC:CORRECT_COST_MISMATCH:DELIVERY_ORDER:6:ITEM:1:WH:1','Correction inventory costing mismatch',1,'2026-08-19 07:26:39','2026-08-19 07:26:39'),(21,'2026-08-12','JV-20260819-00022','GOODS_RECEIPT',2,'REVERSAL',3,'INVREC:REVERSAL:JOURNAL:3','Reversal orphan journal JV-20260812-00004',1,'2026-08-19 07:26:39','2026-08-19 07:26:39'),(22,'2026-08-15','JV-20260819-00023','DELIVERY_ORDER',999,'REVERSAL',8,'INVREC:REVERSAL:JOURNAL:8','Reversal orphan journal JV-20260815-00009',1,'2026-08-19 07:26:39','2026-08-19 07:26:39'),(23,'2026-08-15','JV-20260819-00024','DELIVERY_ORDER',999,'REVERSAL',9,'INVREC:REVERSAL:JOURNAL:9','Reversal orphan journal JV-20260815-00010',1,'2026-08-19 07:26:39','2026-08-19 07:26:39');

#
# Structure for table "journal_details"
#

DROP TABLE IF EXISTS `journal_details`;
CREATE TABLE `journal_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `journal_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `quantity` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `unit_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `debit` decimal(18,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(18,2) NOT NULL DEFAULT '0.00',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_details_journal_id_foreign` (`journal_id`),
  KEY `journal_details_account_id_index` (`account_id`),
  CONSTRAINT `journal_details_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_details_journal_id_foreign` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "journal_details"
#

INSERT INTO `journal_details` VALUES (1,1,1,0.0000,0.00,10000000.00,0.00,'Kas Masuk','2026-08-04 10:20:55','2026-08-04 10:20:55'),(2,1,8,0.0000,0.00,0.00,10000000.00,'Modal Pemilik','2026-08-04 10:20:55','2026-08-04 10:20:55'),(3,2,1,0.0000,0.00,5000000.00,0.00,'Kas','2026-08-05 07:01:10','2026-08-05 07:01:10'),(4,2,8,0.0000,0.00,0.00,5000000.00,'Modal','2026-08-05 07:01:10','2026-08-05 07:01:10'),(5,3,4,0.0000,0.00,700000.00,0.00,'Inventory','2026-08-12 10:57:39','2026-08-12 10:57:39'),(6,3,15,0.0000,0.00,0.00,700000.00,'GRNI','2026-08-12 10:57:39','2026-08-12 10:57:39'),(7,4,4,0.0000,0.00,700000.00,0.00,'Inventory','2026-08-12 12:09:59','2026-08-12 12:09:59'),(8,4,15,0.0000,0.00,0.00,700000.00,'GRNI','2026-08-12 12:09:59','2026-08-12 12:09:59'),(11,6,15,0.0000,0.00,700000.00,0.00,'Reverse GRNI','2026-08-13 04:33:00','2026-08-13 04:33:00'),(12,6,7,0.0000,0.00,0.00,700000.00,'Account Payable','2026-08-13 04:33:00','2026-08-13 04:33:00'),(13,7,7,0.0000,0.00,700000.00,0.00,'Hutang Dagang','2026-08-13 14:09:13','2026-08-13 14:09:13'),(14,7,2,0.0000,0.00,0.00,700000.00,'Kas/Bank','2026-08-13 14:09:13','2026-08-13 14:09:13'),(15,8,11,0.0000,0.00,10000.00,0.00,'HPP','2026-08-15 03:30:07','2026-08-15 03:30:07'),(16,8,4,0.0000,0.00,0.00,10000.00,'Persediaan','2026-08-15 03:30:07','2026-08-15 03:30:07'),(17,9,11,0.0000,0.00,10000.00,0.00,'HPP','2026-08-15 03:30:21','2026-08-15 03:30:21'),(18,9,4,0.0000,0.00,0.00,10000.00,'Persediaan','2026-08-15 03:30:21','2026-08-15 03:30:21'),(19,10,11,0.0000,0.00,20000.00,0.00,'HPP','2026-08-15 03:44:13','2026-08-15 03:44:13'),(20,10,4,0.0000,0.00,0.00,20000.00,'Persediaan','2026-08-15 03:44:13','2026-08-15 03:44:13'),(21,11,11,0.0000,0.00,8000.00,0.00,'HPP','2026-08-15 04:23:58','2026-08-15 04:23:58'),(22,11,4,0.0000,0.00,0.00,8000.00,'Persediaan','2026-08-15 04:23:58','2026-08-15 04:23:58'),(23,12,3,0.0000,0.00,18000.00,0.00,'Piutang Dagang','2026-08-15 09:16:31','2026-08-15 09:16:31'),(24,12,9,0.0000,0.00,0.00,18000.00,'Penjualan','2026-08-15 09:16:31','2026-08-15 09:16:31'),(25,13,3,0.0000,0.00,9000.00,0.00,'Piutang Dagang','2026-08-15 09:49:27','2026-08-15 09:49:27'),(26,13,9,0.0000,0.00,0.00,9000.00,'Penjualan','2026-08-15 09:49:27','2026-08-15 09:49:27'),(29,15,1,0.0000,0.00,5000.00,0.00,'Kas / Bank','2026-08-15 10:39:14','2026-08-15 10:39:14'),(30,15,3,0.0000,0.00,0.00,5000.00,'Piutang Dagang','2026-08-15 10:39:14','2026-08-15 10:39:14'),(31,16,11,0.0000,0.00,58000.00,0.00,'HPP Recovery','2026-08-19 07:26:39','2026-08-19 07:26:39'),(32,16,4,0.0000,0.00,0.00,58000.00,'Persediaan Recovery','2026-08-19 07:26:39','2026-08-19 07:26:39'),(33,17,11,0.0000,0.00,29000.00,0.00,'HPP Recovery','2026-08-19 07:26:39','2026-08-19 07:26:39'),(34,17,4,0.0000,0.00,0.00,29000.00,'Persediaan Recovery','2026-08-19 07:26:39','2026-08-19 07:26:39'),(35,18,11,0.0000,0.00,29000.00,0.00,'HPP Recovery','2026-08-19 07:26:39','2026-08-19 07:26:39'),(36,18,4,0.0000,0.00,0.00,29000.00,'Persediaan Recovery','2026-08-19 07:26:39','2026-08-19 07:26:39'),(37,19,11,0.0000,0.00,9000.00,0.00,'HPP Cost Correction','2026-08-19 07:26:39','2026-08-19 07:26:39'),(38,19,4,0.0000,0.00,0.00,9000.00,'Inventory Cost Correction','2026-08-19 07:26:39','2026-08-19 07:26:39'),(39,20,11,0.0000,0.00,3600.00,0.00,'HPP Cost Correction','2026-08-19 07:26:39','2026-08-19 07:26:39'),(40,20,4,0.0000,0.00,0.00,3600.00,'Inventory Cost Correction','2026-08-19 07:26:39','2026-08-19 07:26:39'),(41,21,4,0.0000,0.00,0.00,700000.00,'Reversal - Inventory','2026-08-19 07:26:39','2026-08-19 07:26:39'),(42,21,15,0.0000,0.00,700000.00,0.00,'Reversal - GRNI','2026-08-19 07:26:39','2026-08-19 07:26:39'),(43,22,11,0.0000,0.00,0.00,10000.00,'Reversal - HPP','2026-08-19 07:26:39','2026-08-19 07:26:39'),(44,22,4,0.0000,0.00,10000.00,0.00,'Reversal - Persediaan','2026-08-19 07:26:39','2026-08-19 07:26:39'),(45,23,11,0.0000,0.00,0.00,10000.00,'Reversal - HPP','2026-08-19 07:26:39','2026-08-19 07:26:39'),(46,23,4,0.0000,0.00,10000.00,0.00,'Reversal - Persediaan','2026-08-19 07:26:39','2026-08-19 07:26:39');

#
# Structure for table "audit_logs"
#

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_module_index` (`module`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_reference_id_index` (`reference_id`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "audit_logs"
#

INSERT INTO `audit_logs` VALUES (1,NULL,'Journal','CREATE','Journal',1,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D54455354227D','127.0.0.1','Symfony','2026-08-05 07:13:18','2026-08-05 07:13:18'),(2,NULL,'Journal','CREATE','Journal',1,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D54455354227D','127.0.0.1','Symfony','2026-08-05 07:16:32','2026-08-05 07:16:32'),(3,NULL,'Purchase Request','CREATE','PurchaseRequest',1,NULL,X'7B2270725F6E6F223A202250522D32303236303830382D3030303031222C2022737461747573223A20224452414654227D','127.0.0.1','Symfony','2026-08-08 08:17:01','2026-08-08 08:17:01'),(4,NULL,'Purchase Request','APPROVE','PurchaseRequest',1,X'7B22737461747573223A20224452414654227D',X'7B22737461747573223A2022415050524F564544222C2022617070726F7665645F6279223A20317D','127.0.0.1','Symfony','2026-08-08 08:21:54','2026-08-08 08:21:54'),(5,NULL,'Purchase Order','CREATE','PurchaseOrder',1,NULL,X'7B22706F5F6E6F223A2022504F2D32303236303830382D3030303031227D','127.0.0.1','Symfony','2026-08-08 08:46:10','2026-08-08 08:46:10'),(6,NULL,'Journal','CREATE','App\\Models\\Journal',3,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831322D3030303034222C20226465736372697074696F6E223A20224175746F204A6F75726E616C20476F6F64732052656365697074222C20227265666572656E63655F6964223A20322C20227265666572656E63655F74797065223A2022474F4F44535F52454345495054227D','127.0.0.1','Symfony','2026-08-12 10:57:39','2026-08-12 10:57:39'),(7,NULL,'Goods Receipt','CREATE','GoodsReceipt',2,NULL,X'7B2267725F6E6F223A202247522D32303236303831322D3030303031227D','127.0.0.1','Symfony','2026-08-12 10:57:39','2026-08-12 10:57:39'),(8,NULL,'Journal','CREATE','App\\Models\\Journal',4,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831322D3030303035222C20226465736372697074696F6E223A20224175746F204A6F75726E616C20476F6F64732052656365697074222C20227265666572656E63655F6964223A20332C20227265666572656E63655F74797065223A2022474F4F44535F52454345495054227D','127.0.0.1','Symfony','2026-08-12 12:09:59','2026-08-12 12:09:59'),(9,NULL,'Goods Receipt','CREATE','GoodsReceipt',3,NULL,X'7B2267725F6E6F223A202247522D32303236303831322D3030303031227D','127.0.0.1','Symfony','2026-08-12 12:09:59','2026-08-12 12:09:59'),(10,NULL,'Journal','CREATE','App\\Models\\Journal',5,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831332D3030303036222C20226465736372697074696F6E223A20224175746F204A6F75726E616C20507572636861736520496E766F696365222C20227265666572656E63655F6964223A20312C20227265666572656E63655F74797065223A202250555243484153455F494E564F494345227D','127.0.0.1','Symfony','2026-08-13 04:20:27','2026-08-13 04:20:27'),(11,NULL,'Purchase Invoice','CREATE','PurchaseInvoice',1,NULL,X'7B22696E766F6963655F6E6F223A2022494E562D32303236303831332D3030303031227D','127.0.0.1','Symfony','2026-08-13 04:20:27','2026-08-13 04:20:27'),(12,NULL,'Journal','CREATE','App\\Models\\Journal',6,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831332D3030303037222C20226465736372697074696F6E223A20224175746F204A6F75726E616C20507572636861736520496E766F696365222C20227265666572656E63655F6964223A20322C20227265666572656E63655F74797065223A202250555243484153455F494E564F494345227D','127.0.0.1','Symfony','2026-08-13 04:33:00','2026-08-13 04:33:00'),(13,NULL,'Purchase Invoice','CREATE','PurchaseInvoice',2,NULL,X'7B22696E766F6963655F6E6F223A2022494E562D32303236303831332D3030303032227D','127.0.0.1','Symfony','2026-08-13 04:33:00','2026-08-13 04:33:00'),(14,NULL,'Journal','CREATE','App\\Models\\Journal',7,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831332D3030303038222C20226465736372697074696F6E223A20225061796D656E7420566F7563686572222C20227265666572656E63655F6964223A20312C20227265666572656E63655F74797065223A20225041594D454E545F564F5543484552227D','127.0.0.1','Symfony','2026-08-13 14:09:13','2026-08-13 14:09:13'),(15,NULL,'Payment Voucher','CREATE','PaymentVoucher',1,NULL,X'7B22766F75636865725F6E6F223A202250562D32303236303831332D3030303031227D','127.0.0.1','Symfony','2026-08-13 14:09:13','2026-08-13 14:09:13'),(16,NULL,'Customer','CREATE','Customer',1,NULL,X'7B22636F6465223A202243555354303031222C20226E616D65223A20224356204D69747261204A617961227D','127.0.0.1','Symfony','2026-08-13 15:08:33','2026-08-13 15:08:33'),(17,NULL,'Sales Order','CREATE','SalesOrder',1,NULL,X'7B22736F5F6E6F223A2022534F2D32303236303831342D3030303031227D','127.0.0.1','Symfony','2026-08-14 08:11:44','2026-08-14 08:11:44'),(18,NULL,'Delivery Order','CREATE','DeliveryOrder',2,NULL,X'7B22646F5F6E6F223A2022444F2D32303236303831342D3030303031227D','127.0.0.1','Symfony','2026-08-14 10:38:32','2026-08-14 10:38:32'),(19,NULL,'Delivery Order','CREATE','DeliveryOrder',3,NULL,X'7B22646F5F6E6F223A2022444F2D32303236303831342D3030303032227D','127.0.0.1','Symfony','2026-08-14 10:47:49','2026-08-14 10:47:49'),(20,NULL,'Delivery Order','CREATE','DeliveryOrder',4,NULL,X'7B22646F5F6E6F223A2022444F2D32303236303831342D3030303033227D','127.0.0.1','Symfony','2026-08-14 10:50:00','2026-08-14 10:50:00'),(21,NULL,'Journal','CREATE','App\\Models\\Journal',8,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831352D3030303039222C20226465736372697074696F6E223A20224175746F204A6F75726E616C2044656C6976657279204F72646572222C20227265666572656E63655F6964223A203939392C20227265666572656E63655F74797065223A202244454C49564552595F4F52444552227D','127.0.0.1','Symfony','2026-08-15 03:30:07','2026-08-15 03:30:07'),(22,NULL,'Journal','CREATE','App\\Models\\Journal',9,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831352D3030303130222C20226465736372697074696F6E223A20224175746F204A6F75726E616C2044656C6976657279204F72646572222C20227265666572656E63655F6964223A203939392C20227265666572656E63655F74797065223A202244454C49564552595F4F52444552227D','127.0.0.1','Symfony','2026-08-15 03:30:21','2026-08-15 03:30:21'),(23,NULL,'Journal','CREATE','App\\Models\\Journal',10,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831352D3030303131222C20226465736372697074696F6E223A20224175746F204A6F75726E616C2044656C6976657279204F72646572222C20227265666572656E63655F6964223A20352C20227265666572656E63655F74797065223A202244454C49564552595F4F52444552227D','127.0.0.1','Symfony','2026-08-15 03:44:13','2026-08-15 03:44:13'),(24,NULL,'Delivery Order','CREATE','DeliveryOrder',5,NULL,X'7B22646F5F6E6F223A2022444F2D32303236303831352D3030303034227D','127.0.0.1','Symfony','2026-08-15 03:44:13','2026-08-15 03:44:13'),(25,NULL,'Journal','CREATE','App\\Models\\Journal',11,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831352D3030303132222C20226465736372697074696F6E223A20224175746F204A6F75726E616C2044656C6976657279204F72646572222C20227265666572656E63655F6964223A20362C20227265666572656E63655F74797065223A202244454C49564552595F4F52444552227D','127.0.0.1','Symfony','2026-08-15 04:23:58','2026-08-15 04:23:58'),(26,NULL,'Delivery Order','CREATE','DeliveryOrder',6,NULL,X'7B22646F5F6E6F223A2022444F2D32303236303831352D3030303035227D','127.0.0.1','Symfony','2026-08-15 04:23:58','2026-08-15 04:23:58'),(27,NULL,'Journal','CREATE','App\\Models\\Journal',12,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831352D3030303133222C20226465736372697074696F6E223A20224175746F204A6F75726E616C2053616C657320496E766F696365222C20227265666572656E63655F6964223A20312C20227265666572656E63655F74797065223A202253414C45535F494E564F494345227D','127.0.0.1','Symfony','2026-08-15 09:16:31','2026-08-15 09:16:31'),(28,NULL,'Sales Invoice','CREATE','SalesInvoice',1,NULL,X'7B22696E766F6963655F6E6F223A2022494E562D32303236303831352D3030303033227D','127.0.0.1','Symfony','2026-08-15 09:16:31','2026-08-15 09:16:31'),(29,NULL,'Journal','CREATE','App\\Models\\Journal',13,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831352D3030303134222C20226465736372697074696F6E223A20224175746F204A6F75726E616C2053616C657320496E766F696365222C20227265666572656E63655F6964223A20322C20227265666572656E63655F74797065223A202253414C45535F494E564F494345227D','127.0.0.1','Symfony','2026-08-15 09:49:27','2026-08-15 09:49:27'),(30,NULL,'Journal','CREATE','App\\Models\\Journal',14,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831352D3030303135222C20226465736372697074696F6E223A20224175746F204A6F75726E616C2053616C657320496E766F696365222C20227265666572656E63655F6964223A20322C20227265666572656E63655F74797065223A202253414C45535F494E564F494345227D','127.0.0.1','Symfony','2026-08-15 09:49:27','2026-08-15 09:49:27'),(31,NULL,'Sales Invoice','CREATE','SalesInvoice',2,NULL,X'7B22696E766F6963655F6E6F223A2022494E562D32303236303831352D3030303034227D','127.0.0.1','Symfony','2026-08-15 09:49:27','2026-08-15 09:49:27'),(32,NULL,'Journal','CREATE','App\\Models\\Journal',15,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831352D3030303136222C20226465736372697074696F6E223A2022437573746F6D65722052656365697074222C20227265666572656E63655F6964223A20312C20227265666572656E63655F74797065223A2022435553544F4D45525F52454345495054227D','127.0.0.1','Symfony','2026-08-15 10:39:14','2026-08-15 10:39:14'),(33,NULL,'Customer Receipt','CREATE','CustomerReceipt',1,NULL,X'7B22726563656970745F6E6F223A202243522D32303236303831352D3030303031227D','127.0.0.1','Symfony','2026-08-15 10:39:14','2026-08-15 10:39:14'),(34,NULL,'Journal','CREATE','App\\Models\\Journal',16,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831392D3030303137222C20226465736372697074696F6E223A20225265636F76657279206D697373696E67206A6F75726E616C2044656C6976657279204F72646572222C20227265666572656E63655F6964223A20322C20227265666572656E63655F74797065223A202244454C49564552595F4F52444552222C20226A6F75726E616C5F707572706F7365223A20225245434F56455259222C2022736F757263655F6A6F75726E616C5F6964223A206E756C6C2C20227265636F6E63696C696174696F6E5F6B6579223A2022494E565245433A5245434F5645525F4D495353494E475F4A4F55524E414C3A44454C49564552595F4F524445523A323A4954454D3A313A57483A31227D','127.0.0.1','Symfony','2026-08-19 07:26:39','2026-08-19 07:26:39'),(35,NULL,'Journal','CREATE','App\\Models\\Journal',17,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831392D3030303138222C20226465736372697074696F6E223A20225265636F76657279206D697373696E67206A6F75726E616C2044656C6976657279204F72646572222C20227265666572656E63655F6964223A20332C20227265666572656E63655F74797065223A202244454C49564552595F4F52444552222C20226A6F75726E616C5F707572706F7365223A20225245434F56455259222C2022736F757263655F6A6F75726E616C5F6964223A206E756C6C2C20227265636F6E63696C696174696F6E5F6B6579223A2022494E565245433A5245434F5645525F4D495353494E475F4A4F55524E414C3A44454C49564552595F4F524445523A333A4954454D3A313A57483A31227D','127.0.0.1','Symfony','2026-08-19 07:26:39','2026-08-19 07:26:39'),(36,NULL,'Journal','CREATE','App\\Models\\Journal',18,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831392D3030303139222C20226465736372697074696F6E223A20225265636F76657279206D697373696E67206A6F75726E616C2044656C6976657279204F72646572222C20227265666572656E63655F6964223A20342C20227265666572656E63655F74797065223A202244454C49564552595F4F52444552222C20226A6F75726E616C5F707572706F7365223A20225245434F56455259222C2022736F757263655F6A6F75726E616C5F6964223A206E756C6C2C20227265636F6E63696C696174696F6E5F6B6579223A2022494E565245433A5245434F5645525F4D495353494E475F4A4F55524E414C3A44454C49564552595F4F524445523A343A4954454D3A313A57483A31227D','127.0.0.1','Symfony','2026-08-19 07:26:39','2026-08-19 07:26:39'),(37,NULL,'Journal','CREATE','App\\Models\\Journal',19,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831392D3030303230222C20226465736372697074696F6E223A2022436F7272656374696F6E20696E76656E746F727920636F7374696E67206D69736D61746368222C20227265666572656E63655F6964223A20352C20227265666572656E63655F74797065223A202244454C49564552595F4F52444552222C20226A6F75726E616C5F707572706F7365223A2022434F53545F434F5252454354494F4E222C2022736F757263655F6A6F75726E616C5F6964223A206E756C6C2C20227265636F6E63696C696174696F6E5F6B6579223A2022494E565245433A434F52524543545F434F53545F4D49534D415443483A44454C49564552595F4F524445523A353A4954454D3A313A57483A31227D','127.0.0.1','Symfony','2026-08-19 07:26:39','2026-08-19 07:26:39'),(38,NULL,'Journal','CREATE','App\\Models\\Journal',20,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831392D3030303231222C20226465736372697074696F6E223A2022436F7272656374696F6E20696E76656E746F727920636F7374696E67206D69736D61746368222C20227265666572656E63655F6964223A20362C20227265666572656E63655F74797065223A202244454C49564552595F4F52444552222C20226A6F75726E616C5F707572706F7365223A2022434F53545F434F5252454354494F4E222C2022736F757263655F6A6F75726E616C5F6964223A206E756C6C2C20227265636F6E63696C696174696F6E5F6B6579223A2022494E565245433A434F52524543545F434F53545F4D49534D415443483A44454C49564552595F4F524445523A363A4954454D3A313A57483A31227D','127.0.0.1','Symfony','2026-08-19 07:26:39','2026-08-19 07:26:39'),(39,NULL,'Journal','CREATE','App\\Models\\Journal',21,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831392D3030303232222C20226465736372697074696F6E223A2022526576657273616C206F727068616E206A6F75726E616C204A562D32303236303831322D3030303034222C20227265666572656E63655F6964223A20322C20227265666572656E63655F74797065223A2022474F4F44535F52454345495054222C20226A6F75726E616C5F707572706F7365223A2022524556455253414C222C2022736F757263655F6A6F75726E616C5F6964223A20332C20227265636F6E63696C696174696F6E5F6B6579223A2022494E565245433A524556455253414C3A4A4F55524E414C3A33227D','127.0.0.1','Symfony','2026-08-19 07:26:39','2026-08-19 07:26:39'),(40,NULL,'Journal','CREATE','App\\Models\\Journal',22,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831392D3030303233222C20226465736372697074696F6E223A2022526576657273616C206F727068616E206A6F75726E616C204A562D32303236303831352D3030303039222C20227265666572656E63655F6964223A203939392C20227265666572656E63655F74797065223A202244454C49564552595F4F52444552222C20226A6F75726E616C5F707572706F7365223A2022524556455253414C222C2022736F757263655F6A6F75726E616C5F6964223A20382C20227265636F6E63696C696174696F6E5F6B6579223A2022494E565245433A524556455253414C3A4A4F55524E414C3A38227D','127.0.0.1','Symfony','2026-08-19 07:26:39','2026-08-19 07:26:39'),(41,NULL,'Journal','CREATE','App\\Models\\Journal',23,NULL,X'7B226A6F75726E616C5F6E6F223A20224A562D32303236303831392D3030303234222C20226465736372697074696F6E223A2022526576657273616C206F727068616E206A6F75726E616C204A562D32303236303831352D3030303130222C20227265666572656E63655F6964223A203939392C20227265666572656E63655F74797065223A202244454C49564552595F4F52444552222C20226A6F75726E616C5F707572706F7365223A2022524556455253414C222C2022736F757263655F6A6F75726E616C5F6964223A20392C20227265636F6E63696C696174696F6E5F6B6579223A2022494E565245433A524556455253414C3A4A4F55524E414C3A39227D','127.0.0.1','Symfony','2026-08-19 07:26:39','2026-08-19 07:26:39');

#
# Structure for table "warehouses"
#

DROP TABLE IF EXISTS `warehouses`;
CREATE TABLE `warehouses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouses_code_unique` (`code`),
  KEY `warehouses_company_id_foreign` (`company_id`),
  KEY `warehouses_branch_id_foreign` (`branch_id`),
  CONSTRAINT `warehouses_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warehouses_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "warehouses"
#

INSERT INTO `warehouses` VALUES (1,1,1,'WH-001','Gudang Utama KSM',NULL,NULL,NULL,1,'2026-08-04 08:11:56','2026-08-04 08:11:56',NULL);

#
# Structure for table "purchase_requests"
#

DROP TABLE IF EXISTS `purchase_requests`;
CREATE TABLE `purchase_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pr_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pr_date` date NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `status` enum('DRAFT','APPROVED','REJECTED','CLOSED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_requests_pr_no_unique` (`pr_no`),
  KEY `purchase_requests_warehouse_id_foreign` (`warehouse_id`),
  KEY `purchase_requests_created_by_foreign` (`created_by`),
  KEY `purchase_requests_pr_no_index` (`pr_no`),
  KEY `purchase_requests_status_index` (`status`),
  CONSTRAINT `purchase_requests_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "purchase_requests"
#

INSERT INTO `purchase_requests` VALUES (1,'PR-20260808-00001','2026-08-08',1,'Pembelian Pakan','APPROVED',1,'2026-08-08 08:17:01','2026-08-08 08:21:54');

#
# Structure for table "purchase_orders"
#

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `po_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_request_id` bigint unsigned DEFAULT NULL,
  `po_date` date NOT NULL,
  `supplier_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `status` enum('DRAFT','APPROVED','PARTIAL','COMPLETED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_orders_po_no_unique` (`po_no`),
  KEY `purchase_orders_purchase_request_id_foreign` (`purchase_request_id`),
  KEY `purchase_orders_created_by_foreign` (`created_by`),
  KEY `purchase_orders_po_no_index` (`po_no`),
  KEY `purchase_orders_status_index` (`status`),
  CONSTRAINT `purchase_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "purchase_orders"
#

INSERT INTO `purchase_orders` VALUES (1,'PO-20260808-00001',NULL,'2026-08-08','PT Pakan Jaya','PO Manual','DRAFT',1,'2026-08-08 08:46:10','2026-08-08 08:46:10');

#
# Structure for table "goods_receipts"
#

DROP TABLE IF EXISTS `goods_receipts`;
CREATE TABLE `goods_receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `gr_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_order_id` bigint unsigned NOT NULL,
  `receipt_date` date NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POSTED',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `goods_receipts_gr_no_unique` (`gr_no`),
  KEY `goods_receipts_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `goods_receipts_created_by_foreign` (`created_by`),
  KEY `goods_receipts_gr_no_index` (`gr_no`),
  CONSTRAINT `goods_receipts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `goods_receipts_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "goods_receipts"
#

INSERT INTO `goods_receipts` VALUES (3,'GR-20260812-00001',1,'2026-08-12','POSTED','Barang diterima lengkap',1,'2026-08-12 12:09:59','2026-08-12 12:09:59');

#
# Structure for table "purchase_invoices"
#

DROP TABLE IF EXISTS `purchase_invoices`;
CREATE TABLE `purchase_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_date` date NOT NULL,
  `goods_receipt_id` bigint unsigned NOT NULL,
  `supplier_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_invoice_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POSTED',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_invoices_invoice_no_unique` (`invoice_no`),
  KEY `purchase_invoices_goods_receipt_id_foreign` (`goods_receipt_id`),
  KEY `purchase_invoices_created_by_foreign` (`created_by`),
  CONSTRAINT `purchase_invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_invoices_goods_receipt_id_foreign` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "purchase_invoices"
#

INSERT INTO `purchase_invoices` VALUES (2,'INV-20260813-00002','2026-08-13',3,'PT Pakan Jaya','SJ-001',700000.00,0.00,700000.00,'OPEN',1,'2026-08-13 04:32:59','2026-08-13 04:32:59',NULL);

#
# Structure for table "purchase_invoice_details"
#

DROP TABLE IF EXISTS `purchase_invoice_details`;
CREATE TABLE `purchase_invoice_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_invoice_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_invoice_details_purchase_invoice_id_foreign` (`purchase_invoice_id`),
  KEY `purchase_invoice_details_item_id_foreign` (`item_id`),
  CONSTRAINT `purchase_invoice_details_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_invoice_details_purchase_invoice_id_foreign` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "purchase_invoice_details"
#

INSERT INTO `purchase_invoice_details` VALUES (2,2,1,100.0000,7000.00,700000.00,NULL,'2026-08-13 04:32:59','2026-08-13 04:32:59');

#
# Structure for table "purchase_order_details"
#

DROP TABLE IF EXISTS `purchase_order_details`;
CREATE TABLE `purchase_order_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `received_qty` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_details_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `purchase_order_details_item_id_foreign` (`item_id`),
  CONSTRAINT `purchase_order_details_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_order_details_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "purchase_order_details"
#

INSERT INTO `purchase_order_details` VALUES (1,1,1,100.0000,7000.00,100.0000,'Pakan Starter','2026-08-08 08:46:10','2026-08-12 12:09:59');

#
# Structure for table "goods_receipt_details"
#

DROP TABLE IF EXISTS `goods_receipt_details`;
CREATE TABLE `goods_receipt_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `goods_receipt_id` bigint unsigned NOT NULL,
  `purchase_order_detail_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `qty_received` decimal(18,4) NOT NULL,
  `unit_cost` decimal(18,2) NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `goods_receipt_details_goods_receipt_id_foreign` (`goods_receipt_id`),
  KEY `goods_receipt_details_purchase_order_detail_id_foreign` (`purchase_order_detail_id`),
  KEY `goods_receipt_details_item_id_foreign` (`item_id`),
  CONSTRAINT `goods_receipt_details_goods_receipt_id_foreign` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goods_receipt_details_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goods_receipt_details_purchase_order_detail_id_foreign` FOREIGN KEY (`purchase_order_detail_id`) REFERENCES `purchase_order_details` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "goods_receipt_details"
#

INSERT INTO `goods_receipt_details` VALUES (1,3,1,1,100.0000,7000.00,'Pakan Starter','2026-08-12 12:09:59','2026-08-12 12:09:59');

#
# Structure for table "purchase_request_details"
#

DROP TABLE IF EXISTS `purchase_request_details`;
CREATE TABLE `purchase_request_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_request_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_request_details_purchase_request_id_foreign` (`purchase_request_id`),
  KEY `purchase_request_details_item_id_foreign` (`item_id`),
  CONSTRAINT `purchase_request_details_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_request_details_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "purchase_request_details"
#

INSERT INTO `purchase_request_details` VALUES (1,1,1,50.0000,'Pakan Starter','2026-08-08 08:17:01','2026-08-08 08:17:01');

#
# Structure for table "stock_ledgers"
#

DROP TABLE IF EXISTS `stock_ledgers`;
CREATE TABLE `stock_ledgers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `reference_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_id` bigint unsigned NOT NULL,
  `qty_in` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `qty_out` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `balance_qty` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `unit_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total_cost` decimal(18,2) NOT NULL DEFAULT '0.00',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_ledgers_item_id_foreign` (`item_id`),
  KEY `stock_ledgers_warehouse_id_item_id_index` (`warehouse_id`,`item_id`),
  KEY `stock_ledgers_transaction_date_index` (`transaction_date`),
  CONSTRAINT `stock_ledgers_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_ledgers_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "stock_ledgers"
#

INSERT INTO `stock_ledgers` VALUES (2,1,1,'2026-08-08','OPENING',1,100.0000,0.0000,100.0000,5000.00,500000.00,'Saldo Awal','2026-08-08 06:41:25','2026-08-08 06:41:25'),(3,1,1,'2026-08-08','OPENING',1,100.0000,0.0000,200.0000,5000.00,1000000.00,'Saldo Awal','2026-08-08 06:45:30','2026-08-08 06:45:30'),(4,1,1,'2026-08-08','SALE',1,0.0000,50.0000,150.0000,5000.00,750000.00,'Penjualan','2026-08-08 07:45:41','2026-08-08 07:45:41'),(6,1,1,'2026-08-12','GOODS_RECEIPT',3,100.0000,0.0000,250.0000,7000.00,1750000.00,'Goods Receipt','2026-08-12 12:09:59','2026-08-12 12:09:59'),(7,1,1,'2026-08-14','DELIVERY_ORDER',2,0.0000,10.0000,240.0000,0.00,0.00,'Delivery Order','2026-08-14 10:38:32','2026-08-14 10:38:32'),(8,1,1,'2026-08-14','DELIVERY_ORDER',3,0.0000,5.0000,235.0000,0.00,0.00,'Delivery Order','2026-08-14 10:47:49','2026-08-14 10:47:49'),(9,1,1,'2026-08-14','DELIVERY_ORDER',4,0.0000,5.0000,230.0000,0.00,0.00,'Delivery Order','2026-08-14 10:50:00','2026-08-14 10:50:00'),(10,1,1,'2026-08-15','DELIVERY_ORDER',5,0.0000,5.0000,225.0000,0.00,0.00,'Delivery Order','2026-08-15 03:44:12','2026-08-15 03:44:12'),(11,1,1,'2026-08-15','DELIVERY_ORDER',6,0.0000,2.0000,223.0000,4000.00,8000.00,'Delivery Order','2026-08-15 04:23:58','2026-08-15 04:23:58'),(12,1,1,'2026-08-18','COSTING_TEST_IN',1,1.0000,0.0000,224.0000,7500.00,7500.00,'Test Moving Average','2026-08-18 07:18:15','2026-08-18 07:18:15'),(13,1,1,'2026-08-18','COSTING_TEST_IN',1,1.0000,0.0000,225.0000,7500.00,7500.00,'Test Moving Average','2026-08-18 07:23:01','2026-08-18 07:23:01'),(14,1,1,'2026-08-18','COSTING_TEST_OUT',1,0.0000,1.0000,224.0000,5815.11,5815.11,'Test Moving Average OUT','2026-08-18 07:25:04','2026-08-18 07:25:04');
