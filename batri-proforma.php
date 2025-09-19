<?php
/**
 * Plugin Name: Batri Proforma Invoice
 * Description: سیستم پیش فاکتور دو مرحله‌ای برای batri.co
 * Version: 1.2.1
 * Author: Batri Co
 * License: GPL v2 or later
 * Text Domain: batri-proforma
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// بررسی فعال بودن ووکامرس
function bpi_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'bpi_woocommerce_notice');
        return false;
    }
    return true;
}

function bpi_woocommerce_notice() {
    echo '<div class="error"><p>پلاگین Batri Proforma Invoice نیاز به ووکامرس دارد. لطفاً ووکامرس را نصب و فعال کنید.</p></div>';
}

// تعریف ثابت‌های مورد نیاز
define('BPI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BPI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BPI_VERSION', '1.2.1');
define('BPI_DB_VERSION', '1.2');

class BatriProformaInvoice {
    
    public function __construct() {
        // بررسی وجود ووکامرس
        if (!bpi_check_woocommerce()) {
            return;
        }
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('batri_proforma', array($this, 'proforma_shortcode'));
        
        // ثبت هوک‌های AJAX
        add_action('wp_ajax_bpi_submit_request', array($this, 'submit_request'));
        add_action('wp_ajax_nopriv_bpi_submit_request', array($this, 'submit_request'));
        add_action('wp_ajax_bpi_get_products', array($this, 'get_products'));
        add_action('wp_ajax_nopriv_bpi_get_products', array($this, 'get_products'));
        
        // ایجاد منوی مدیریت
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // بررسی وجود جدول در دیتابیس
        add_action('admin_init', array($this, 'check_table_exists'));
        
        // ثبت هوک برای بروزرسانی وضعیت
        add_action('admin_post_bpi_update_status', array($this, 'update_status'));
        
        // ثبت هوک برای پشتیبان‌گیری
        add_action('admin_post_bpi_backup', array($this, 'create_backup'));
        
        // ثبت هوک برای بازگردانی
        add_action('admin_post_bpi_restore', array($this, 'restore_backup'));
        
        // اضافه کردن لینک‌های سریع در صفحه پلاگین‌ها
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_links'));
        
        // ثبت هوک برای حذف پشتیبان
        add_action('admin_post_bpi_backup_delete', array($this, 'delete_backup'));
        
        // هوک برای بارگذاری محصولات در frontend
        add_action('wp_loaded', array($this, 'load_woocommerce_products'));
    }
    
    public function init() {
        // شروع session در صورت نیاز
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        
        // ایجاد جدول در دیتابیس هنگام فعال‌سازی پلاگین
        register_activation_hook(__FILE__, array($this, 'create_table'));
        
        // بارگذاری textdomain برای ترجمه
        load_plugin_textdomain('batri-proforma', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_scripts() {
        // استایل‌ها
        wp_enqueue_style('bpi-style', BPI_PLUGIN_URL . 'assets/css/style.css');
        
        // اسکریپت‌ها
        wp_enqueue_script('bpi-script', BPI_PLUGIN_URL . 'assets/js/script.js', array('jquery'), BPI_VERSION, true);
        
        // انتقال داده به جاوااسکریپت
        wp_localize_script('bpi-script', 'bpi_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bpi_nonce'),
            'products' => $this->get_products_data(),
            'i18n' => array(
                'add_to_proforma' => __('افزودن به پیش فاکتور', 'batri-proforma'),
                'quantity' => __('تعداد', 'batri-proforma'),
                'update' => __('بروزرسانی', 'batri-proforma'),
                'next' => __('مرحله بعد', 'batri-proforma'),
                'previous' => __('مرحله قبل', 'batri-proforma'),
                'confirm_clear' => __('آیا از پاک کردن پیش فاکتور اطمینان دارید؟', 'batri-proforma')
            )
        ));
    }
    
    public function load_woocommerce_products() {
        // بارگذاری محصولات ووکامرس در session برای دسترسی سریع‌تر
        if (!isset($_SESSION['bpi_products'])) {
            $_SESSION['bpi_products'] = $this->get_products_data();
        }
    }
    
    public function get_products_data() {
        $products = array();
        
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $products_query = new WP_Query($args);
        
        if ($products_query->have_posts()) {
            while ($products_query->have_posts()) {
                $products_query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product && $product->is_visible() && $product->is_in_stock()) {
                    $products[] = array(
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'price' => $product->get_price(),
                        'regular_price' => $product->get_regular_price(),
                        'sale_price' => $product->get_sale_price(),
                        'image' => wp_get_attachment_url($product->get_image_id()),
                        'description' => $product->get_short_description(),
                        'permalink' => get_permalink(),
                        'sku' => $product->get_sku(),
                        'stock_quantity' => $product->get_stock_quantity(),
                        'in_stock' => $product->is_in_stock(),
                        'max_qty' => $product->get_max_purchase_quantity() > 0 ? $product->get_max_purchase_quantity() : 100
                    );
                }
            }
            wp_reset_postdata();
        }
        
        return $products;
    }
    
    public function get_products() {
        // بررسی nonce برای امنیت
        if (!wp_verify_nonce($_POST['nonce'], 'bpi_nonce')) {
            wp_send_json_error('خطای امنیتی');
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            's' => $search
        );
        
        // فیلتر بر اساس دسته‌بندی
        if (!empty($category)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $category
                )
            );
        }
        
        $products = array();
        $products_query = new WP_Query($args);
        
        if ($products_query->have_posts()) {
            while ($products_query->have_posts()) {
                $products_query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product && $product->is_visible()) {
                    $products[] = array(
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'price' => $product->get_price(),
                        'image' => wp_get_attachment_url($product->get_image_id()),
                        'permalink' => get_permalink(),
                        'max_qty' => $product->get_max_purchase_quantity() > 0 ? $product->get_max_purchase_quantity() : 100
                    );
                }
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success($products);
    }
    
    public function proforma_shortcode() {
        ob_start();
        include BPI_PLUGIN_PATH . 'templates/proforma-template.php';
        return ob_get_clean();
    }
    
    public function submit_request() {
        // بررسی nonce برای امنیت
        if (!wp_verify_nonce($_POST['nonce'], 'bpi_nonce')) {
            wp_send_json_error('خطای امنیتی');
        }
        
        // دریافت اطلاعات
        $user_info = $_POST['user_info'];
        
        // شبیه‌سازی ذخیره در دیتابیس
        global $wpdb;
        $table_name = $wpdb->prefix . 'bpi_requests';
        
        $data = array(
            'user_info' => json_encode($user_info, JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            // ارسال ایمیل به ادمین
            $this->send_admin_email($user_info, $wpdb->insert_id);
            
            wp_send_json_success('درخواست با موفقیت ثبت شد');
        } else {
            wp_send_json_error('خطا در ثبت درخواست');
        }
    }
    
    private function send_admin_email($user_info, $request_id) {
        $to = get_option('admin_email');
        $subject = 'درخواست پیش فاکتور جدید - Batri.co #' . $request_id;
        $message = 'یک درخواست پیش فاکتور جدید ثبت شده است.' . "\n\n";
        $message .= 'جزئیات درخواست:' . "\n";
        $message .= 'نام: ' . $user_info['fullname'] . "\n";
        $message .= 'شهر: ' . $user_info['city'] . "\n";
        $message .= 'ایمیل: ' . $user_info['email'] . "\n";
        $message .= 'تلفن: ' . $user_info['phone'] . "\n";
        $message .= 'نوع پیش فاکتور: ' . ($user_info['invoiceType'] == 'official' ? 'رسمی' : 'غیر رسمی') . "\n\n";

        if ($user_info['forSomeoneElse']) {
            $message .= 'اطلاعات خریدار نهایی:' . "\n";
            $message .= 'نام خریدار: ' . $user_info['buyerName'] . "\n";
            $message .= 'شهر خریدار: ' . $user_info['buyerCity'] . "\n\n";
        }

        $message .= 'محصولات:' . "\n";
        $total = 0;
        
        foreach ($user_info['items'] as $item) {
            $product = wc_get_product($item['id']);
            $item_total = $item['price'] * $item['quantity'];
            $total += $item_total;
            
            $message .= '- ' . $item['name'] . ' (' . wc_price($item['price']) . ' × ' . $item['quantity'] . ') = ' . wc_price($item_total) . "\n";
        }

        $message .= "\n" . 'جمع کل: ' . wc_price($total) . "\n\n";
        $message .= 'برای مشاهده جزئیات کامل، به پنل مدیریت مراجعه کنید: ' . admin_url('admin.php?page=bpi-requests');

        wp_mail($to, $subject, $message);
    }
    
    public function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bpi_requests';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_info text NOT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function check_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bpi_requests';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // اگر جدول وجود ندارد، آن را ایجاد کن
            $this->create_table();
        }
    }
    
    public function admin_menu() {
        add_menu_page(
            'مدیریت پیش فاکتورها',
            'پیش فاکتورها',
            'manage_options',
            'bpi-requests',
            array($this, 'admin_page'),
            'dashicons-clipboard',
            30
        );
        
        // اضافه کردن زیرمنو برای پشتیبان‌گیری
        add_submenu_page(
            'bpi-requests',
            'پشتیبان‌گیری و بازگردانی',
            'پشتیبان‌گیری',
            'manage_options',
            'bpi-backup',
            array($this, 'backup_page')
        );
    }
    
    public function add_plugin_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=bpi-requests') . '">مدیریت درخواست‌ها</a>';
        $backup_link = '<a href="' . admin_url('admin.php?page=bpi-backup') . '">پشتیبان‌گیری</a>';
        array_push($links, $settings_link, $backup_link);
        return $links;
    }
    
    public function admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bpi_requests';
        
        // بررسی اگر action view است
        if (isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['id'])) {
            $request_id = intval($_GET['id']);
            $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $request_id));
            
            if ($request) {
                $user_info = json_decode($request->user_info, true);
                
                echo '<div class="wrap">';
                echo '<h1>مشاهده درخواست پیش فاکتور #' . $request->id . '</h1>';
                echo '<a href="' . admin_url('admin.php?page=bpi-requests') . '">← بازگشت به لیست</a>';
                echo '<div style="background:white;padding:20px;margin-top:20px;border-radius:5px;">';
                
                echo '<h2>اطلاعات کاربر</h2>';
                echo '<p><strong>نام:</strong> ' . esc_html($user_info['fullname']) . '</p>';
                echo '<p><strong>شهر:</strong> ' . esc_html($user_info['city']) . '</p>';
                echo '<p><strong>ایمیل:</strong> ' . esc_html($user_info['email']) . '</p>';
                echo '<p><strong>تلفن:</strong> ' . esc_html($user_info['phone']) . '</p>';
                echo '<p><strong>نوع پیش فاکتور:</strong> ' . ($user_info['invoiceType'] == 'official' ? 'رسمی' : 'غیر رسمی') . '</p>';
                
                if ($user_info['forSomeoneElse']) {
                    echo '<h2>اطلاعات خریدار نهایی</h2>';
                    echo '<p><strong>نام خریدار:</strong> ' . esc_html($user_info['buyerName']) . '</p>';
                    echo '<p><strong>شهر خریدار:</strong> ' . esc_html($user_info['buyerCity']) . '</p>';
                }
                
                echo '<h2>محصولات</h2>';
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>محصول</th><th>قیمت</th><th>تعداد</th><th>جمع</th></tr></thead>';
                echo '<tbody>';
                
                $total = 0;
                foreach ($user_info['items'] as $item) {
                    $item_total = $item['price'] * $item['quantity'];
                    $total += $item_total;
                    
                    echo '<tr>
                            <td>' . esc_html($item['name']) . '</td>
                            <td>' . number_format($item['price']) . ' تومان</td>
                            <td>' . $item['quantity'] . '</td>
                            <td>' . number_format($item_total) . ' تومان</td>
                        </tr>';
                }
                
                echo '<tr><td colspan="3"><strong>جمع کل:</strong></td><td><strong>' . number_format($total) . ' تومان</strong></td></tr>';
                echo '</tbody></table>';
                
                echo '<h2>تغییر وضعیت</h2>';
                echo '<form method="post" action="' . admin_url('admin-post.php') . '">
                        <input type="hidden" name="action" value="bpi_update_status">
                        <input type="hidden" name="request_id" value="' . $request->id . '">
                        <select name="status">
                            <option value="pending" ' . ($request->status == 'pending' ? 'selected' : '') . '>در انتظار</option>
                            <option value="processing" ' . ($request->status == 'processing' ? 'selected' : '') . '>در حال پردازش</option>
                            <option value="completed" ' . ($request->status == 'completed' ? 'selected' : '') . '>تکمیل شده</option>
                            <option value="cancelled" ' . ($request->status == 'cancelled' ? 'selected' : '') . '>لغو شده</option>
                        </select>
                        <input type="submit" class="button button-primary" value="بروزرسانی وضعیت">
                    </form>';
                
                echo '</div></div>';
                return;
            }
        }
        
        // دریافت درخواست‌ها
        $requests = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        
        echo '<div class="wrap">';
        echo '<h1>مدیریت درخواست‌های پیش فاکتور</h1>';
        
        if (!empty($requests)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>
                    <tr>
                        <th>ID</th>
                        <th>وضعیت</th>
                        <th>اطلاعات کاربر</th>
                        <th>تاریخ ثبت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($requests as $request) {
                $user_info = json_decode($request->user_info, true);
                echo '<tr>
                        <td>' . $request->id . '</td>
                        <td>' . $request->status . '</td>
                        <td>
                            <strong>نام:</strong> ' . esc_html($user_info['fullname']) . '<br>
                            <strong>شهر:</strong> ' . esc_html($user_info['city']) . '<br>
                            <strong>تلفن:</strong> ' . esc_html($user_info['phone']) . '
                        </td>
                        <td>' . $request->created_at . '</td>
                        <td>
                            <a href="?page=bpi-requests&action=view&id=' . $request->id . '">مشاهده</a> |
                            <a href="?page=bpi-requests&action=delete&id=' . $request->id . '" onclick="return confirm(\'آیا مطمئن هستید؟\')">حذف</a>
                        </td>
                    </tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>هیچ درخواستی یافت نشد.</p>';
        }
        
        echo '</div>';
    }
    
    public function update_status() {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bpi_requests';
        
        $request_id = intval($_POST['request_id']);
        $status = sanitize_text_field($_POST['status']);
        
        $wpdb->update(
            $table_name,
            array('status' => $status),
            array('id' => $request_id),
            array('%s'),
            array('%d')
        );
        
        wp_redirect(admin_url('admin.php?page=bpi-requests&action=view&id=' . $request_id));
        exit;
    }
    
    public function backup_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bpi_requests';
        
        // دریافت اطلاعات نسخه
        $current_version = get_option('bpi_version', '1.0.0');
        $db_version = get_option('bpi_db_version', '1.0');
        
        // دریافت تعداد درخواست‌ها
        $requests_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // نمایش پیام‌ها
        if (isset($_GET['message'])) {
            echo '<div class="notice notice-success is-dismissible">';
            switch ($_GET['message']) {
                case 'backup_created':
                    echo '<p>پشتیبان با موفقیت ایجاد شد.</p>';
                    break;
                case 'backup_restored':
                    $restored = isset($_GET['restored']) ? intval($_GET['restored']) : 0;
                    echo '<p>پشتیبان با موفقیت بازگردانی شد. ' . $restored . ' رکورد بازگردانی شد.</p>';
                    break;
                case 'backup_deleted':
                    echo '<p>پشتیبان با موفقیت حذف شد.</p>';
                    break;
            }
            echo '</div>';
        }
        
        echo '<div class="wrap">';
        echo '<h1>پشتیبان‌گیری و بازگردانی</h1>';
        
        echo '<div class="card">';
        echo '<h2>وضعیت سیستم</h2>';
        echo '<p>نسخه پلاگین: <strong>' . BPI_VERSION . '</strong></p>';
        echo '<p>نسخه دیتابیس: <strong>' . $db_version . '</strong></p>';
        echo '<p>تعداد درخواست‌های ثبت شده: <strong>' . $requests_count . '</strong></p>';
        echo '</div>';
        
        echo '<div class="card">';
        echo '<h2>ایجاد پشتیبان</h2>';
        echo '<p>با استفاده از این بخش می‌توانید از تمام درخواست‌های پیش فاکتور پشتیبان بگیرید.</p>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        echo '<input type="hidden" name="action" value="bpi_backup">';
        submit_button('ایجاد پشتیبان', 'primary', 'submit', false);
        echo '</form>';
        echo '</div>';
        
        echo '<div class="card">';
        echo '<h2>بازگردانی پشتیبان</h2>';
        echo '<p>اگر قبلاً پشتیبانی گرفته‌اید، می‌توانید آن را بازگردانی کنید.</p>';
        
        // بررسی وجود پشتیبان‌ها
        $backups = get_option('bpi_backups', array());
        
        if (!empty($backups)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>تاریخ</th><th>توضیحات</th><th>تعداد رکورد</th><th>عملیات</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($backups as $timestamp => $backup) {
                echo '<tr>';
                echo '<td>' . date('Y-m-d H:i:s', $timestamp) . '</td>';
                echo '<td>' . esc_html($backup['description']) . '</td>';
                echo '<td>' . $backup['count'] . ' درخواست</td>';
                echo '<td>';
                echo '<form method="post" action="' . admin_url('admin-post.php') . '" style="display:inline;">';
                echo '<input type="hidden" name="action" value="bpi_restore">';
                echo '<input type="hidden" name="backup_timestamp" value="' . $timestamp . '">';
                submit_button('بازگردانی', 'secondary', 'submit', false, array(
                    'onclick' => 'return confirm("آیا از بازگردانی این پشتیبان اطمینان دارید؟ داده‌های فعلی overwrite خواهند شد.")'
                ));
                echo '</form>';
                echo ' <form method="post" action="' . admin_url('admin-post.php') . '" style="display:inline;">';
                echo '<input type="hidden" name="action" value="bpi_backup_delete">';
                echo '<input type="hidden" name="backup_timestamp" value="' . $timestamp . '">';
                submit_button('حذف', 'delete', 'submit', false, array(
                    'onclick' => 'return confirm("آیا از حذف این پشتیبان اطمینان دارید？")'
                ));
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>هیچ پشتیبانی یافت نشد.</p>';
        }
        
        echo '</div>';
        
        echo '</div>';
    }
    
    public function create_backup() {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bpi_requests';
        
        // دریافت تمام درخواست‌ها
        $requests = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
        
        // ایجاد پشتیبان
        $backup = array(
            'version' => BPI_VERSION,
            'db_version' => BPI_DB_VERSION,
            'created' => current_time('timestamp'),
            'description' => 'پشتیبان خودکار - ' . date('Y-m-d H:i:s'),
            'count' => count($requests),
            'data' => $requests
        );
        
        // ذخیره پشتیبان
        $backups = get_option('bpi_backups', array());
        $timestamp = current_time('timestamp');
        $backups[$timestamp] = $backup;
        update_option('bpi_backups', $backups);
        
        // ثبت لاگ
        error_log('Batri Proforma Backup Created: ' . $timestamp . ' - ' . count($requests) . ' records');
        
        wp_redirect(admin_url('admin.php?page=bpi-backup&message=backup_created'));
        exit;
    }
    
    public function restore_backup() {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }
        
        if (!isset($_POST['backup_timestamp'])) {
            wp_die('پشتیبان مشخص نشده است');
        }
        
        $timestamp = intval($_POST['backup_timestamp']);
        $backups = get_option('bpi_backups', array());
        
        if (!isset($backups[$timestamp])) {
            wp_die('پشتیبان یافت نشد');
        }
        
        $backup = $backups[$timestamp];
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bpi_requests';
        
        // خالی کردن جدول فعلی
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        // بازگردانی داده‌ها
        $restored = 0;
        foreach ($backup['data'] as $row) {
            // حذف id برای درج به عنوان رکورد جدید
            unset($row['id']);
            
            $result = $wpdb->insert($table_name, $row);
            if ($result) {
                $restored++;
            }
        }
        
        // ثبت لاگ
        error_log('Batri Proforma Backup Restored: ' . $timestamp . ' - ' . $restored . ' records restored');
        
        wp_redirect(admin_url('admin.php?page=bpi-backup&message=backup_restored&restored=' . $restored));
        exit;
    }
    
    public function delete_backup() {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }
        
        if (!isset($_POST['backup_timestamp'])) {
            wp_die('پشتیبان مشخص نشده است');
        }
        
        $timestamp = intval($_POST['backup_timestamp']);
        $backups = get_option('bpi_backups', array());
        
        if (isset($backups[$timestamp])) {
            unset($backups[$timestamp]);
            update_option('bpi_backups', $backups);
            
            // ثبت لاگ
            error_log('Batri Proforma Backup Deleted: ' . $timestamp);
            
            wp_redirect(admin_url('admin.php?page=bpi-backup&message=backup_deleted'));
        } else {
            wp_die('پشتیبان یافت نشد');
        }
        
        exit;
    }
    
    public function log_event($type, $message) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bpi_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'log_type' => $type,
                'message' => $message,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
    }
}

// راه اندازی پلاگین
function init_batri_proforma() {
    new BatriProformaInvoice();
}
add_action('plugins_loaded', 'init_batri_proforma');

// اضافه کردن استایل‌های مدیریت
add_action('admin_head', function() {
    echo '<style>
        .bpi-backup-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .bpi-backup-card h2 {
            margin-top: 0;
        }
    </style>';
});
