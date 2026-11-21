# کلینیک — سیستم مدیریت هوشمند کلینیک

نرم‌افزار جامع و مدرن مدیریت کلینیک چندتخصصی با **فرم‌ساز پزشکی اختصاصی**، **پرونده الکترونیک بیمار** و رابط کاربری کاملاً **فارسی و راست‌به‌چپ**. این سیستم با معماری ماژولار (پرونده پزشکی، فرم‌ساز، بیماران، کارکنان) توسعه یافته و برای استفاده توسط پزشک، دستیار پزشک و منشی طراحی شده است.

## ✨ امکانات

### 🔐 ورود و امنیت
- ورود ویژه کارکنان (بدون ثبت‌نام عمومی؛ حساب‌ها توسط مدیر ساخته می‌شوند)
- رمز عبور با حداقل ۱۲ کاراکتر و هش امن
- محدودسازی تلاش‌های ورود (Rate Limiting)
- سشن رمزنگاری‌شده و ذخیره در دیتابیس
- محافظت CSRF و پاک‌سازی XSS در تمام محتوای بالینی
- ۱۱ نقش سیستمی و ۱۸ مجوز تفکیک‌شده با کنترل دسترسی در سطح هر رکورد
- مجوز `super-admin` همیشه فعال و محافظت از حساب خودی

### 📊 داشبورد نقش‌محور
- عنوان و خوش‌آمدگویی متفاوت برای هر نقش (پزشک، مدیر، منشی، دستیار و…)
- تاریخ شمسی امروز در سربرگ و داشبورد
- کارت‌های آماری (تعداد بیماران مجاز، فرم‌های فعال) بر اساس مجوز هر کاربر
- دسترسی سریع به بخش‌های مجاز
- کارت پروفایل پزشک (تخصص، شماره نظام پزشکی، مدت و تعرفه ویزیت)

### 👤 مدیریت بیماران (پذیرش هویتی)
- ثبت، ویرایش و مشاهده اطلاعات هویتی بیمار
- کد ملی با اعتبارسنجی ایرانی + موبایل + تاریخ تولد (اختیاری) با تقویم شمسی
- جستجوی زنده با پشتیبانی از اعداد فارسی (نام، کد ملی، موبایل)
- تشخیص بیمار تکراری هنگام ثبت (بر اساس کد ملی و موبایل)
- کنترل دید: پزشک یا فقط بیماران منتسب را می‌بیند یا (با مجوز) همه بیماران
- صفحه بیمار مستقیماً به پرونده پزشکی او هدایت می‌شود

### 🩺 پرونده پزشکی
- **خلاصه مزمن بیمار**: ویرایشگر غنی با ذخیره JSON و نمایش در بالای پرونده
- **مراجعات (Encounters)**: ثبت، ویرایش و حذف نرم با حفظ تاریخچه کامل
- **ویرایشگر بالینی پیشرفته**: راست‌به‌چپ/چپ‌به‌راست، واگرد/ازنو، قالب‌بندی متن، تنظیم جهت پاراگراف
- زبان نگارش بالینی (فارسی/انگلیسی) و اندازه قلم — مستقل برای هر پزشک و ماندگار
- **اصلاحیه‌ها (Amendments)**: افزودن توضیح تکمیلی بدون دست‌زدن به یادداشت اصلی
- **فایل‌های خصوصی**: آپلود و دانلود پیوست با بازبینی دسترسی در هر بار دانلود
- **گزارش قابل چاپ**: خروجی HTML آماده چاپ و PDF (قطع A4، فونت وزیرمتن) فقط با محتوای مجاز
- فیلدهای محرمانه و فیلدهای غیرقابل‌صدور در گزارش به‌طور خودکار پنهان می‌شوند
- **رخدادهای ممیزی**: ثبت ایجاد/ویرایش/حذف همراه با عامل هر تغییر

### 🧾 فرم‌ساز پزشکی
- طراحی قالب فرم اختصاصی برای هر پزشک: بخش‌ها، ستون‌ها و ۱۳ نوع فیلد
  - متن کوتاه، متن بلند، متن غنی، گزینه‌ای (رادیو)، لیست انتخابی، چندانتخابی، چک‌باکس، چک‌باکس تکی، اسلایدر، عدد اعشاری، تصویر، چندتصویره و پیوست PDF
