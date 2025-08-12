-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 11 أغسطس 2025 الساعة 15:51
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `shipping`
--

DELIMITER $$
--
-- الإجراءات
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `AddCustomerTransaction` (IN `p_customer_id` INT, IN `p_type` ENUM('credit','debit'), IN `p_amount` DECIMAL(12,2), IN `p_description` TEXT, IN `p_reference_type` VARCHAR(50), IN `p_reference_id` INT, IN `p_created_by` VARCHAR(100))   BEGIN
    DECLARE current_balance DECIMAL(12,2) DEFAULT 0;
    DECLARE new_balance DECIMAL(12,2) DEFAULT 0;
    
    -- جلب الرصيد الحالي
    SELECT IFNULL(balance, 0) INTO current_balance
    FROM customers WHERE id = p_customer_id;
    
    -- حساب الرصيد الجديد
    IF p_type = 'credit' THEN
        SET new_balance = current_balance + p_amount;
    ELSE
        SET new_balance = current_balance - p_amount;
    END IF;
    
    -- إدراج الحركة المالية
    INSERT INTO customer_balances (
        customer_id, type, amount, description, 
        balance_before, balance_after, 
        reference_type, reference_id, created_by
    ) VALUES (
        p_customer_id, p_type, p_amount, p_description,
        current_balance, new_balance,
        p_reference_type, p_reference_id, p_created_by
    );
    
    -- تحديث رصيد العميل
    UPDATE customers SET balance = new_balance WHERE id = p_customer_id;
    
    -- إدراج سجل في كشف الحساب
    INSERT INTO customer_statements (
        customer_id, transaction_type, amount,
        balance_before, balance_after, description,
        reference_type, reference_id, transaction_date, created_by
    ) VALUES (
        p_customer_id, p_type, p_amount,
        current_balance, new_balance, p_description,
        p_reference_type, p_reference_id, NOW(), p_created_by
    );
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CalculateCustomerBalance` (IN `customer_id` INT)   BEGIN
    DECLARE total_credit DECIMAL(12,2) DEFAULT 0;
    DECLARE total_debit DECIMAL(12,2) DEFAULT 0;
    DECLARE current_balance DECIMAL(12,2) DEFAULT 0;
    
    -- حساب إجمالي الائتمان (المبالغ المستحقة للعميل)
    SELECT IFNULL(SUM(amount), 0) INTO total_credit
    FROM customer_balances 
    WHERE customer_id = customer_id AND type = 'credit';
    
    -- حساب إجمالي الخصم (المبالغ المدفوعة من العميل)
    SELECT IFNULL(SUM(amount), 0) INTO total_debit
    FROM customer_balances 
    WHERE customer_id = customer_id AND type = 'debit';
    
    -- حساب الرصيد الحالي
    SET current_balance = total_credit - total_debit;
    
    -- تحديث رصيد العميل
    UPDATE customers 
    SET balance = current_balance,
        total_due = CASE WHEN current_balance < 0 THEN ABS(current_balance) ELSE 0 END
    WHERE id = customer_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CalculateParcelBalance` (IN `parcel_id` INT)   BEGIN
    DECLARE v_delivered_value DECIMAL(12,2) DEFAULT 0;
    DECLARE v_amount_paid DECIMAL(12,2) DEFAULT 0;
    DECLARE v_returned_value DECIMAL(12,2) DEFAULT 0;
    DECLARE v_remaining_balance DECIMAL(12,2) DEFAULT 0;
    DECLARE v_payment_status VARCHAR(20) DEFAULT 'unpaid';
    
    -- جلب القيم الحالية
    SELECT 
        IFNULL(delivered_value, 0),
        IFNULL(amount_paid_so_far, 0),
        IFNULL(returned_value, 0)
    INTO v_delivered_value, v_amount_paid, v_returned_value
    FROM parcels 
    WHERE id = parcel_id;
    
    -- حساب الرصيد المتبقي (الصيغة: المسلم - المدفوع)
    -- ملاحظة: المرتجعات لا تدخل في حساب الرصيد المتبقي
    SET v_remaining_balance = v_delivered_value - v_amount_paid;
    
    -- تحديد حالة الدفع
    IF v_amount_paid = 0 THEN
        SET v_payment_status = 'unpaid';
    ELSEIF v_remaining_balance <= 0 THEN
        SET v_payment_status = 'fully_paid';
    ELSE
        SET v_payment_status = 'partially_paid';
    END IF;
    
    -- تحديث الشحنة
    UPDATE parcels SET 
        remaining_balance = v_remaining_balance,
        detailed_payment_status = v_payment_status,
        updated_at = NOW()
    WHERE id = parcel_id;
    
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `get_party_balance` (`party_type_param` VARCHAR(20), `party_id_param` INT) RETURNS DECIMAL(12,2) DETERMINISTIC READS SQL DATA BEGIN
  DECLARE balance DECIMAL(12,2) DEFAULT 0.00;
  
  SELECT IFNULL(SUM(IF(direction='in', amount, -amount)), 0) 
  INTO balance
  FROM cashbox_transactions 
  WHERE type = party_type_param AND relation_id = party_id_param;
  
  RETURN balance;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- بنية الجدول `agents`
--

CREATE TABLE `agents` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `geo_area` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `coop_type` enum('to_them','from_them','exchange') DEFAULT 'exchange',
  `commission_type` enum('percent','fixed') DEFAULT 'fixed',
  `commission_value` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `agents`
--

INSERT INTO `agents` (`id`, `company_name`, `contact_person`, `geo_area`, `phone`, `email`, `address`, `coop_type`, `commission_type`, `commission_value`, `status`) VALUES
(1, 'الامل', 'يوسف', 'القاهرة المنصورة الصعيد ', '01012345678', 'shabanmohamed1455@mail.com', 'المنصورة', 'exchange', 'fixed', 1.00, 1);

-- --------------------------------------------------------

--
-- بنية الجدول `agent_payments`
--

CREATE TABLE `agent_payments` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `agent_transactions`
--

CREATE TABLE `agent_transactions` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `shipment_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `direction` enum('delivered','received') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `agent_users`
--

CREATE TABLE `agent_users` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `agent_users`
--

INSERT INTO `agent_users` (`id`, `agent_id`, `username`, `password`, `status`, `created_at`, `last_login`) VALUES
(1, 1, 'elamal', '$2y$10$g.JR74dwObxVXupJ6YxaPef/z4JKXoccbJhGPWxRXPU4apRAnapRy', 'active', '2025-08-10 22:37:21', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `app_settings`
--

CREATE TABLE `app_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `app_settings`
--

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('commission_cut_type', 'fixed', '2025-08-11 02:45:39'),
('commission_cut_value', '0', '2025-08-11 02:45:39');

-- --------------------------------------------------------

--
-- بنية الجدول `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `governorate_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `areas`
--

INSERT INTO `areas` (`id`, `name`, `price`, `governorate_id`) VALUES
(1, 'دمياط الجديدة', 35.00, 2),
(2, 'أجا', 0.00, 3);

-- --------------------------------------------------------

--
-- بنية الجدول `backups`
--

