jQuery(document).ready(function($) {
    let proformaItems = [];
    const proformaContainer = $('#bpi-proforma-items');
    const quantityContainer = $('#bpi-quantity-items');
    const totalElement = $('#bpi-total-price');
    const userForm = $('#bpi-user-info-form');
    const otherPersonInfo = $('#bpi-other-person-info');
    const forSomeoneElse = $('#bpi-for-someone-else');
    const buyerCity = $('#bpi-buyer-city');
    const userCity = $('#bpi-city');
    const locationNote = $('#bpi-location-note');
    const productGrid = $('#bpi-product-grid');
    const productSearch = $('#bpi-product-search');
    const productCategory = $('#bpi-product-category');
    
    // مدیریت مراحل
    const steps = $('.bpi-step');
    const stepContents = $('.bpi-step-content');
    let currentStep = 1;
    
    // بارگذاری پیش فاکتور از sessionStorage در صورت وجود
    const savedProforma = sessionStorage.getItem('bpi_proformaItems');
    if (savedProforma) {
        proformaItems = JSON.parse(savedProforma);
        updateProformaDisplay();
        updateStep2Display();
        checkNextButton();
    }
    
    // تغییر مرحله
    function goToStep(step) {
        // مخفی کردن تمام مراحل
        stepContents.removeClass('active');
        steps.removeClass('active');
        
        // نمایش مرحله مورد نظر
        $(`.bpi-step-content[data-step="${step}"]`).addClass('active');
        $(`.bpi-step[data-step="${step}"]`).addClass('active');
        
        currentStep = step;
        
        // اگر به مرحله 2 رفتیم، محصولات را نمایش بده
        if (step === 2) {
            updateStep2Display();
        }
    }
    
    // کلیک روی دکمه‌های下一步 و قبلی
    $(document).on('click', '.bpi-next-btn', function() {
        const nextStep = currentStep + 1;
        goToStep(nextStep);
    });
    
    $(document).on('click', '.bpi-prev-btn', function() {
        const prevStep = $(this).data('step');
        goToStep(prevStep);
    });
    
    // بارگذاری محصولات از ووکامرس
    function loadProducts(search = '', category = '') {
        productGrid.html('<div class="bpi-loading">در حال بارگذاری محصولات...</div>');
        
        $.ajax({
            url: bpi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bpi_get_products',
                nonce: bpi_ajax.nonce,
                search: search,
                category: category
            },
            success: function(response) {
                if (response.success) {
                    displayProducts(response.data);
                } else {
                    productGrid.html('<div class="bpi-empty-message">خطا در بارگذاری محصولات</div>');
                }
            },
            error: function() {
                productGrid.html('<div class="bpi-empty-message">خطا در ارتباط با سرور</div>');
            }
        });
    }
    
    // نمایش محصولات در grid
    function displayProducts(products) {
        if (products.length === 0) {
            productGrid.html('<div class="bpi-empty-message">محصولی یافت نشد</div>');
            return;
        }
        
        let html = '';
        
        products.forEach(product => {
            // بررسی آیا محصول قبلاً اضافه شده
            const isAdded = proformaItems.some(item => item.id === product.id);
            const buttonText = isAdded ? '✓ افزوده شد' : bpi_ajax.i18n.add_to_proforma;
            const buttonStyle = isAdded ? 'background-color: #27ae60;' : '';
            
            html += `
                <div class="bpi-product-card">
                    <img src="${product.image || 'https://via.placeholder.com/300x150?text=بدون+تصویر'}" class="bpi-product-image" alt="${product.name}">
                    <h3 class="bpi-product-title">${product.name}</h3>
                    <div class="bpi-product-price">${formatPrice(product.price)}</div>
                    <button class="bpi-add-button" data-id="${product.id}" data-name="${product.name}" data-price="${product.price}" data-max="${product.max_qty || 100}" style="${buttonStyle}">
                        ${buttonText}
                    </button>
                </div>
            `;
        });
        
        productGrid.html(html);
    }
    
    // فرمت قیمت
    function formatPrice(price) {
        return new Intl.NumberFormat('fa-IR').format(price) + ' تومان';
    }
    
    // بارگذاری اولیه محصولات
    loadProducts();
    
    // جستجوی محصولات
    productSearch.on('input', function() {
        loadProducts($(this).val(), productCategory.val());
    });
    
    // فیلتر بر اساس دسته‌بندی
    productCategory.on('change', function() {
        loadProducts(productSearch.val(), $(this).val());
    });
    
    // افزودن محصول به پیش فاکتور
    $(document).on('click', '.bpi-add-button', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const price = parseFloat($(this).data('price'));
        const maxQty = parseInt($(this).data('max')) || 100;
        
        // بررسی آیا محصول از قبل وجود دارد
        const existingIndex = proformaItems.findIndex(item => item.id === id);
        
        if (existingIndex >= 0) {
            // اگر محصول وجود دارد، حذفش کن
            proformaItems.splice(existingIndex, 1);
            $(this).text(bpi_ajax.i18n.add_to_proforma);
            $(this).css('background-color', '#3498db');
        } else {
            // اگر محصول وجود ندارد، اضافه‌اش کن
            proformaItems.push({
                id,
                name,
                price,
                quantity: 1,
                maxQuantity: maxQty
            });
            $(this).text('✓ افزوده شد');
            $(this).css('background-color', '#27ae60');
        }
        
        // ذخیره در sessionStorage
        sessionStorage.setItem('bpi_proformaItems', JSON.stringify(proformaItems));
        updateProformaDisplay();
        checkNextButton();
    });
    
    // بررسی فعال بودن دکمه下一步
    function checkNextButton() {
        const nextButton = $('#bpi-to-step-2');
        if (proformaItems.length > 0) {
            nextButton.prop('disabled', false);
        } else {
            nextButton.prop('disabled', true);
        }
    }
    
    // به روزرسانی نمایش پیش فاکتور
    function updateProformaDisplay() {
        if (proformaItems.length === 0) {
            proformaContainer.html('<div class="bpi-empty-message">هیچ محصولی انتخاب نشده است</div>');
            return;
        }
        
        let html = '';
        let total = 0;
        
        proformaItems.forEach(item => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            
            html += `
                <div class="bpi-proforma-item">
                    <div>
                        <div>${item.name}</div>
                        <div style="font-size: 12px; color: #7f8c8d;">${formatPrice(item.price)} × ${item.quantity}</div>
                    </div>
                    <div>${formatPrice(itemTotal)}</div>
                </div>
            `;
        });
        
        html += `
            <div class="bpi-proforma-total">
                <span>جمع کل:</span>
                <span>${formatPrice(total)}</span>
            </div>
        `;
        
        proformaContainer.html(html);
    }
    
    // نمایش مرحله 2 (تعیین تعداد)
    function updateStep2Display() {
        if (proformaItems.length === 0) {
            quantityContainer.html('<div class="bpi-empty-message">هیچ محصولی انتخاب نشده است</div>');
            return;
        }
        
        let html = '<div class="bpi-quantity-list">';
        
        proformaItems.forEach((item, index) => {
            html += `
                <div class="bpi-quantity-item">
                    <div class="bpi-quantity-info">
                        <h4>${item.name}</h4>
                        <div class="bpi-product-price">${formatPrice(item.price)}</div>
                    </div>
                    <div class="bpi-quantity-controls">
                        <label>${bpi_ajax.i18n.quantity}:</label>
                        <input type="number" class="bpi-quantity-input" 
                               data-index="${index}" 
                               value="${item.quantity}" 
                               min="1" 
                               max="${item.maxQuantity}" 
                               onkeyup="updateQuantity(${index}, this.value)">
                        <button class="bpi-update-btn" onclick="updateQuantity(${index})">${bpi_ajax.i18n.update}</button>
                    </div>
                    <div class="bpi-item-total">${formatPrice(item.price * item.quantity)}</div>
                </div>
            `;
        });
        
        // محاسبه جمع کل
        const total = proformaItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        html += `
            <div class="bpi-quantity-total">
                <strong>جمع کل: ${formatPrice(total)}</strong>
            </div>
        </div>`;
        
        quantityContainer.html(html);
    }
    
    // تابع global برای آپدیت تعداد
    window.updateQuantity = function(index, value = null) {
        if (value !== null) {
            const quantity = parseInt(value) || 1;
            const maxQty = proformaItems[index].maxQuantity;
            
            if (quantity > 0 && quantity <= maxQty) {
                proformaItems[index].quantity = quantity;
            }
        }
        
        // ذخیره در sessionStorage
        sessionStorage.setItem('bpi_proformaItems', JSON.stringify(proformaItems));
        
        // refresh نمایش
        updateStep2Display();
    };
    
    // مدیریت چک باکس "پیش فاکتور برای شخص دیگری است"
    forSomeoneElse.on('change', function() {
        if (this.checked) {
            otherPersonInfo.removeClass('bpi-hidden');
            // پیش‌فرض شهر خریدار همان شهر کاربر است
            buyerCity.val(userCity.val());
            // به روزرسانی پیام
            updateLocationNote();
        } else {
            otherPersonInfo.addClass('bpi-hidden');
        }
    });
    
    // به روزرسانی پیام موقعیت
    function updateLocationNote() {
        if (userCity.val() && buyerCity.val()) {
            if (userCity.val() !== buyerCity.val()) {
                locationNote.text(`توجه: شهر خریدار (${buyerCity.val()}) با شهر شما (${userCity.val()}) متفاوت است`);
            } else {
                locationNote.text('شهر خریدار با شهر شما یکسان است. در صورت تفاوت، لطفاً تصحیح کنید.');
            }
        } else {
            locationNote.text('اگر لوکیشن خریدار متفاوت از شهر شماست، لطفاً مشخص کنید');
        }
    }
    
    // ردیابی تغییرات شهر کاربر و خریدار
    userCity.on('input', function() {
        if (forSomeoneElse.is(':checked') && buyerCity.val() === '') {
            buyerCity.val($(this).val());
        }
        updateLocationNote();
    });
    
    buyerCity.on('input', updateLocationNote);
    
    // ثبت درخواست پیش فاکتور
    $(document).on('click', '#bpi-submit-request', function(e) {
        e.preventDefault();
        
        // اعتبارسنجی فرم
        const fullname = $('#bpi-fullname').val();
        const city = $('#bpi-city').val();
        const email = $('#bpi-email').val();
        const phone = $('#bpi-phone').val();
        const invoiceType = $('#bpi-invoice-type').val();
        
        if (!fullname || !city || !email || !phone || !invoiceType) {
            alert('لطفاً تمام فیلدهای اجباری را پر کنید.');
            return;
        }
        
        if (forSomeoneElse.is(':checked')) {
            const buyerName = $('#bpi-buyer-name').val();
            if (!buyerName) {
                alert('لطفاً نام خریدار نهایی را وارد کنید.');
                return;
            }
        }
        
        // جمع‌آوری اطلاعات
        const userInfo = {
            fullname: fullname,
            city: city,
            email: email,
            phone: phone,
            invoiceType: invoiceType,
            forSomeoneElse: forSomeoneElse.is(':checked'),
            items: proformaItems
        };
        
        if (forSomeoneElse.is(':checked')) {
            userInfo.buyerName = $('#bpi-buyer-name').val();
            userInfo.buyerCity = $('#bpi-buyer-city').val() || city;
        }
        
        // ارسال درخواست به سرور
        $.ajax({
            url: bpi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bpi_submit_request',
                nonce: bpi_ajax.nonce,
                user_info: userInfo
            },
            success: function(response) {
                if (response.success) {
                    // رفتن به مرحله 4 (تأیید نهایی)
                    goToStep(4);
                    
                    // پاک کردن sessionStorage
                    sessionStorage.removeItem('bpi_proformaItems');
                } else {
                    alert('خطا در ثبت درخواست: ' + response.data);
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.');
            }
        });
    });
    
    // دکمه ثبت درخواست جدید
    $(document).on('click', '#bpi-new-request', function() {
        // پاک کردن تمام داده‌ها
        proformaItems = [];
        sessionStorage.removeItem('bpi_proformaItems');
        
        // ریست کردن فرم
        $('#bpi-user-info-form')[0].reset();
        otherPersonInfo.addClass('bpi-hidden');
        forSomeoneElse.prop('checked', false);
        
        // بازگشت به مرحله اول
        goToStep(1);
        
        // بارگذاری مجدد محصولات
        loadProducts();
    });
    
    // دکمه پاک کردن پیش فاکتور
    $(document).on('click', '#bpi-clear-proforma', function() {
        if (proformaItems.length === 0) {
            alert('پیش فاکتور شما در حال حاضر خالی است!');
            return;
        }
        
        if (confirm(bpi_ajax.i18n.confirm_clear)) {
            proformaItems = [];
            sessionStorage.removeItem('bpi_proformaItems');
            updateProformaDisplay();
            checkNextButton();
            alert('پیش فاکتور با موفقیت پاک شد.');
        }
    });
});

// توابع global برای دسترسی از HTML
function updateQuantity(index, value = null) {
    // پیدا کردن المنت ورودی
    const inputElement = document.querySelector(`.bpi-quantity-input[data-index="${index}"]`);
    if (!inputElement) return;
    
    // بررسی مرورگر Edge و اصلاح مقدار اگر لازم است
    let quantity = parseInt(value) || 1;
    if (navigator.userAgent.includes('Edge')) {
        // برای Edge، مطمئن شویم مقدار عددی است
        quantity = isNaN(quantity) ? 1 : Math.max(1, quantity);
        inputElement.value = quantity;
    }
    
    // به روزرسانی مقدار در آرایه
    if (window.proformaItems && window.proformaItems[index]) {
        const maxQty = window.proformaItems[index].maxQuantity;
        if (quantity > 0 && quantity <= maxQty) {
            window.proformaItems[index].quantity = quantity;
            
            // ذخیره در sessionStorage
            sessionStorage.setItem('bpi_proformaItems', JSON.stringify(window.proformaItems));
            
            // refresh نمایش
            const event = new Event('input');
            inputElement.dispatchEvent(event);
        }
    }
}
