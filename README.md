# بهنگین کریستال — نظام نقطة البيع
# Bangeen Crystal — POS System v1.0.0

---

## 🇮🇶 التثبيت (عربي)

### المتطلبات
- XAMPP (PHP 8.0+ مع GD extension)
- MySQL 5.7+
- متصفح حديث

### خطوات التثبيت

1. **نقل الملفات**
   ```
   انسخ مجلد hadaya-pos إلى:
   C:\xampp\htdocs\hadaya-pos\
   ```

2. **إنشاء قاعدة البيانات**
   - افتح XAMPP Control Panel → تشغيل Apache و MySQL
   - افتح المتصفح → http://localhost/phpmyadmin
   - اضغط "New" → اسم قاعدة البيانات: `bangeen_pos`
   - اختر Collation: `utf8mb4_unicode_ci`
   - افتح تبويب SQL → الصق محتوى ملف `setup.sql` → Run

3. **إعداد الاتصال بقاعدة البيانات**
   - افتح `includes/config.php`
   - تعديل إذا لزم:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'bangeen_pos');
     define('DB_USER', 'root');
     define('DB_PASS', '');  // كلمة مرور MySQL إن وجدت
     ```

4. **صلاحيات مجلد الباركود**
   - تأكد أن مجلد `barcodes/` قابل للكتابة
   - في Windows مع XAMPP: هذا تلقائي

5. **الدخول**
   - افتح: http://localhost/hadaya-pos/
   - المستخدم: `admin`
   - كلمة المرور: `password`
   - **غيّر كلمة المرور فوراً من صفحة الإعدادات!**

---

## 🇬🇧 Installation (English)

### Requirements
- XAMPP (PHP 8.0+ with GD extension enabled)
- MySQL 5.7+
- Modern browser (Chrome, Firefox, Edge)

### Steps

1. **Copy Files**
   ```
   Copy the hadaya-pos folder to:
   C:\xampp\htdocs\hadaya-pos\
   ```

2. **Create Database**
   - Open XAMPP Control Panel → Start Apache & MySQL
   - Go to http://localhost/phpmyadmin
   - Click "New" → Database name: `bangeen_pos`
   - Collation: `utf8mb4_unicode_ci` → Create
   - Click SQL tab → Paste contents of `setup.sql` → Go

3. **Configure DB Connection**
   - Open `includes/config.php`
   - Edit if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'bangeen_pos');
     define('DB_USER', 'root');
     define('DB_PASS', '');  // Your MySQL password if set
     ```

4. **Folder Permissions**
   - The `barcodes/` folder needs to be writable
   - On Windows with XAMPP this is automatic

5. **Login**
   - Open: http://localhost/hadaya-pos/
   - Username: `admin`
   - Password: `password`
   - **Change password immediately in Settings!**

---

## 📁 File Structure

```
hadaya-pos/
├── index.php              Login page (AR+EN)
├── dashboard.php          Dashboard
├── pos.php                POS / Sales screen
├── products.php           Product management + barcode gen
├── categories.php         Categories
├── suppliers.php          Suppliers
├── stock.php              Inventory / stock log
├── sales.php              Sales history
├── reports.php            Reports + Chart.js + CSV export
├── users.php              User management
├── settings.php           Store settings
├── backup.php             CSV export / backup
├── logout.php             Session logout
├── setup.sql              Database schema + seed data
├── .htaccess              Security rules
├── includes/
│   ├── config.php         DB, auth, i18n (t() function)
│   ├── layout.php         Shared sidebar + topbar
│   └── layout_end.php     Footer + toast JS
├── api/
│   ├── products.php       GET products JSON
│   ├── product_lookup.php GET product by barcode/name
│   ├── checkout.php       POST checkout → save sale
│   └── sale_detail.php    GET sale details
├── lib/
│   └── barcode/
│       └── BarcodeGenerator.php  Pure PHP Code128 PNG
└── barcodes/              Generated barcode images (auto-created)
```

---

## 🌐 Bilingual System

- Switch language with the flag button in the top bar
- All pages support **Arabic (RTL)** and **English (LTR)**
- All DB columns have `_ar` and `_en` variants
- Receipts print in the active language
- Barcode format: `IQ000001|{id}:{price}` (Code128 PNG)

---

## 👥 Default Users

| Username | Password | Role |
|----------|----------|------|
| admin    | password | Admin |
| manager  | password | Manager |
| cashier  | password | Cashier |

---

## 🎨 Brand Colors

| Use | Hex |
|-----|-----|
| Primary / Accent | `#E03A1E` |
| Background (Cream) | `#F5F0EB` |
| Dark Text | `#2B2B2B` |
| Sidebar | `#1A1008` |

---

## ⚠️ Troubleshooting

**Barcode images not generating?**
→ Make sure PHP GD extension is enabled in `php.ini`:
  `extension=gd`

**Arabic text garbled in CSV?**
→ Open in Excel → Data → From Text/CSV → File Origin: UTF-8

**Can't connect to DB?**
→ Check `config.php` credentials match XAMPP MySQL settings

**Session issues?**
→ Make sure `session.save_path` is writable in php.ini