CREATE TABLE `backups` (
  `id` int(11) NOT NULL,
  `backup_name` varchar(255) NOT NULL,
  `backup_type` enum('manual','automatic','scheduled') DEFAULT 'manual',
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `database_name` varchar(100) DEFAULT NULL,
  `tables_count` int(11) DEFAULT 0,
  `records_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('success','failed','in_progress') DEFAULT 'in_progress',
  `error_message` text DEFAULT NULL,
  `compression` enum('none','gzip','zip') DEFAULT 'gzip',
  `backup_hash` varchar(64) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `branches`
--

CREATE TABLE `branches` (
  `id` int(30) NOT NULL,
  `branch_code` varchar(50) NOT NULL,
  `street` text NOT NULL,
  `city` text NOT NULL,
  `state` text NOT NULL,
  `zip_code` varchar(50) NOT NULL,
  `country` text NOT NULL,
  `contact` varchar(100) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `is_main_branch` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `branches`
--

INSERT INTO `branches` (`id`, `branch_code`, `street`, `city`, `state`, `zip_code`, `country`, `contact`, `date_created`, `is_main_branch`) VALUES
(1, '6y4L0hvC9j1x5PE', 'شيشسب', 'بسشبسش', 'شسبسشبش', '124021', 'سئبشسل', '5354', '2025-08-10 22:51:10', 0);

-- --------------------------------------------------------

--
-- بنية الجدول `branch_cashbox_transactions`
--

CREATE TABLE `branch_cashbox_transactions` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `transaction_type` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'EGP',
  `related_parcel_id` int(11) DEFAULT NULL,
  `related_customer_id` int(11) DEFAULT NULL,
  `related_agent_id` int(11) DEFAULT NULL,
  `related_courier_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `cashbox_logs`
--

CREATE TABLE `cashbox_logs` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `cashbox_transactions`
--

CREATE TABLE `cashbox_transactions` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `type` enum('customer','agent') NOT NULL,
  `relation_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `direction` enum('out','in') NOT NULL DEFAULT 'out',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `couriers`
--

CREATE TABLE `couriers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `branch_id` int(11) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `governorate_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `commission_type` enum('per_delivery','percent','fixed') NOT NULL DEFAULT 'per_delivery',
  `commission_value` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `couriers`
--

INSERT INTO `couriers` (`id`, `name`, `phone`, `email`, `password`, `last_login`, `failed_attempts`, `branch_id`, `status`, `created_at`, `governorate_id`, `area_id`, `commission_type`, `commission_value`) VALUES
(1, 'شعبان', '01030552995', 'shabanmohamed1455@gmail.com', '$2y$10$F9JOcCL7bbl6YpQcKG.B8unguxdU043Omo4ImDGfDOTSFuegMH8la', NULL, 2, 1, 1, '2025-08-10 23:13:21', 2, 1, 'per_delivery', 0.00),
(2, 'Mohamed Dawoud', '01000613138', 'admin@example.com', '$2y$10$GMYYmARpdlnLGLF0yJEe8.OS3gX8ice7qah6XDaHhoBxWnzIi0Utu', NULL, 0, 1, 1, '2025-08-10 23:21:41', 2, 1, 'per_delivery', 0.00),
(3, 'محمد جلال', '01012345678', 'shabanmohamed145501@gmail.com', '$2y$10$KggaNNsRXiV7ExNz9qWX5eisBsShOfG2CzJdBYFXeWe0r8yY4L37e', NULL, 0, 1, 1, '2025-08-10 23:32:51', 2, 1, 'per_delivery', 0.00),
(4, 'احمد ريف', '01012365478', 'md01064875226@gmail.com', '$2y$10$o2RC8KAiW2MRcWg5TMOt3.rXISw5ZXvWJDNAL7DXiEd6dwUgYpeHO', '2025-08-11 15:51:48', 0, 1, 1, '2025-08-10 23:58:04', 2, 1, 'per_delivery', 0.00);

-- --------------------------------------------------------

--
-- بنية الجدول `courier_areas`
--

CREATE TABLE `courier_areas` (
  `courier_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `courier_areas`
--

INSERT INTO `courier_areas` (`courier_id`, `area_id`) VALUES
(1, 0);

-- --------------------------------------------------------

--
-- بنية الجدول `courier_auth_tokens`
--

CREATE TABLE `courier_auth_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `courier_id` int(11) NOT NULL,
  `token_hash` varbinary(64) NOT NULL,
  `user_agent_hash` varbinary(64) DEFAULT NULL,
  `ip_hash` varbinary(64) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `courier_auth_tokens`
--

INSERT INTO `courier_auth_tokens` (`id`, `courier_id`, `token_hash`, `user_agent_hash`, `ip_hash`, `expires_at`, `created_at`, `revoked_at`) VALUES
(1, 4, 0xb6cf4708fe3dc2addf15c6d74c7a9d1ea7887395874162d6ba21be0a3ee96f4c, 0xf89af39a2aaa441315bce3e908d5eb85efd0ddfdc278745ec42c4f89890d6c4a, 0xeff8e7ca506627fe15dda5e0e512fcaad70b6d520f37cc76597fdb4f2d83a1a3, '2025-09-09 23:16:43', '2025-08-11 00:16:43', '2025-08-11 00:18:41'),
(2, 4, 0x49ca4bd98823300dcaaa458065a8a6db31be9a77e6c17d4013773abbe0b9483d, 0xf89af39a2aaa441315bce3e908d5eb85efd0ddfdc278745ec42c4f89890d6c4a, 0xeff8e7ca506627fe15dda5e0e512fcaad70b6d520f37cc76597fdb4f2d83a1a3, '2025-09-09 23:19:51', '2025-08-11 00:19:51', '2025-08-11 01:24:03'),
(3, 4, 0x2071eda6d6becce1a31b54ae708df7ceac433f822edb6e3828945926b04948dc, 0xf89af39a2aaa441315bce3e908d5eb85efd0ddfdc278745ec42c4f89890d6c4a, 0xeff8e7ca506627fe15dda5e0e512fcaad70b6d520f37cc76597fdb4f2d83a1a3, '2025-09-10 00:24:14', '2025-08-11 01:24:14', '2025-08-11 01:24:19'),
(4, 4, 0x933c19050a096a994a822650f6b4f75f219fec68248145e820e3043585f6c8fe, 0xf89af39a2aaa441315bce3e908d5eb85efd0ddfdc278745ec42c4f89890d6c4a, 0xeff8e7ca506627fe15dda5e0e512fcaad70b6d520f37cc76597fdb4f2d83a1a3, '2025-09-10 00:30:56', '2025-08-11 01:30:56', '2025-08-11 01:35:07'),
(5, 4, 0x7e7cea0f86ceb033e527c856c4650f7fd5d4e7053c84520cc39a5c73967fd3af, 0xf89af39a2aaa441315bce3e908d5eb85efd0ddfdc278745ec42c4f89890d6c4a, 0xeff8e7ca506627fe15dda5e0e512fcaad70b6d520f37cc76597fdb4f2d83a1a3, '2025-09-10 00:35:10', '2025-08-11 01:35:10', '2025-08-11 02:19:41'),
(6, 4, 0x8f2206fb5743f253daf3d28ccc6e2bcca14854eba959f36c198e8c9d90117cea, 0x49381fa74d7cee4ad8b56a985c76334ff341dd9369a52e39d8c91cb25466d55b, 0xeff8e7ca506627fe15dda5e0e512fcaad70b6d520f37cc76597fdb4f2d83a1a3, '2025-09-10 04:39:09', '2025-08-11 05:39:09', '2025-08-11 06:45:24'),
(7, 4, 0x8316c35d6a0b71542ce288a6ef5bc971dcaad617b4187748c62e0d121f78e877, 0x49381fa74d7cee4ad8b56a985c76334ff341dd9369a52e39d8c91cb25466d55b, 0xeff8e7ca506627fe15dda5e0e512fcaad70b6d520f37cc76597fdb4f2d83a1a3, '2025-09-10 06:52:15', '2025-08-11 07:52:15', '2025-08-11 14:40:09');

-- --------------------------------------------------------

--
-- بنية الجدول `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `date_created` datetime DEFAULT current_timestamp(),
  `governorate_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `client_phone` varchar(255) DEFAULT NULL,
  `client_governorate` varchar(255) DEFAULT NULL,
  `client_zone` varchar(255) DEFAULT NULL,
  `client_address` text DEFAULT NULL,
  `balance` decimal(12,2) DEFAULT 0.00 COMMENT 'رصيد العميل الحالي',
  `credit_limit` decimal(12,2) DEFAULT 0.00 COMMENT 'حد الائتمان',
  `customer_type` enum('individual','business','corporate') DEFAULT 'individual' COMMENT 'نوع العميل',
  `total_due` decimal(12,2) DEFAULT 0.00 COMMENT 'إجمالي المستحقات',
  `last_payment_date` datetime DEFAULT NULL COMMENT 'تاريخ آخر دفعة',
  `account_manager_id` int(11) DEFAULT NULL COMMENT 'مدير الحساب',
  `payment_terms` int(11) DEFAULT 30 COMMENT 'شروط الدفع بالأيام',
  `discount_rate` decimal(5,2) DEFAULT 0.00 COMMENT 'معدل الخصم %'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `customer_accounts`
--

CREATE TABLE `customer_accounts` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `balance` decimal(12,2) DEFAULT 0.00,
  `credit_limit` decimal(12,2) DEFAULT 0.00,
  `last_transaction_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','suspended','closed') DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `customer_activity_log`
--

CREATE TABLE `customer_activity_log` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `activity_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'القيم القديمة' CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'القيم الجديدة' CHECK (json_valid(`new_values`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `customer_balances`
--

CREATE TABLE `customer_balances` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `type` enum('credit','debit') NOT NULL COMMENT 'نوع الحركة المالية',
  `amount` decimal(12,2) NOT NULL COMMENT 'المبلغ',
  `description` text DEFAULT NULL COMMENT 'وصف الحركة',
  `transaction_id` int(11) DEFAULT NULL COMMENT 'ربط مع المعاملة',
  `balance_before` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'الرصيد قبل الحركة',
  `balance_after` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'الرصيد بعد الحركة',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'نوع المرجع (parcel, payment, adjustment, commission)',
  `reference_id` int(11) DEFAULT NULL COMMENT 'معرف المرجع',
  `status` enum('pending','completed','cancelled') DEFAULT 'completed' COMMENT 'حالة الحركة',
  `created_by` varchar(100) DEFAULT NULL COMMENT 'المستخدم الذي أنشأ الحركة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول أرصدة العملاء والحركات المالية';

-- --------------------------------------------------------

--
-- Stand-in structure for view `customer_due_shipments`
-- (See below for the actual view)
--
CREATE TABLE `customer_due_shipments` (
`parcel_id` int(30)
,`tracking_number` varchar(255)
,`customer_id` int(11)
,`customer_name` varchar(255)
,`customer_phone` varchar(50)
,`cod_amount` decimal(10,2)
,`paid_amount` decimal(10,2)
,`due_amount` decimal(11,2)
,`shipment_date` datetime
,`days_overdue` int(7)
,`status_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `customer_financial_summary`
-- (See below for the actual view)
--
CREATE TABLE `customer_financial_summary` (
`id` int(11)
,`name` varchar(255)
,`phone` varchar(50)
,`email` varchar(255)
,`balance` decimal(12,2)
,`credit_limit` decimal(12,2)
,`total_due` decimal(12,2)
,`last_payment_date` datetime
,`total_parcels` bigint(21)
,`delivered_parcels` decimal(22,0)
,`total_cod_amount` decimal(32,2)
,`total_paid_amount` decimal(32,2)
,`pending_amount` decimal(32,2)
,`customer_rating` decimal(2,1)
,`account_status` varchar(10)
);

-- --------------------------------------------------------

--
-- بنية الجدول `customer_logs`
--

CREATE TABLE `customer_logs` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `customer_monthly_reports`
--

CREATE TABLE `customer_monthly_reports` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `month` date DEFAULT NULL,
  `total_shipments` int(11) DEFAULT 0,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `total_paid` decimal(12,2) DEFAULT 0.00,
  `total_due` decimal(12,2) DEFAULT 0.00,
  `commission_earned` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `customer_parcels`
--

CREATE TABLE `customer_parcels` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `role` enum('sender','recipient','payer') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `customer_payments`
--

CREATE TABLE `customer_payments` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `parcel_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','transfer','check','online') DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bank_name` varchar(100) DEFAULT NULL COMMENT 'اسم البنك',
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'completed' COMMENT 'حالة الدفع',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `customer_payment_details`
--

CREATE TABLE `customer_payment_details` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `shipment_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='تفاصيل ربط المدفوعات بالشحنات';

-- --------------------------------------------------------

--
-- بنية الجدول `customer_payment_settings`
--

CREATE TABLE `customer_payment_settings` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `preferred_payment_method` enum('cash','transfer','check','online') DEFAULT 'cash',
  `auto_payment` tinyint(1) DEFAULT 0,
  `payment_reminder_days` int(11) DEFAULT 3,
  `minimum_payment_amount` decimal(12,2) DEFAULT 0.00,
  `maximum_credit_days` int(11) DEFAULT 30,
  `late_payment_fee_rate` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `customer_ratings`
--

CREATE TABLE `customer_ratings` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `payment_reliability` int(1) DEFAULT 5,
  `volume_rating` int(1) DEFAULT 3,
  `cooperation_rating` int(1) DEFAULT 5,
  `overall_rating` decimal(2,1) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `complaint_rating` int(1) DEFAULT 5 COMMENT 'قلة الشكاوى (1-5)',
  `risk_level` enum('low','medium','high') DEFAULT 'low' COMMENT 'مستوى المخاطر'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `customer_statements`
--

CREATE TABLE `customer_statements` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `transaction_type` enum('debit','credit') DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `balance_before` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'الرصيد قبل الحركة',
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ المعاملة',
  `created_by` varchar(100) DEFAULT NULL COMMENT 'المستخدم الذي أنشأ الحركة'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `financial_summary_view`
-- (See below for the actual view)
--
CREATE TABLE `financial_summary_view` (
`parcel_id` int(30)
,`tracking_number` varchar(255)
,`total_shipment_value` decimal(12,2)
,`delivered_value` decimal(12,2)
,`returned_value` decimal(12,2)
,`amount_paid_so_far` decimal(12,2)
,`remaining_balance` decimal(12,2)
,`detailed_payment_status` enum('unpaid','partially_paid','fully_paid')
,`date_created` datetime
,`customer_name` varchar(255)
,`customer_phone` varchar(50)
,`outstanding_balance` decimal(13,2)
,`calculated_status` varchar(14)
);

-- --------------------------------------------------------

--
-- بنية الجدول `free_notification_logs`
--

CREATE TABLE `free_notification_logs` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `recipient_type` enum('sender','recipient') NOT NULL,
  `status_id` int(11) NOT NULL,
  `message_content` text NOT NULL,
  `whatsapp_link` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_manually` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `governorates`
--

CREATE TABLE `governorates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `governorates`
--

INSERT INTO `governorates` (`id`, `name`, `price`) VALUES
(2, 'دمياط', 50.00),
(3, 'اسوان', 100.00);

-- --------------------------------------------------------

--
-- بنية الجدول `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `message_type` enum('sender','recipient') DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `message_content` text DEFAULT NULL,
  `send_status` enum('pending','sent','failed') DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `notification_settings`
--

CREATE TABLE `notification_settings` (
  `id` int(11) NOT NULL,
  `status_id` int(11) DEFAULT NULL,
  `send_to_sender` tinyint(1) DEFAULT 1,
  `send_to_recipient` tinyint(1) DEFAULT 1,
  `sender_message_template` text DEFAULT NULL,
  `recipient_message_template` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `parcels`
--

CREATE TABLE `parcels` (
  `id` int(30) NOT NULL,
  `tracking_number` varchar(255) DEFAULT NULL,
  `contents` text DEFAULT NULL,
  `reference_number` varchar(100) NOT NULL,
  `sender_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sender_address` text NOT NULL,
  `sender_contact` text NOT NULL,
  `recipient_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_address` text NOT NULL,
  `recipient_contact` text NOT NULL,
  `type` int(1) NOT NULL COMMENT '1 = Deliver, 2=Pickup',
  `from_branch_id` varchar(30) NOT NULL,
  `to_branch_id` varchar(30) NOT NULL,
  `weight` varchar(100) NOT NULL,
  `height` varchar(100) NOT NULL,
  `width` varchar(100) NOT NULL,
  `length` varchar(100) NOT NULL,
  `price` float NOT NULL,
  `status` int(2) NOT NULL DEFAULT 0,
  `status_reason_id` int(11) DEFAULT NULL,
  `status_reason_note` text DEFAULT NULL,
  `status_updated_at` timestamp NULL DEFAULT NULL,
  `status_updated_by` varchar(100) DEFAULT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `courier_id` int(11) DEFAULT NULL,
  `sender_phone` varchar(50) DEFAULT NULL,
  `sender_email` varchar(255) DEFAULT NULL,
  `sender_governorate_id` int(11) DEFAULT NULL,
  `sender_area_id` int(11) DEFAULT NULL,
  `sender_status` tinyint(4) DEFAULT 1,
  `recipient_phone` varchar(50) DEFAULT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `recipient_governorate_id` int(11) DEFAULT NULL,
  `recipient_area_id` int(11) DEFAULT NULL,
  `recipient_status` tinyint(4) DEFAULT 1,
  `agent_id` int(11) DEFAULT NULL,
  `delivery_agent_id` int(11) DEFAULT NULL,
  `agent_direction` varchar(20) DEFAULT NULL,
  `to_area` varchar(255) DEFAULT NULL,
  `refuse_reason` varchar(255) DEFAULT NULL,
  `is_paid` tinyint(1) DEFAULT NULL,
  `paid_amount` decimal(10,2) DEFAULT NULL,
  `partial_paid` decimal(10,2) DEFAULT NULL,
  `partial_note` varchar(255) DEFAULT NULL,
  `postpone_date` date DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `courier_note` text DEFAULT NULL,
  `shipping_fees` decimal(10,2) DEFAULT 0.00 COMMENT 'مصروفات الشحن',
  `agent_share` decimal(10,2) DEFAULT 0.00 COMMENT 'نصيب الوكيل',
  `delivery_agent_fee` decimal(10,2) DEFAULT 0.00 COMMENT 'عمولة المندوب',
  `company_profit` decimal(10,2) DEFAULT 0.00 COMMENT 'ربح الشركة',
  `total_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'إجمالي المبلغ',
  `customer_paid` decimal(10,2) DEFAULT 0.00 COMMENT 'ما دفعه العميل',
  `payment_status` enum('unpaid','partial_paid','partially_paid','paid') DEFAULT 'unpaid',
  `payment_method` enum('cash','transfer','deferred') DEFAULT 'cash' COMMENT 'طريقة الدفع',
  `collection_status` enum('pending','collected','failed') DEFAULT 'pending' COMMENT 'حالة التحصيل',
  `financial_notes` text DEFAULT NULL COMMENT 'ملاحظات مالية',
  `profit_calculated` tinyint(1) DEFAULT 0 COMMENT 'تم حساب الربح',
  `commission_paid` tinyint(1) DEFAULT 0 COMMENT 'تم دفع العمولة',
  `created_financial_transaction` tinyint(1) DEFAULT 0 COMMENT 'تم إنشاء حركة مالية',
  `shipment_direction` enum('to_agent','from_agent') DEFAULT NULL COMMENT 'اتجاه الشحنة: إرسال_للوكيل أو استلام_من_الوكيل',
  `client_id_fk` int(11) DEFAULT NULL COMMENT 'مفتاح جدول العملاء (المرسل)',
  `agent_cost` decimal(10,2) DEFAULT NULL COMMENT 'تكلفة الوكيل',
  `cod_amount` decimal(10,2) DEFAULT NULL COMMENT 'مبلغ التحصيل عند الاستلام (COD)',
  `declared_value` decimal(10,2) DEFAULT NULL COMMENT 'القيمة المعلن عنها',
  `delegate_id_fk` int(11) DEFAULT NULL COMMENT 'مفتاح جدول المناديب (في حالة الاستلام)',
  `total_to_collect` decimal(10,2) DEFAULT NULL COMMENT 'الإجمالي المطلوب تحصيله من المستلم',
  `net_amount` decimal(10,2) DEFAULT NULL COMMENT 'المبلغ الصافي للعملية',
  `note` text DEFAULT NULL,
  `pieces` int(11) DEFAULT 1,
  `net_profit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_payer` enum('sender','recipient') DEFAULT 'sender' COMMENT 'من يتحمل تكلفة الشحن',
  `courier_commission` decimal(10,2) NOT NULL DEFAULT 0.00,
  `returned_pieces` int(11) NOT NULL DEFAULT 0,
  `net_amount_due` decimal(10,2) DEFAULT 0.00 COMMENT 'المبلغ الصافي المستحق',
  `customer_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `returned_items_count` int(11) DEFAULT 0 COMMENT 'عدد القطع المرتجعة',
  `return_value` decimal(10,2) DEFAULT 0.00 COMMENT 'قيمة المرتجع',
  `return_reason` text DEFAULT NULL COMMENT 'سبب الإرجاع',
  `return_date` timestamp NULL DEFAULT NULL COMMENT 'تاريخ الإرجاع',
  `customer_due_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'المبلغ المستحق للعميل فعلياً',
  `total_shipment_value` decimal(12,2) DEFAULT 0.00 COMMENT 'القيمة الإجمالية للشحنة',
  `delivered_value` decimal(12,2) DEFAULT 0.00 COMMENT 'قيمة المسلم',
  `returned_value` decimal(12,2) DEFAULT 0.00 COMMENT 'قيمة المرتجع',
  `amount_paid_so_far` decimal(12,2) DEFAULT 0.00 COMMENT 'المبلغ المدفوع حتى الآن',
  `remaining_balance` decimal(12,2) DEFAULT 0.00 COMMENT 'الرصيد المتبقي',
  `detailed_payment_status` enum('unpaid','partially_paid','fully_paid') DEFAULT 'unpaid' COMMENT 'حالة الدفع التفصيلية'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `parcels`
--

INSERT INTO `parcels` (`id`, `tracking_number`, `contents`, `reference_number`, `sender_name`, `sender_address`, `sender_contact`, `recipient_name`, `recipient_address`, `recipient_contact`, `type`, `from_branch_id`, `to_branch_id`, `weight`, `height`, `width`, `length`, `price`, `status`, `status_reason_id`, `status_reason_note`, `status_updated_at`, `status_updated_by`, `date_created`, `courier_id`, `sender_phone`, `sender_email`, `sender_governorate_id`, `sender_area_id`, `sender_status`, `recipient_phone`, `recipient_email`, `recipient_governorate_id`, `recipient_area_id`, `recipient_status`, `agent_id`, `delivery_agent_id`, `agent_direction`, `to_area`, `refuse_reason`, `is_paid`, `paid_amount`, `partial_paid`, `partial_note`, `postpone_date`, `paid_at`, `courier_note`, `shipping_fees`, `agent_share`, `delivery_agent_fee`, `company_profit`, `total_amount`, `customer_paid`, `payment_status`, `payment_method`, `collection_status`, `financial_notes`, `profit_calculated`, `commission_paid`, `created_financial_transaction`, `shipment_direction`, `client_id_fk`, `agent_cost`, `cod_amount`, `declared_value`, `delegate_id_fk`, `total_to_collect`, `net_amount`, `note`, `pieces`, `net_profit`, `shipping_payer`, `courier_commission`, `returned_pieces`, `net_amount_due`, `customer_id`, `updated_at`, `returned_items_count`, `return_value`, `return_reason`, `return_date`, `customer_due_amount`, `total_shipment_value`, `delivered_value`, `returned_value`, `amount_paid_so_far`, `remaining_balance`, `detailed_payment_status`) VALUES
(1, 'TRACK-68992873B529C', 'ملابس', 'REF-68992873E9330', 'شعبان', 'دمياط', '01030552995', 'منه', 'دمياط', '01001578685', 1, '', '', '0', '0', '0', '0', 0, 4, NULL, '', '2025-08-11 00:34:50', '4', '2025-08-11 02:17:08', 4, '01030552995', NULL, 2, 1, 1, '01001578685', NULL, 2, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', NULL, NULL, NULL, 30.00, 0.00, 30.00, 3.00, 2500.00, 0.00, 'unpaid', 'cash', 'pending', '', 0, 0, 0, 'from_agent', NULL, NULL, 2500.00, 0.00, NULL, 2500.00, 2470.00, '', 1, 0.00, 'sender', 0.00, 0, 0.00, NULL, '2025-08-11 00:34:50', 0, 0.00, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid'),
(2, 'TRACK-689928CE7F96D', 'لا', 'REF-689928CE898D9', 'شعبان', 'دمياط', '01030552995', 'مريم', 'يسريس', '0101581851', 1, '', '', '0', '0', '0', '0', 0, 4, NULL, '', '2025-08-11 04:58:49', '4', '2025-08-11 02:18:38', 4, '01030552995', NULL, 2, 1, 1, '0101581851', NULL, 2, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', NULL, NULL, NULL, 20.00, 0.00, 20.00, 3.00, 1000.00, 0.00, 'unpaid', 'cash', 'pending', '', 0, 0, 0, 'from_agent', NULL, NULL, 1000.00, 0.00, NULL, 1000.00, 980.00, '', 1, 0.00, 'sender', 0.00, 0, 0.00, NULL, '2025-08-11 04:58:49', 0, 0.00, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid'),
(3, 'TRACK-68992A00322ED', 'م', 'REF-68992A003EC14', 'محمد', 'شسبسشب', '0102030210', 'بشسبشس', 'لاءرسير', '01040504', 1, '', '', '0', '0', '0', '0', 0, 4, NULL, '', '2025-08-11 04:58:47', '4', '2025-08-11 02:23:44', 4, '0102030210', NULL, 2, 1, 1, '01040504', NULL, 2, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 0.00, 1000.00, '', NULL, NULL, NULL, 40.00, 0.00, 40.00, 3.00, 2500.00, 0.00, 'unpaid', 'cash', 'pending', '', 0, 0, 0, 'from_agent', NULL, NULL, 2500.00, 0.00, NULL, 2500.00, 2460.00, '', 1, 0.00, 'sender', 0.00, 1, 0.00, NULL, '2025-08-11 04:58:47', 0, 0.00, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid'),
(4, 'TRACK-6899476F7D514', 'ملابس', 'REF-6899476F86BBA', 'مريم', 'دمياط الجديدة', '01030552998', 'محمد ابراهيم', 'السنانيه', '010225998452', 1, '', '', '0', '0', '0', '0', 0, 4, NULL, '', '2025-08-11 12:51:41', '4', '2025-08-11 04:29:19', 4, '01030552998', NULL, 2, 1, 1, '010225998452', NULL, 2, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', NULL, NULL, NULL, 25.00, 0.00, 25.00, 3.00, 2350.00, 0.00, 'unpaid', 'cash', 'pending', '', 0, 0, 0, 'from_agent', NULL, NULL, 2350.00, 0.00, NULL, 2350.00, 2325.00, '', 1, 0.00, 'sender', 0.00, 0, 0.00, NULL, '2025-08-11 12:51:41', 0, 0.00, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid'),
(5, 'TRACK-68994CA7082D8', 'ملابس', 'REF-68994CA713B6D', 'شهد ابراهيم', 'دمياط', '01030225985', 'شعبان', 'السنانيه', '01030552995', 1, '', '', '0', '0', '0', '0', 0, 4, NULL, '', '2025-08-11 04:58:44', '4', '2025-08-11 04:51:35', 4, '01030225985', NULL, 2, 1, 1, '01030552995', NULL, 2, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', NULL, NULL, NULL, 25.00, 0.00, 25.00, 3.00, 2500.00, 0.00, 'unpaid', 'cash', 'pending', '', 0, 0, 0, 'from_agent', NULL, NULL, 2500.00, 0.00, NULL, 2500.00, 2475.00, '', 1, 0.00, 'sender', 0.00, 0, 0.00, NULL, '2025-08-11 04:58:44', 0, 0.00, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid'),
(6, 'TRACK-68995317B9DDA', 'ملابس', 'REF-68995317C0F7D', 'مرام محمود', 'دمياط', '01030552987', 'شهنده علاء', 'الزرقا', '01030552952', 1, '', '', '0', '0', '0', '0', 0, 4, NULL, '', '2025-08-11 12:00:14', '4', '2025-08-11 05:19:03', 4, '01030552987', NULL, 2, 1, 1, '01030552952', NULL, 2, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', '2025-08-12', NULL, NULL, 25.00, 0.00, 25.00, 3.00, 2500.00, 0.00, 'unpaid', 'cash', 'pending', '', 0, 0, 0, 'from_agent', NULL, NULL, 2500.00, 0.00, NULL, 2500.00, 2475.00, '', 1, 0.00, 'sender', 0.00, 0, 0.00, NULL, '2025-08-11 12:00:14', 0, 0.00, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid'),
(7, 'TRACK-68995559A7C3F', 'ملابس', 'REF-68995559AE9C5', 'شهد شهد', 'الزرقا', '01365256150', 'مريم مريم', 'دمياط', '01365250150', 1, '', '', '0', '0', '0', '0', 0, 4, NULL, '', '2025-08-11 04:58:33', '4', '2025-08-11 05:28:41', 4, '01365256150', NULL, 2, 1, 1, '01365250150', NULL, 2, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', NULL, NULL, NULL, 30.00, 0.00, 30.00, 3.00, 2500.00, 0.00, 'unpaid', 'cash', 'pending', '', 0, 0, 0, 'from_agent', NULL, NULL, 2500.00, 0.00, NULL, 2500.00, 2470.00, '', 1, 0.00, 'sender', 0.00, 0, 0.00, NULL, '2025-08-11 04:58:33', 0, 0.00, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid'),
(8, 'TRACK-6899571ADDF33', 'ريس', 'REF-6899571AE4730', 'بسيبلسيل', 'سيليسليس', '00540450', 'تلىؤلاءؤ', 'لابيسي', '042042', 1, '', '', '0', '0', '0', '0', 0, 4, NULL, '', '2025-08-11 04:58:31', '4', '2025-08-11 05:36:10', 4, '00540450', NULL, 2, 1, 1, '042042', NULL, 2, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', NULL, NULL, NULL, 25.00, 0.00, 25.00, 3.00, 1000.00, 0.00, 'unpaid', 'cash', 'pending', '', 0, 0, 0, 'from_agent', NULL, NULL, 1000.00, 0.00, NULL, 1000.00, 975.00, '', 1, 0.00, 'sender', 0.00, 0, 0.00, NULL, '2025-08-11 04:58:31', 0, 0.00, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid'),
(9, 'TRACK-689958451CE04', 'لي', 'REF-68995845274CD', 'لابيلاب', 'لاييبلا', '02404', 'يلسيلسيءر', 'ارءؤئء', '272752752', 1, '', '', '0', '0', '0', '0', 0, 4, NULL, '', '2025-08-11 04:58:28', '4', '2025-08-11 05:41:09', 4, '02404', NULL, 2, 1, 1, '272752752', NULL, 2, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', NULL, NULL, NULL, 80.00, 0.00, 80.00, 3.00, 1000.00, 0.00, 'unpaid', 'cash', 'pending', '', 0, 0, 0, 'from_agent', NULL, NULL, 1000.00, 0.00, NULL, 1000.00, 920.00, '', 1, 0.00, 'sender', 0.00, 0, 0.00, NULL, '2025-08-11 04:58:28', 0, 0.00, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid'),
(10, 'TRACK-689959F917428', 'v', 'REF-689959F91E1FF', 'hfdgdfgx', 'gdcx', '573757', 'bdfvcvsdvx', 'fvxcbxnfbc', '0240401', 1, '', '', '0', '0', '0', '0', 0, 2, NULL, '', '2025-08-11 04:58:26', '4', '2025-08-11 05:48:25', 4, '573757', NULL, 2, 1, 1, '0240401', NULL, 2, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', NULL, NULL, NULL, 30.00, 0.00, 30.00, 3.00, 500.00, 0.00, 'unpaid', 'cash', 'pending', '', 0, 0, 0, 'from_agent', NULL, NULL, 500.00, 0.00, NULL, 500.00, 470.00, '', 1, 0.00, 'sender', 0.00, 0, 0.00, NULL, '2025-08-11 13:12:00', 0, 0.00, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid'),
(11, 'TRACK-68995D4E3C6F0', 'ؤ', 'REF-68995D4E4584B', 'يرسيلايسلاي', 'بسبسير', '0101010505421', 'يسلسيليسل', 'سيرسيرء', '0410420', 1, '', '', '0', '0', '0', '0', 0, 2, NULL, '', '2025-08-11 12:52:17', '4', '2025-08-11 06:02:38', 4, '0101010505421', NULL, 2, 1, 1, '0410420', NULL, 2, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', NULL, NULL, NULL, 50.00, 0.00, 50.00, 3.00, 2500.00, 0.00, 'unpaid', 'cash', 'pending', '', 0, 0, 0, 'from_agent', NULL, NULL, 2500.00, 0.00, NULL, 2500.00, 2450.00, '', 1, 0.00, 'sender', 0.00, 0, 0.00, NULL, '2025-08-11 12:53:55', 0, 0.00, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid');

--
-- القوادح `parcels`
--
DELIMITER $$
CREATE TRIGGER `calculate_balance_after_update` AFTER UPDATE ON `parcels` FOR EACH ROW BEGIN
    -- فقط إذا تغيرت القيم المالية
    IF (OLD.delivered_value != NEW.delivered_value OR 
        OLD.amount_paid_so_far != NEW.amount_paid_so_far OR 
        OLD.returned_value != NEW.returned_value) THEN
        CALL CalculateParcelBalance(NEW.id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- بنية الجدول `parcels_notes`
--

CREATE TABLE `parcels_notes` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `courier_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `sender_type` enum('courier','admin') DEFAULT 'courier'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `parcel_collections`
--

CREATE TABLE `parcel_collections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parcel_id` int(30) NOT NULL,
  `collected_by_courier_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('cash','pos','transfer','other') NOT NULL DEFAULT 'cash',
  `collected_at` datetime NOT NULL DEFAULT current_timestamp(),
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `parcel_history`
--

CREATE TABLE `parcel_history` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL COMMENT 'حالة الشحنة',
  `remarks` text DEFAULT NULL,
  `update_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `parcel_notes`
--

CREATE TABLE `parcel_notes` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `courier_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `parcel_payment_history`
--

CREATE TABLE `parcel_payment_history` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `payment_amount` decimal(12,2) NOT NULL COMMENT 'مبلغ الدفعة',
  `payment_method` enum('cash','transfer','check','online') DEFAULT 'cash' COMMENT 'طريقة الدفع',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الدفع',
  `remaining_after_payment` decimal(12,2) DEFAULT 0.00 COMMENT 'المتبقي بعد الدفع',
  `payment_status_after` enum('unpaid','partially_paid','fully_paid') DEFAULT 'unpaid' COMMENT 'حالة الدفع بعد هذه الدفعة',
  `reference_number` varchar(100) DEFAULT NULL COMMENT 'رقم المرجع',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات',
  `processed_by` varchar(100) DEFAULT NULL COMMENT 'معالج الدفع',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل دفعات الشحنات';

-- --------------------------------------------------------

--
-- بنية الجدول `parcel_payment_transactions`
--

CREATE TABLE `parcel_payment_transactions` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `transaction_type` enum('payment','return','delivery','adjustment') NOT NULL COMMENT 'نوع المعاملة',
  `amount` decimal(12,2) NOT NULL COMMENT 'المبلغ',
  `payment_method` enum('cash','transfer','check','online') DEFAULT 'cash' COMMENT 'طريقة الدفع',
  `description` text DEFAULT NULL COMMENT 'وصف المعاملة',
  `reference_number` varchar(100) DEFAULT NULL COMMENT 'رقم المرجع',
  `created_by` varchar(100) DEFAULT NULL COMMENT 'منشئ المعاملة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='معاملات الدفع للشحنات';

-- --------------------------------------------------------

--
-- بنية الجدول `parcel_returns`
--

CREATE TABLE `parcel_returns` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `returned_items_count` int(11) DEFAULT 1 COMMENT 'عدد القطع المرتجعة',
  `returned_value` decimal(12,2) NOT NULL COMMENT 'قيمة المرتجع',
  `return_reason` text DEFAULT NULL COMMENT 'سبب الإرجاع',
  `return_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإرجاع',
  `return_method` enum('full','partial') DEFAULT 'partial' COMMENT 'طريقة الإرجاع',
  `processed_by` varchar(100) DEFAULT NULL COMMENT 'معالج الإرجاع',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تفاصيل المرتجعات';

-- --------------------------------------------------------

--
-- بنية الجدول `parcel_status`
--

CREATE TABLE `parcel_status` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `priority` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `parcel_status`
--

INSERT INTO `parcel_status` (`id`, `name_ar`, `priority`) VALUES
(1, 'قيد التنفيذ', 1),
(2, 'تم تسليمها للمندوب', 2),
(3, 'جاري التوصيل', 3),
(4, 'تم التسليم بنجاح', 4),
(5, 'تم الدفع بنجاح', 5),
(6, 'تم الدفع جزئي', 6),
(7, 'تم تأجيل الطلب', 7),
(8, 'تم الرفض', 8),
(9, 'مرتجع للفرع', 9),
(10, 'مرتجع للعميل', 10);

-- --------------------------------------------------------

--
-- بنية الجدول `parcel_status_history`
--

CREATE TABLE `parcel_status_history` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `old_status_id` int(11) DEFAULT NULL,
  `new_status_id` int(11) NOT NULL,
  `reason_id` int(11) DEFAULT NULL,
  `reason_note` text DEFAULT NULL,
  `changed_by` varchar(100) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `parcel_status_history`
--

INSERT INTO `parcel_status_history` (`id`, `parcel_id`, `old_status_id`, `new_status_id`, `reason_id`, `reason_note`, `changed_by`, `changed_at`, `ip_address`) VALUES
(1, 2, 2, 6, NULL, '', 'احمد ريف', '2025-08-11 00:25:17', '::1'),
(2, 2, 6, 4, NULL, '', 'احمد ريف', '2025-08-11 00:25:26', '::1'),
(3, 1, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 00:25:33', '::1'),
(4, 3, 4, 4, NULL, '', 'احمد ريف', '2025-08-11 00:25:37', '::1'),
(5, 2, 4, 4, NULL, '', 'احمد ريف', '2025-08-11 00:29:35', '::1'),
(6, 3, 4, 6, NULL, '', 'احمد ريف', '2025-08-11 00:31:14', '::1'),
(7, 2, 4, 4, NULL, '', 'احمد ريف', '2025-08-11 00:34:43', '::1'),
(8, 1, 4, 4, NULL, '', 'احمد ريف', '2025-08-11 00:34:50', '::1'),
(9, 4, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 01:29:48', '::1'),
(10, 5, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 01:51:45', '::1'),
(11, 6, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 02:19:19', '::1'),
(12, 6, 2, 7, 1, '', 'احمد ريف', '2025-08-11 02:22:57', '::1'),
(13, 7, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 02:32:02', '::1'),
(14, 7, 2, 8, 20, '', 'احمد ريف', '2025-08-11 02:35:22', '::1'),
(15, 8, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 02:39:29', '::1'),
(16, 10, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 02:56:02', '::1'),
(17, 9, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 03:02:04', '::1'),
(18, 11, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 03:03:09', '::1'),
(19, 11, 3, 4, NULL, '', 'احمد ريف', '2025-08-11 03:19:55', '::1'),
(20, 11, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:04:06', '::1'),
(21, 10, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:04:12', '::1'),
(22, 9, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:04:17', '::1'),
(23, 11, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:28:08', '::1'),
(24, 11, 2, 8, 20, '', 'احمد ريف', '2025-08-11 04:37:43', '::1'),
(25, 11, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:58:23', '::1'),
(26, 10, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:58:26', '::1'),
(27, 9, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:58:28', '::1'),
(28, 8, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:58:31', '::1'),
(29, 7, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:58:33', '::1'),
(30, 6, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:58:40', '::1'),
(31, 5, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:58:44', '::1'),
(32, 4, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:58:46', '::1'),
(33, 3, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:58:47', '::1'),
(34, 2, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 04:58:49', '::1'),
(35, 6, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 12:00:14', '::1'),
(36, 4, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 12:51:41', '::1'),
(37, 11, 2, 4, NULL, '', 'احمد ريف', '2025-08-11 12:52:17', '::1');

-- --------------------------------------------------------

--
-- بنية الجدول `parcel_status_logs`
--

CREATE TABLE `parcel_status_logs` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `courier_id` int(11) NOT NULL,
  `old_status` varchar(50) NOT NULL,
  `new_status` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `parcel_status_reasons`
--

CREATE TABLE `parcel_status_reasons` (
  `id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `reason_code` varchar(50) NOT NULL,
  `reason_text` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `parcel_status_reasons`
--

INSERT INTO `parcel_status_reasons` (`id`, `status_id`, `reason_code`, `reason_text`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 7, 'DEFER_TO_DATE', 'تأجيل إلى تاريخ محدد', 1, 1, '2025-08-10 23:00:14'),
(2, 7, 'CUSTOMER_REQUEST', 'طلب العميل التأجيل', 1, 2, '2025-08-10 23:00:14'),
(3, 7, 'WRONG_TIMING', 'توقيت غير مناسب للاستلام', 1, 3, '2025-08-10 23:00:14'),
(4, 7, 'NO_ANSWER', 'عدم الرد على الهاتف', 1, 4, '2025-08-10 23:00:14'),
(5, 7, 'BUSY', 'العميل مشغول الآن', 1, 5, '2025-08-10 23:00:14'),
(6, 7, 'OUT_OF_TOWN', 'العميل خارج المدينة/السفر', 1, 6, '2025-08-10 23:00:14'),
(7, 7, 'WEATHER_ISSUE', 'ظروف جوية سيئة', 1, 7, '2025-08-10 23:00:14'),
(8, 7, 'ADDRESS_NOT_CLEAR', 'العنوان غير واضح ويحتاج تأكيد', 1, 8, '2025-08-10 23:00:14'),
(9, 7, 'REQUEST_LATER_TODAY', 'يرغب بالتسليم لاحقًا اليوم', 1, 9, '2025-08-10 23:00:14'),
(10, 7, 'REQUEST_TOMORROW', 'يرغب بالتسليم غدًا', 1, 10, '2025-08-10 23:00:14'),
(11, 7, 'REQUEST_NEXT_WEEK', 'يرغب بالتسليم الأسبوع القادم', 1, 11, '2025-08-10 23:00:14'),
(12, 7, 'PAYMENT_NOT_READY', 'المبلغ غير متوفر حاليًا', 1, 12, '2025-08-10 23:00:14'),
(13, 7, 'RECIPIENT_ABSENT', 'عدم تواجد المستلم في الموقع', 1, 13, '2025-08-10 23:00:14'),
(14, 7, 'NEED_CALL_BEFORE', 'يرغب باتصال مسبق قبل التسليم', 1, 14, '2025-08-10 23:00:14'),
(15, 7, 'CHANGE_DELIVERY_TIME', 'تغيير وقت التسليم', 1, 15, '2025-08-10 23:00:14'),
(16, 7, 'CHANGE_DELIVERY_LOCATION', 'تغيير موقع التسليم', 1, 16, '2025-08-10 23:00:14'),
(17, 7, 'HOLIDAY', 'إجازة/عطلة رسمية', 1, 17, '2025-08-10 23:00:14'),
(18, 7, 'TRAFFIC', 'زحام/عائق مروري', 1, 18, '2025-08-10 23:00:14'),
(19, 7, 'TECHNICAL_ISSUE', 'ظرف فني لدى العميل', 1, 19, '2025-08-10 23:00:14'),
(20, 8, 'CUSTOMER_CHANGED_MIND', 'العميل غير رأيه', 1, 1, '2025-08-10 23:00:14'),
(21, 8, 'FOUND_BETTER_PRICE', 'وجد سعرًا أفضل', 1, 2, '2025-08-10 23:00:14'),
(22, 8, 'WRONG_ITEM', 'منتج غير صحيح', 1, 3, '2025-08-10 23:00:14'),
(23, 8, 'DAMAGED_GOODS', 'البضاعة تالفة/غير سليمة', 1, 4, '2025-08-10 23:00:14'),
(24, 8, 'PRICE_ISSUE', 'مشكلة في السعر/التكلفة', 1, 5, '2025-08-10 23:00:14'),
(25, 8, 'QUALITY_ISSUE', 'مشكلة في الجودة', 1, 6, '2025-08-10 23:00:14'),
(26, 8, 'DELAYED_DELIVERY', 'التأخير في التسليم', 1, 7, '2025-08-10 23:00:14'),
(27, 8, 'FAKE_ORDER', 'طلب وهمي/غير جاد', 1, 8, '2025-08-10 23:00:14'),
(28, 8, 'FRAUD_SUSPECT', 'شبهة احتيال', 1, 9, '2025-08-10 23:00:14'),
(29, 8, 'DUPLICATE_ORDER', 'طلب مكرر', 1, 10, '2025-08-10 23:00:14'),
(30, 8, 'WRONG_ADDRESS', 'عنوان خاطئ', 1, 11, '2025-08-10 23:00:14'),
(31, 8, 'WRONG_RECIPIENT', 'المستلم غير صحيح', 1, 12, '2025-08-10 23:00:14'),
(32, 8, 'NOT_ORDERED', 'العميل لم يطلب هذه الشحنة', 1, 13, '2025-08-10 23:00:14'),
(33, 8, 'EXPIRED_PRODUCT', 'المنتج منتهي الصلاحية', 1, 14, '2025-08-10 23:00:14'),
(34, 8, 'OVER_BUDGET', 'المبلغ أعلى من الميزانية', 1, 15, '2025-08-10 23:00:14'),
(35, 8, 'SIZE_WEIGHT_ISSUE', 'حجم/وزن غير مناسب', 1, 16, '2025-08-10 23:00:14'),
(36, 8, 'OTHER', 'سبب آخر', 1, 99, '2025-08-10 23:00:14');

-- --------------------------------------------------------

--
-- بنية الجدول `parcel_tracks`
--

CREATE TABLE `parcel_tracks` (
  `id` int(30) NOT NULL,
  `parcel_id` int(30) NOT NULL,
  `status` int(2) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `parcel_tracks`
--

INSERT INTO `parcel_tracks` (`id`, `parcel_id`, `status`, `date_created`, `remarks`, `customer_id`) VALUES
(1, 1, 2, '2025-08-11 02:17:08', NULL, NULL),
(2, 2, 2, '2025-08-11 02:18:38', NULL, NULL),
(3, 3, 2, '2025-08-11 02:23:44', NULL, NULL),
(4, 3, 4, '2025-08-11 02:59:34', '', NULL),
(5, 2, 6, '2025-08-11 03:25:17', NULL, NULL),
(6, 2, 4, '2025-08-11 03:25:26', NULL, NULL),
(7, 1, 4, '2025-08-11 03:25:33', NULL, NULL),
(8, 3, 4, '2025-08-11 03:25:37', NULL, NULL),
(9, 2, 4, '2025-08-11 03:29:35', NULL, NULL),
(10, 3, 6, '2025-08-11 03:31:14', NULL, NULL),
(11, 2, 4, '2025-08-11 03:34:43', NULL, NULL),
(12, 1, 4, '2025-08-11 03:34:50', NULL, NULL),
(13, 4, 2, '2025-08-11 04:29:19', NULL, NULL),
(14, 4, 4, '2025-08-11 04:29:48', NULL, NULL),
(15, 5, 2, '2025-08-11 04:51:35', NULL, NULL),
(16, 5, 4, '2025-08-11 04:51:45', NULL, NULL),
(17, 6, 2, '2025-08-11 05:19:03', NULL, NULL),
(18, 6, 4, '2025-08-11 05:19:19', NULL, NULL),
(19, 6, 2, '2025-08-11 05:19:39', '', NULL),
(20, 6, 7, '2025-08-11 05:22:57', NULL, NULL),
(21, 6, 2, '2025-08-11 05:25:58', '', NULL),
(22, 6, 4, '2025-08-11 05:26:29', '', NULL),
(23, 7, 2, '2025-08-11 05:28:41', NULL, NULL),
(24, 7, 4, '2025-08-11 05:32:02', NULL, NULL),
(25, 7, 2, '2025-08-11 05:34:51', '', NULL),
(26, 7, 8, '2025-08-11 05:35:22', NULL, NULL),
(27, 8, 2, '2025-08-11 05:36:10', NULL, NULL),
(28, 8, 4, '2025-08-11 05:39:29', NULL, NULL),
(29, 9, 2, '2025-08-11 05:41:09', NULL, NULL),
(30, 10, 2, '2025-08-11 05:48:25', NULL, NULL),
(31, 10, 4, '2025-08-11 05:56:02', NULL, NULL),
(32, 9, 4, '2025-08-11 06:02:04', NULL, NULL),
(33, 11, 2, '2025-08-11 06:02:38', NULL, NULL),
(34, 11, 4, '2025-08-11 06:03:09', NULL, NULL),
(35, 11, 3, '2025-08-11 06:04:44', '', NULL),
(36, 11, 4, '2025-08-11 06:19:55', NULL, NULL),
(37, 11, 2, '2025-08-11 06:21:22', '', NULL),
(38, 10, 2, '2025-08-11 06:21:27', '', NULL),
(39, 9, 2, '2025-08-11 06:21:33', '', NULL),
(40, 8, 2, '2025-08-11 06:27:15', '', NULL),
(41, 7, 2, '2025-08-11 06:29:42', '', NULL),
(42, 4, 2, '2025-08-11 06:33:32', '', NULL),
(43, 3, 2, '2025-08-11 06:33:54', '', NULL),
(44, 5, 2, '2025-08-11 06:44:57', '', NULL),
(45, 2, 2, '2025-08-11 07:01:30', '', NULL),
(46, 6, 2, '2025-08-11 07:02:19', '', NULL),
(47, 11, 4, '2025-08-11 07:04:06', NULL, NULL),
(48, 10, 4, '2025-08-11 07:04:12', NULL, NULL),
(49, 9, 4, '2025-08-11 07:04:17', NULL, NULL),
(50, 11, 2, '2025-08-11 07:04:43', '', NULL),
(51, 10, 2, '2025-08-11 07:11:17', '', NULL),
(52, 11, 5, '2025-08-11 07:13:23', NULL, NULL),
(53, 11, 2, '2025-08-11 07:17:45', '', NULL),
(54, 11, 4, '2025-08-11 07:28:08', NULL, NULL),
(55, 9, 2, '2025-08-11 07:29:56', '', NULL),
(56, 2, 1, '2025-08-11 07:30:07', '', NULL),
(57, 11, 2, '2025-08-11 07:30:30', '', NULL),
(58, 2, 2, '2025-08-11 07:31:32', '', NULL),
(59, 8, 1, '2025-08-11 07:34:37', '', NULL),
(60, 8, 2, '2025-08-11 07:34:53', '', NULL),
(61, 7, 1, '2025-08-11 07:35:18', '', NULL),
(62, 7, 2, '2025-08-11 07:35:30', '', NULL),
(63, 11, 8, '2025-08-11 07:37:43', NULL, NULL),
(64, 11, 2, '2025-08-11 07:48:14', '', NULL),
(65, 11, 4, '2025-08-11 07:58:23', NULL, NULL),
(66, 10, 4, '2025-08-11 07:58:26', NULL, NULL),
(67, 9, 4, '2025-08-11 07:58:28', NULL, NULL),
(68, 8, 4, '2025-08-11 07:58:31', NULL, NULL),
(69, 7, 4, '2025-08-11 07:58:33', NULL, NULL),
(70, 6, 4, '2025-08-11 07:58:40', NULL, NULL),
(71, 5, 4, '2025-08-11 07:58:44', NULL, NULL),
(72, 4, 4, '2025-08-11 07:58:46', NULL, NULL),
(73, 3, 4, '2025-08-11 07:58:47', NULL, NULL),
(74, 2, 4, '2025-08-11 07:58:49', NULL, NULL),
(75, 4, 2, '2025-08-11 08:00:29', '', NULL),
(76, 6, 2, '2025-08-11 08:21:55', '', NULL),
(77, 6, 4, '2025-08-11 15:00:14', NULL, NULL),
(78, 11, 7, '2025-08-11 15:28:25', '', NULL),
(79, 4, 4, '2025-08-11 15:51:41', NULL, NULL),
(80, 11, 2, '2025-08-11 15:51:58', '', NULL),
(81, 11, 4, '2025-08-11 15:52:17', NULL, NULL),
(82, 11, 2, '2025-08-11 15:53:55', '', NULL),
(83, 10, 2, '2025-08-11 16:12:00', '', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `payments_log`
--

CREATE TABLE `payments_log` (
  `id` int(11) NOT NULL,
  `transaction_number` varchar(50) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `shipments` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `pending_payouts`
--

CREATE TABLE `pending_payouts` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `party_type` enum('customer','agent','courier') NOT NULL,
  `party_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payout_type` enum('shipping_fee','agent_commission','courier_commission','customer_refund') NOT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `profit_reports`
--

CREATE TABLE `profit_reports` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `total_revenue` decimal(10,2) NOT NULL,
  `shipping_fees` decimal(10,2) NOT NULL,
  `agent_commission` decimal(10,2) NOT NULL,
  `courier_commission` decimal(10,2) NOT NULL,
  `net_profit` decimal(10,2) NOT NULL,
  `profit_margin` decimal(5,2) NOT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `settlements`
--

CREATE TABLE `settlements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_type` enum('client','agent','courier') NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `total_payable` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','posted','partial','paid') NOT NULL DEFAULT 'draft',
  `reference` varchar(64) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(30) NOT NULL,
  `name` text NOT NULL,
  `email` varchar(200) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `cover_img` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `system_settings`
--

INSERT INTO `system_settings` (`id`, `name`, `email`, `contact`, `address`, `cover_img`) VALUES
(1, 'Courier Management System', 'info@sample.comm', '+6948 8542 623', '2102  Caldwell Road, Rochester, New York, 14608', '');

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(30) NOT NULL,
  `firstname` varchar(200) NOT NULL,
  `lastname` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password` text NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT 2 COMMENT '1 = admin, 2 = staff',
  `branch_id` int(30) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `firstname`, `lastname`, `email`, `password`, `type`, `branch_id`, `date_created`) VALUES
(1, 'شعبان', 'Master', 'admin@admin.com', '$2y$10$flzntutA1vLPgMcmxHI3FunNTWM1GVJ5bLU5.vlCMkA84vUbVopHW', 1, 1, '2025-08-03 17:51:31');

-- --------------------------------------------------------

--
-- بنية الجدول `wallets`
--

CREATE TABLE `wallets` (
  `id` int(11) NOT NULL,
  `party_type` enum('customer','agent','courier','company') NOT NULL,
  `party_id` int(11) NOT NULL,
  `balance` decimal(12,2) DEFAULT 0.00,
  `last_transaction_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `whatsapp_config`
--

CREATE TABLE `whatsapp_config` (
  `id` int(11) NOT NULL,
  `api_provider` enum('twilio','maytapi','chatapi','custom') DEFAULT 'twilio',
  `api_url` varchar(255) DEFAULT NULL,
  `api_token` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `rate_limit_per_minute` int(11) DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `customer_due_shipments`
--
DROP TABLE IF EXISTS `customer_due_shipments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `customer_due_shipments`  AS SELECT `p`.`id` AS `parcel_id`, `p`.`tracking_number` AS `tracking_number`, `c`.`id` AS `customer_id`, `c`.`name` AS `customer_name`, `c`.`phone` AS `customer_phone`, `p`.`cod_amount` AS `cod_amount`, `p`.`paid_amount` AS `paid_amount`, `p`.`cod_amount`- ifnull(`p`.`paid_amount`,0) AS `due_amount`, `p`.`date_created` AS `shipment_date`, to_days(current_timestamp()) - to_days(`p`.`date_created`) AS `days_overdue`, `ps`.`name_ar` AS `status_name` FROM ((`parcels` `p` join `customers` `c` on(`p`.`client_id_fk` = `c`.`id`)) left join `parcel_status` `ps` on(`p`.`status` = `ps`.`id`)) WHERE `p`.`status` = 4 AND `p`.`payment_status` <> 'paid' AND `p`.`cod_amount` - ifnull(`p`.`paid_amount`,0) > 0 ;

-- --------------------------------------------------------

--
-- Structure for view `customer_financial_summary`
--
DROP TABLE IF EXISTS `customer_financial_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `customer_financial_summary`  AS SELECT `c`.`id` AS `id`, `c`.`name` AS `name`, `c`.`phone` AS `phone`, `c`.`email` AS `email`, `c`.`balance` AS `balance`, `c`.`credit_limit` AS `credit_limit`, `c`.`total_due` AS `total_due`, `c`.`last_payment_date` AS `last_payment_date`, ifnull(`parcels_stats`.`total_parcels`,0) AS `total_parcels`, ifnull(`parcels_stats`.`delivered_parcels`,0) AS `delivered_parcels`, ifnull(`parcels_stats`.`total_cod_amount`,0) AS `total_cod_amount`, ifnull(`parcels_stats`.`total_paid_amount`,0) AS `total_paid_amount`, ifnull(`parcels_stats`.`pending_amount`,0) AS `pending_amount`, ifnull(`cr`.`overall_rating`,0) AS `customer_rating`, CASE WHEN `c`.`balance` > `c`.`credit_limit` THEN 'over_limit' WHEN `c`.`balance` < 0 THEN 'overdue' WHEN `c`.`balance` = 0 THEN 'cleared' ELSE 'active' END AS `account_status` FROM ((`customers` `c` left join (select `parcels`.`client_id_fk` AS `client_id_fk`,count(0) AS `total_parcels`,sum(case when `parcels`.`status` = 4 then 1 else 0 end) AS `delivered_parcels`,sum(ifnull(`parcels`.`cod_amount`,0)) AS `total_cod_amount`,sum(ifnull(`parcels`.`paid_amount`,0)) AS `total_paid_amount`,sum(case when `parcels`.`status` = 4 and `parcels`.`payment_status` <> 'paid' then ifnull(`parcels`.`cod_amount`,0) else 0 end) AS `pending_amount` from `parcels` where `parcels`.`client_id_fk` is not null group by `parcels`.`client_id_fk`) `parcels_stats` on(`c`.`id` = `parcels_stats`.`client_id_fk`)) left join `customer_ratings` `cr` on(`c`.`id` = `cr`.`customer_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `financial_summary_view`
--
DROP TABLE IF EXISTS `financial_summary_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `financial_summary_view`  AS SELECT `p`.`id` AS `parcel_id`, `p`.`tracking_number` AS `tracking_number`, `p`.`total_shipment_value` AS `total_shipment_value`, `p`.`delivered_value` AS `delivered_value`, `p`.`returned_value` AS `returned_value`, `p`.`amount_paid_so_far` AS `amount_paid_so_far`, `p`.`remaining_balance` AS `remaining_balance`, `p`.`detailed_payment_status` AS `detailed_payment_status`, `p`.`date_created` AS `date_created`, `c`.`name` AS `customer_name`, `c`.`phone` AS `customer_phone`, `p`.`delivered_value`- `p`.`amount_paid_so_far` AS `outstanding_balance`, CASE WHEN `p`.`amount_paid_so_far` = 0 THEN 'Unpaid' WHEN `p`.`remaining_balance` = 0 THEN 'Fully Paid' ELSE 'Partially Paid' END AS `calculated_status` FROM (`parcels` `p` left join `customers` `c` on(`p`.`client_id_fk` = `c`.`id`)) WHERE `p`.`status` in (4,5,6) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agents`
--
ALTER TABLE `agents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `agent_payments`
--
ALTER TABLE `agent_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Indexes for table `agent_transactions`
--
ALTER TABLE `agent_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_id` (`agent_id`),
  ADD KEY `shipment_id` (`shipment_id`);

--
-- Indexes for table `agent_users`
--
ALTER TABLE `agent_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `governorate_id` (`governorate_id`);

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_backup_type` (`backup_type`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `branch_cashbox_transactions`
--
ALTER TABLE `branch_cashbox_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cashbox_logs`
--
ALTER TABLE `cashbox_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cashbox_transactions`
--
ALTER TABLE `cashbox_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `type` (`type`),
  ADD KEY `relation_id` (`relation_id`);

--
-- Indexes for table `couriers`
--
ALTER TABLE `couriers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `courier_areas`
--
ALTER TABLE `courier_areas`
  ADD KEY `idx_courier_areas_area_courier` (`area_id`,`courier_id`);

--
-- Indexes for table `courier_auth_tokens`
--
ALTER TABLE `courier_auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_courier_expires` (`courier_id`,`expires_at`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_phone` (`client_phone`),
  ADD KEY `idx_customers_phone` (`phone`),
  ADD KEY `idx_customers_balance` (`balance`);

--
-- Indexes for table `customer_accounts`
--
ALTER TABLE `customer_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_number` (`account_number`);

--
-- Indexes for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_balances`
--
ALTER TABLE `customer_balances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_balance` (`customer_id`,`created_at`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_type_status` (`type`,`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `customer_logs`
--
ALTER TABLE `customer_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_monthly_reports`
--
ALTER TABLE `customer_monthly_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_parcels`
--
ALTER TABLE `customer_parcels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer_parcel_role` (`customer_id`,`parcel_id`,`role`),
  ADD KEY `idx_customer_role` (`customer_id`,`role`),
  ADD KEY `idx_parcel_role` (`parcel_id`,`role`);

--
-- Indexes for table `customer_payments`
--
ALTER TABLE `customer_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_payment_details`
--
ALTER TABLE `customer_payment_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment` (`payment_id`),
  ADD KEY `idx_shipment` (`shipment_id`);

--
-- Indexes for table `customer_payment_settings`
--
ALTER TABLE `customer_payment_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer_settings` (`customer_id`);

--
-- Indexes for table `customer_ratings`
--
ALTER TABLE `customer_ratings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_statements`
--
ALTER TABLE `customer_statements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_customer_statements_customer` (`customer_id`);

--
-- Indexes for table `free_notification_logs`
--
ALTER TABLE `free_notification_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `governorates`
--
ALTER TABLE `governorates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcels`
--
ALTER TABLE `parcels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parcels_payment_status` (`payment_status`),
  ADD KEY `idx_parcels_collection_status` (`collection_status`),
  ADD KEY `idx_parcels_agent_id` (`agent_id`),
  ADD KEY `idx_parcels_courier_id` (`courier_id`),
  ADD KEY `idx_parcels_financial` (`profit_calculated`,`commission_paid`),
  ADD KEY `idx_parcels_direction` (`shipment_direction`),
  ADD KEY `idx_parcels_status` (`status`),
  ADD KEY `idx_parcels_date_created` (`date_created`),
  ADD KEY `idx_parcels_agent` (`agent_id`),
  ADD KEY `idx_parcels_courier` (`courier_id`),
  ADD KEY `idx_parcels_client` (`client_id_fk`),
  ADD KEY `idx_status_reason` (`status_reason_id`),
  ADD KEY `idx_status_updated` (`status_updated_at`),
  ADD KEY `idx_parcels_status_reason` (`status`,`status_reason_id`),
  ADD KEY `idx_parcels_status_updated` (`status`,`status_updated_at`),
  ADD KEY `idx_parcels_sender_email` (`sender_email`),
  ADD KEY `idx_parcels_recipient_email` (`recipient_email`),
  ADD KEY `idx_client_fk` (`client_id_fk`),
  ADD KEY `idx_sender_phone` (`sender_phone`),
  ADD KEY `idx_status_payment` (`status`,`payment_status`),
  ADD KEY `idx_date_created` (`date_created`),
  ADD KEY `idx_shipment_direction` (`shipment_direction`),
  ADD KEY `idx_parcels_sender_phone` (`sender_phone`),
  ADD KEY `idx_detailed_payment_status` (`detailed_payment_status`),
  ADD KEY `idx_delivered_value` (`delivered_value`),
  ADD KEY `idx_amount_paid` (`amount_paid_so_far`),
  ADD KEY `idx_remaining_balance` (`remaining_balance`),
  ADD KEY `idx_parcels_paid_amount` (`paid_amount`),
  ADD KEY `idx_parcels_courier_status` (`courier_id`,`status`),
  ADD KEY `idx_parcels_courier_status_updated` (`courier_id`,`status`,`updated_at`);

--
-- Indexes for table `parcels_notes`
--
ALTER TABLE `parcels_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parcel_id` (`parcel_id`),
  ADD KEY `courier_id` (`courier_id`);

--
-- Indexes for table `parcel_collections`
--
ALTER TABLE `parcel_collections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parcel_id` (`parcel_id`),
  ADD KEY `collected_by_courier_id` (`collected_by_courier_id`);

--
-- Indexes for table `parcel_history`
--
ALTER TABLE `parcel_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parcel_id` (`parcel_id`);

--
-- Indexes for table `parcel_notes`
--
ALTER TABLE `parcel_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parcel_id` (`parcel_id`),
  ADD KEY `courier_id` (`courier_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `parcel_payment_history`
--
ALTER TABLE `parcel_payment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parcel_id` (`parcel_id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_payment_status` (`payment_status_after`);

--
-- Indexes for table `parcel_payment_transactions`
--
ALTER TABLE `parcel_payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parcel_id` (`parcel_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `parcel_returns`
--
ALTER TABLE `parcel_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parcel_id` (`parcel_id`),
  ADD KEY `idx_return_date` (`return_date`);

--
-- Indexes for table `parcel_status`
--
ALTER TABLE `parcel_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcel_status_history`
--
ALTER TABLE `parcel_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parcel_history_parcel` (`parcel_id`),
  ADD KEY `idx_parcel_history_status` (`old_status_id`,`new_status_id`),
  ADD KEY `idx_parcel_history_reason` (`reason_id`);

--
-- Indexes for table `parcel_status_logs`
--
ALTER TABLE `parcel_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parcel_id` (`parcel_id`),
  ADD KEY `courier_id` (`courier_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `parcel_status_reasons`
--
ALTER TABLE `parcel_status_reasons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_status_reason` (`status_id`,`reason_code`),
  ADD KEY `idx_status_reasons_status` (`status_id`);

--
-- Indexes for table `parcel_tracks`
--
ALTER TABLE `parcel_tracks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments_log`
--
ALTER TABLE `payments_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_id` (`agent_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `transaction_number` (`transaction_number`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_amount` (`amount`);

--
-- Indexes for table `pending_payouts`
--
ALTER TABLE `pending_payouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_party` (`party_type`,`party_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_parcel_id` (`parcel_id`);

--
-- Indexes for table `profit_reports`
--
ALTER TABLE `profit_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parcel_id` (`parcel_id`),
  ADD KEY `idx_profit_margin` (`profit_margin`),
  ADD KEY `idx_calculated_at` (`calculated_at`);

--
-- Indexes for table `settlements`
--
ALTER TABLE `settlements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity_type` (`entity_type`,`entity_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wallet` (`party_type`,`party_id`),
  ADD KEY `idx_party_type` (`party_type`),
  ADD KEY `idx_balance` (`balance`);

--
-- Indexes for table `whatsapp_config`
--
ALTER TABLE `whatsapp_config`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agents`
--
ALTER TABLE `agents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `agent_payments`
--
ALTER TABLE `agent_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `agent_transactions`
--
ALTER TABLE `agent_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `agent_users`
--
ALTER TABLE `agent_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `branch_cashbox_transactions`
--
ALTER TABLE `branch_cashbox_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cashbox_logs`
--
ALTER TABLE `cashbox_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cashbox_transactions`
--
ALTER TABLE `cashbox_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `couriers`
--
ALTER TABLE `couriers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `courier_auth_tokens`
--
ALTER TABLE `courier_auth_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_accounts`
--
ALTER TABLE `customer_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_balances`
--
ALTER TABLE `customer_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_logs`
--
ALTER TABLE `customer_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_monthly_reports`
--
ALTER TABLE `customer_monthly_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_parcels`
--
ALTER TABLE `customer_parcels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_payments`
--
ALTER TABLE `customer_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_payment_details`
--
ALTER TABLE `customer_payment_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_payment_settings`
--
ALTER TABLE `customer_payment_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_ratings`
--
ALTER TABLE `customer_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_statements`
--
ALTER TABLE `customer_statements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `free_notification_logs`
--
ALTER TABLE `free_notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `governorates`
--
ALTER TABLE `governorates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_settings`
--
ALTER TABLE `notification_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parcels`
--
ALTER TABLE `parcels`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `parcels_notes`
--
ALTER TABLE `parcels_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `parcel_collections`
--
ALTER TABLE `parcel_collections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `parcel_history`
--
ALTER TABLE `parcel_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parcel_notes`
--
ALTER TABLE `parcel_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parcel_payment_history`
--
ALTER TABLE `parcel_payment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parcel_payment_transactions`
--
ALTER TABLE `parcel_payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parcel_returns`
--
ALTER TABLE `parcel_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parcel_status`
--
ALTER TABLE `parcel_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `parcel_status_history`
--
ALTER TABLE `parcel_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `parcel_status_logs`
--
ALTER TABLE `parcel_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parcel_status_reasons`
--
ALTER TABLE `parcel_status_reasons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `parcel_tracks`
--
ALTER TABLE `parcel_tracks`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `payments_log`
--
ALTER TABLE `payments_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pending_payouts`
--
ALTER TABLE `pending_payouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profit_reports`
--
ALTER TABLE `profit_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settlements`
--
ALTER TABLE `settlements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wallets`
--
ALTER TABLE `wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_config`
--
ALTER TABLE `whatsapp_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `agent_payments`
--
ALTER TABLE `agent_payments`
  ADD CONSTRAINT `agent_payments_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `agent_transactions`
--
ALTER TABLE `agent_transactions`
  ADD CONSTRAINT `agent_transactions_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_transactions_ibfk_2` FOREIGN KEY (`shipment_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `agent_users`
--
ALTER TABLE `agent_users`
  ADD CONSTRAINT `agent_users_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `areas`
--
ALTER TABLE `areas`
  ADD CONSTRAINT `areas_ibfk_1` FOREIGN KEY (`governorate_id`) REFERENCES `governorates` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `couriers`
--
ALTER TABLE `couriers`
  ADD CONSTRAINT `couriers_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- قيود الجداول `courier_auth_tokens`
--
ALTER TABLE `courier_auth_tokens`
  ADD CONSTRAINT `fk_cat_courier` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `customer_balances`
--
ALTER TABLE `customer_balances`
  ADD CONSTRAINT `customer_balances_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `customer_parcels`
--
ALTER TABLE `customer_parcels`
  ADD CONSTRAINT `customer_parcels_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_parcels_ibfk_2` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `customer_payment_details`
--
ALTER TABLE `customer_payment_details`
  ADD CONSTRAINT `customer_payment_details_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `customer_payments` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `customer_payment_settings`
--
ALTER TABLE `customer_payment_settings`
  ADD CONSTRAINT `customer_payment_settings_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `customer_statements`
--
ALTER TABLE `customer_statements`
  ADD CONSTRAINT `fk_customer_statements_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `parcels`
--
ALTER TABLE `parcels`
  ADD CONSTRAINT `fk_parcels_customer` FOREIGN KEY (`client_id_fk`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `parcels_notes`
--
ALTER TABLE `parcels_notes`
  ADD CONSTRAINT `parcels_notes_ibfk_1` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`),
  ADD CONSTRAINT `parcels_notes_ibfk_2` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`id`);

--
-- قيود الجداول `parcel_collections`
--
ALTER TABLE `parcel_collections`
  ADD CONSTRAINT `fk_pc_courier` FOREIGN KEY (`collected_by_courier_id`) REFERENCES `couriers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pc_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `parcel_payment_history`
--
ALTER TABLE `parcel_payment_history`
  ADD CONSTRAINT `fk_parcel_payment_history_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `parcel_payment_transactions`
--
ALTER TABLE `parcel_payment_transactions`
  ADD CONSTRAINT `fk_parcel_payment_transactions_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `parcel_returns`
--
ALTER TABLE `parcel_returns`
  ADD CONSTRAINT `fk_parcel_returns_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `pending_payouts`
--
ALTER TABLE `pending_payouts`
  ADD CONSTRAINT `pending_payouts_ibfk_1` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `profit_reports`
--
ALTER TABLE `profit_reports`
  ADD CONSTRAINT `profit_reports_ibfk_1` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
