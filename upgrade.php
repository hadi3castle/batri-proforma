<?php
/**
 * Upgrade routines for Batri Proforma Invoice
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * بررسی و اجرای ارتقاءهای لازم
 */
function bpi_check_upgrade() {
    $current_version = get_option('bpi_version', '1.0.0');
    $db_version = get_option('bpi_db_version', '1.0');
    
    // ارتقاء از نسخه 1.0.0 به 1.0.1
    if (version_compare($current_version, '1.0.1', '<')) {
        bpi_upgrade_to_101();
        update_option('bpi_version', '1.0.1');
    }
    
    // ارتقاء از نسخه دیتابیس 1.0 به 1.1
    if (version_compare($db_version, '1.1', '<')) {
        bpi_upgrade_db_to_11();
        update_option('bpi_db_version', '1.1');
    }
}
add_action('admin_init', 'bpi_check_upgrade');

/**
 * ارتقاء به نسخه 1.0.1
 */
function bpi_upgrade_to_101() {
    global $wpdb;
    
    // ایجاد جدول برای ذخیره لاگ‌ها
    $table_name = $wpdb->prefix . 'bpi_logs';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        log_type varchar(50) NOT NULL,
        message text NOT NULL,
        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // ثبت لاگ ارتقاء
    error_log('Batri Proforma upgraded to version 1.0.1');
}

/**
 * ارتقاء دیتابیس به نسخه 1.1
 */
function bpi_upgrade_db_to_11() {
    global $wpdb;
    
    // اضافه کردن فیلد جدید به جدول درخواست‌ها
    $table_name = $wpdb->prefix . 'bpi_requests';
    
    // بررسی وجود فیلد updated_at
    $column_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = %s AND COLUMN_NAME = 'updated_at'",
            $table_name
        )
    );
    
    if (!$column_exists) {
        $wpdb->query("ALTER TABLE $table_name ADD updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL AFTER created_at");
    }
    
    // ثبت لاگ ارتقاء
    error_log('Batri Proforma database upgraded to version 1.1');
}

/**
 * ایجاد پشتیبان قبل از ارتقاء
 */
function bpi_pre_upgrade_backup() {
    // فقط اگر داده‌هایی وجود دارد پشتیبان بگیرید
    global $wpdb;
    $table_name = $wpdb->prefix . 'bpi_requests';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    if ($count > 0) {
        $backup = array(
            'version' => get_option('bpi_version', '1.0.0'),
            'db_version' => get_option('bpi_db_version', '1.0'),
            'created' => current_time('timestamp'),
            'description' => 'پشتیبان قبل از ارتقاء - ' . date('Y-m-d H:i:s'),
            'count' => $count,
            'data' => $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A)
        );
        
        $backups = get_option('bpi_upgrade_backups', array());
        $backups[] = $backup;
        update_option('bpi_upgrade_backups', $backups);
    }
}
register_activation_hook(__FILE__, 'bpi_pre_upgrade_backup');