- اعتبارسنجی کامل ساختار (Schema) هنگام ذخیره
- **نسخه‌بندی فرم‌ها**: انتشار نسخه جدید با حفظ نسخه‌های قبلی و سازگاری داده‌های ثبت‌شده
- کپی قالب، اشتراک‌گذاری قالب با پزشکان دیگر و تعیین فرم پیش‌فرض
- ثبت نمونه آزمایشی برای پیش‌نمایش بدون اثرگذاری بر پرونده
- **ثبت فرم در مراجعه**: تکمیل هم‌زمان چند فرم هنگام ایجاد مراجعه
- فیلدهای محرمانه (Confidential) فقط برای پزشک معالج قابل مشاهده است
- پیوست‌های فایل با پیش‌نمایش PDF و کنترل دسترسی در سطح فیلد

### 👥 کارکنان و نقش‌ها
- مدیریت حساب‌های کاربری (ایجاد، ویرایش، فعال/غیرفعال‌سازی)
- **نقش‌ها و مجوزها**: نقش‌های سیستمی با ماتریس مجوز قابل ویرایش + نقش‌های اختصاصی
- قفل نقش‌های سیستمی (شناسه و عنوان ثابت) و حفاظت از مدیر ارشد
- **مشخصات پزشک**: تخصص، فوق‌تخصص، معرفی، شماره نظام پزشکی، مدت و تعرفه ویزیت
- **انتساب کارکنان**: همکاری منشی/دستیار با پزشک با بازه شروع و پایان، جلوگیری از هم‌پوشانی و اعتبارسنجی نقش

### 🎨 رابط کاربری
- کاملاً **فارسی و راست‌به‌چپ** (RTL-first) با دوزبانه فارسی/انگلیسی قابل تغییر برای هر کاربر
- تم روشن/تاریک/همگام با دستگاه — ذخیره‌شده برای هر کاربر
- فونت متغیر وزیرمتن، اعداد فارسی و تاریخ شمسی در سراسر برنامه
- تقویم شمسی برای ورود تاریخ‌ها
- طراحی واکنش‌گرا (موبایل/دسکتاپ) با منوی کشویی و پیمایش با صفحه‌کلید
- آیکون اختصاصی پزشکی برای برنامه (فاوآیکون)

## 🛠 فناوری‌ها

| بخش | فناوری |
|---|---|
| بک‌اند | Laravel (PHP 8.3+) |
| دیتابیس | MySQL (utf8mb4) + درایور دیتابیس برای سشن/کش/صف |
| فرانت‌اند | Tailwind CSS v4، Vite 8، Alpine.js 3، فونت وزیرمتن |
| شناسه‌ها | ULID برای شناسه‌های عمومی رکوردها |
| تست | PHPUnit — ۱۳۳ تست و بیش از ۱۱۰۰ assertion |

ساختار کد ماژولار است: `app/Http/Controllers/Modules`، `app/Services` (لایه سرویس برای منطق کسب‌وکار) و `app/Http/Requests` برای اعتبارسنجی. مستندات معماری در پوشه [`docs/`](docs/) موجود است.

## 🚀 راه‌اندازی

پیش‌نیازها: PHP 8.3+ (با افزونه‌های mbstring، xml، curl، mysql، zip، intl)، Composer، Node.js و MySQL.

```bash
git clone <آدرس-ریپو> clinic
cd clinic
composer install
cp .env.example .env          # ویندوز: copy .env.example .env
php artisan key:generate
# اطلاعات دیتابیس را در .env تنظیم کنید
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force
npm install
npm run build
php artisan serve
```

ساخت اولین حساب کاربری:

```bash
php artisan clinic:create-user admin@example.com --name="مدیر کلینیک"
php artisan clinic:grant-admin admin@example.com   # ارتقا به مدیر ارشد (اختیاری)
```

اجرای تست‌ها:

```bash
php artisan test
```

## 📄 مجوز

این پروژه برای استفاده داخلی کلینیک توسعه یافته است.

---

# Clinic — Smart Clinic Management System

A comprehensive, modern management system for a multi-specialty clinic with a **dedicated medical form builder**, an **electronic patient record** and a fully **RTL/Persian** user interface. The system is built with a modular architecture (medical records, form builder, patients, staff) and is designed for doctors, doctor assistants and receptionists.

## ✨ Features

