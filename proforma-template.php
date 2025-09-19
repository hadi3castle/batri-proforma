<div class="bpi-container">
    <div class="bpi-header">
        <h1>سیستم پیش فاکتور دو مرحله‌ای Batri</h1>
        <p>ثبت درخواست پیش فاکتور برای تأمین‌کنندگان</p>
    </div>
    
    <!-- نمایش مراحل -->
    <div class="bpi-steps">
        <div class="bpi-step active" data-step="1">
            <span class="bpi-step-number">1</span>
            <span class="bpi-step-title">انتخاب محصولات</span>
        </div>
        <div class="bpi-step" data-step="2">
            <span class="bpi-step-number">2</span>
            <span class="bpi-step-title">تعیین تعداد</span>
        </div>
        <div class="bpi-step" data-step="3">
            <span class="bpi-step-number">3</span>
            <span class="bpi-step-title">اطلاعات کاربر</span>
        </div>
        <div class="bpi-step" data-step="4">
            <span class="bpi-step-number">4</span>
            <span class="bpi-step-title">ثبت نهایی</span>
        </div>
    </div>
    
    <!-- مرحله 1: انتخاب محصولات -->
    <div class="bpi-step-content active" data-step="1">
        <div class="bpi-products-container">
            <div class="bpi-products">
                <h2>محصولات</h2>
                
                <!-- فیلترهای محصولات -->
                <div class="bpi-filters">
                    <div class="bpi-form-group">
                        <input type="text" id="bpi-product-search" placeholder="جستجوی محصولات..." class="bpi-search-input">
                    </div>
                    
                    <div class="bpi-form-group">
                        <select id="bpi-product-category">
                            <option value="">همه دسته‌بندی‌ها</option>
                            <?php
                            $categories = get_terms('product_cat', array('hide_empty' => true));
                            foreach ($categories as $category) {
                                echo '<option value="' . $category->slug . '">' . $category->name . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="bpi-product-grid" id="bpi-product-grid">
                    <!-- محصولات به صورت AJAX بارگذاری خواهند شد -->
                    <div class="bpi-loading">در حال بارگذاری محصولات...</div>
                </div>
            </div>
            
            <div class="bpi-proforma">
                <h2 class="bpi-proforma-title">محصولات انتخاب شده</h2>
                <div id="bpi-proforma-items">
                    <div class="bpi-empty-message">هیچ محصولی انتخاب نشده است</div>
                </div>
                
                <div class="bpi-action-buttons">
                    <button class="bpi-action-button bpi-next-btn" id="bpi-to-step-2" disabled>مرحله بعد (تعیین تعداد)</button>
                    <button class="bpi-action-button bpi-clear-btn" id="bpi-clear-proforma">پاک کردن پیش فاکتور</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- مرحله 2: تعیین تعداد -->
    <div class="bpi-step-content" data-step="2">
        <h2>تعیین تعداد محصولات</h2>
        <div id="bpi-quantity-items">
            <!-- محصولات با فیلد تعداد نمایش داده می‌شوند -->
        </div>
        
        <div class="bpi-action-buttons">
            <button class="bpi-action-button bpi-prev-btn" data-step="1">مرحله قبل</button>
            <button class="bpi-action-button bpi-next-btn" id="bpi-to-step-3">مرحله بعد (اطلاعات کاربر)</button>
        </div>
    </div>
    
    <!-- مرحله 3: اطلاعات کاربر -->
    <div class="bpi-step-content" data-step="3">
        <h2>اطلاعات درخواست پیش فاکتور</h2>
        
        <div class="bpi-form-group">
            <label for="bpi-fullname">نام و نام خانوادگی *</label>
            <input type="text" id="bpi-fullname" required placeholder="نام کامل خود را وارد کنید">
        </div>
        
        <div class="bpi-form-group">
            <label for="bpi-city">شهر *</label>
            <input type="text" id="bpi-city" required placeholder="شهر خود را وارد کنید">
        </div>
        
        <div class="bpi-form-group">
            <label for="bpi-email">ایمیل *</label>
            <input type="email" id="bpi-email" required placeholder="ایمیل معتبر وارد کنید">
        </div>
        
        <div class="bpi-form-group">
            <label for="bpi-phone">شماره تماس *</label>
            <input type="tel" id="bpi-phone" required placeholder="شماره تماس خود را وارد کنید">
        </div>
        
        <div class="bpi-form-group">
            <label for="bpi-invoice-type">نوع پیش فاکتور *</label>
            <select id="bpi-invoice-type" required>
                <option value="">انتخاب کنید</option>
                <option value="official">رسمی</option>
                <option value="unofficial">غیر رسمی</option>
            </select>
        </div>
        
        <div class="bpi-checkbox-group">
            <input type="checkbox" id="bpi-for-someone-else">
            <label for="bpi-for-someone-else">پیش فاکتور برای شخص دیگری است</label>
        </div>
        
        <div id="bpi-other-person-info" class="bpi-hidden">
            <h3 style="margin-bottom: 12px; font-size: 16px;">اطلاعات خریدار نهایی</h3>
            
            <div class="bpi-form-group">
                <label for="bpi-buyer-name">نام خریدار *</label>
                <input type="text" id="bpi-buyer-name" placeholder="نام خریدار نهایی">
            </div>
            
            <div class="bpi-form-group">
                <label for="bpi-buyer-city">شهر خریدار</label>
                <input type="text" id="bpi-buyer-city" placeholder="شهر خریدار">
                <span class="bpi-location-note" id="bpi-location-note">اگر لوکیشن خریدار متفاوت از شهر شماست، لطفاً مشخص کنید</span>
            </div>
        </div>
        
        <div class="bpi-action-buttons">
            <button class="bpi-action-button bpi-prev-btn" data-step="2">مرحله قبل</button>
            <button class="bpi-action-button bpi-request-btn" id="bpi-submit-request">ثبت درخواست پیش فاکتور</button>
        </div>
    </div>
    
    <!-- مرحله 4: تأیید نهایی -->
    <div class="bpi-step-content" data-step="4">
        <div class="bpi-success-message">
            <h2>✅ درخواست با موفقیت ثبت شد</h2>
            <p>با تشکر از شما! درخواست پیش فاکتور با موفقیت ثبت شد.</p>
            <p>همکاران ما در اسرع وقت با شما تماس خواهند گرفت.</p>
            <button class="bpi-action-button" id="bpi-new-request">ثبت درخواست جدید</button>
        </div>
    </div>
</div>