### 🔐 Authentication & Security
- Staff-only login (no public registration; accounts are created by administrators)
- Secure password hashing with a 12-character minimum
- Login rate limiting
- Encrypted database-backed sessions
- CSRF protection and XSS sanitization on all clinical content
- 11 system roles and 18 granular permissions with per-record access control
- Always-on super-admin access with self-protection rules

### 📊 Role-based Dashboard
- Distinct heading and greeting per role (doctor, administrator, receptionist, assistant…)
- Today's Jalali (Persian) date in the header and dashboard
- Permission-gated stat cards (accessible patients, active forms)
- Quick access to authorized sections
- Doctor profile card (specialty, medical council number, visit duration and fee)

### 👤 Patient Management (Demographic Reception)
- Register, edit and view patient demographics
- Iranian national ID validation, mobile number and optional birth date with a Jalali calendar picker
- Live search with Persian digit support (name, national ID, mobile)
- Duplicate patient detection on registration (national ID and mobile)
- Visibility control: doctors see only granted patients — or all patients with the proper permission
- The patient page redirects straight to their medical record

### 🩺 Medical Records
- **Chronic summary**: a rich-text summary saved as JSON and shown at the top of the record
- **Encounters**: create, edit and soft-delete with a full revision history
- **Advanced clinical editor**: RTL/LTR, undo/redo, text formatting and paragraph direction controls
- Per-doctor, persistent chart language (fa/en) and font size
- **Amendments**: append clarifications without touching the original note
- **Private files**: upload and download attachments with access re-checked on every download
- **Printable report**: print-ready HTML and PDF output (A4, Vazirmatn font) with only authorized content
- Confidential and non-exportable fields are automatically hidden from reports
- **Audit events**: every create/update/delete is recorded with its actor

### 🧾 Medical Form Builder
- Dedicated form templates per doctor: sections, columns and 13 field types
  - short text, long text, rich text, radio, select, multi-select, checkbox, single checkbox, slider, decimal, image, multiple images and PDF attachment
- Full schema validation on save
- **Versioning**: publish new versions while preserving previous ones and the data recorded with them
- Copy templates, share templates with other doctors and set a default form
- Sample submissions for previews without touching real records
- **In-encounter submissions**: complete several forms at once when creating an encounter
- Confidential fields are visible only to the treating doctor
- File attachments with PDF preview and per-field access control

### 👥 Staff & Roles
- User account management (create, edit, activate/deactivate)
- **Roles & permissions**: system roles with an editable permission matrix plus custom roles
- Locked system roles (fixed key and label) and super-admin protection
- **Doctor profiles**: specialty, sub-specialty, bio, medical council number, visit duration and fee
- **Doctor-staff assignments**: receptionist/assistant cooperation with start/end dates, overlap prevention and role validation

### 🎨 User Interface
- Fully RTL-first Persian UI with a switchable fa/en locale per user
- Light/dark/system theme, saved per user
- Vazirmatn variable font, Persian digits and Jalali dates throughout
- Jalali calendar for date input
- Responsive design (mobile/desktop) with a slide-over menu and keyboard-accessible navigation
- Dedicated medical app icon (favicon)

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel (PHP 8.3+) |
| Database | MySQL (utf8mb4) with database drivers for session/cache/queue |
| Frontend | Tailwind CSS v4, Vite 8, Alpine.js 3, Vazirmatn font |
| Identifiers | ULIDs for public record identifiers |
| Testing | PHPUnit — 133 tests, 1100+ assertions |

The codebase is modular: `app/Http/Controllers/Modules`, `app/Services` (business-logic layer) and `app/Http/Requests` for validation. Architecture documentation lives in [`docs/`](docs/).

## 🚀 Getting Started

Requirements: PHP 8.3+ (with mbstring, xml, curl, mysql, zip, intl), Composer, Node.js and MySQL.

```bash
git clone <repository-url> clinic
cd clinic
composer install
cp .env.example .env
php artisan key:generate
# configure your database in .env
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force
npm install
npm run build
php artisan serve
```

Create the first staff account:

```bash
php artisan clinic:create-user admin@example.com --name="Clinic Admin"
php artisan clinic:grant-admin admin@example.com   # promote to super admin (optional)
```

Run the test suite:

```bash
php artisan test
```

## 📄 License

Developed for internal clinic use.
