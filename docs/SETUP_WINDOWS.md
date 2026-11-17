# ابزارهای موردنیاز و نصب در ویندوز

## نتیجه بررسی محیط

بررسی در ۲۰۲۶-۱۱-۱۷ روی همین دستگاه:

| ابزار | نتیجه |
|---|---|
| Node.js | نصب است: v26.13.0 |
| npm | نصب است: 11.20.0؛ با `npm.cmd` قابل اجرا |
| WinGet | نصب است: v1.29.380 |
| PHP | در PATH پیدا نشد |
| Composer | در PATH پیدا نشد |
| MySQL client | در PATH پیدا نشد |
| Git | در PATH پیدا نشد |
| Docker | در PATH پیدا نشد؛ برای مسیر منتخب الزامی نیست |

مسیرهای متداول PHP، XAMPP، Laragon و MySQL نیز وجود نداشتند؛ نبود دستور در PATH به‌تنهایی اثبات نبود نرم‌افزار در تمام دیسک نیست. هیچ بسته یا ابزار سیستمی در مرحله صفر نصب نشده است. مطابق دستور مرحله صفر، نصب و پیکربندی stack و وابستگی‌های برنامه در مرحله اول انجام می‌شود. کاربر در صورت تمایل می‌تواند پیش‌نیازهای زیر را از همین حالا نصب کند.

## مسیر پیشنهادی نصب

برای توسعه از PHP بومی ویندوز + Composer + MySQL استفاده می‌شود. نصب هم‌زمان XAMPP، Laragon و چند سرویس دیتابیس پیشنهاد نمی‌شود، چون باعث تداخل PATH و پورت می‌شود. VS Code اختیاری است. Redis، Docker، Python و GPU در مرحله اول الزامی نیستند.

### ۱. Git

PowerShell:

```powershell
winget search --id Git.Git --exact
winget install --id Git.Git --exact --source winget
```

اگر نصب درخواست UAC کرد، تأیید در خود ویندوز لازم است. پس از نصب ترمینال تازه باز کنید و `git --version` را اجرا کنید.

### ۲. PHP 8.4 x64

ابتدا شناسه موجود بسته و اطلاعات ناشر را بررسی کنید:

```powershell
winget search PHP
winget show --id PHP.PHP.8.4 --exact --source winget
```

اگر همان بسته در source موجود بود:

```powershell
winget install --id PHP.PHP.8.4 --exact --source winget
```

این شناسه روی registry این دستگاه هنوز بررسی نشده است؛ اگر پیدا نشد از توزیع رسمی در https://windows.php.net/download/ استفاده کنید. برای CLI توسعه نسخه x64 NTS مناسب است. فایل `php.ini-development` را به `php.ini` کپی و مسیر PHP را به PATH کاربر اضافه کنید. runtime رسمی Microsoft Visual C++ موردنیاز همان build باید نصب باشد.

extensionهای لازم یا مورد بررسی: curl، fileinfo، intl، mbstring، openssl، pdo_mysql، zip، dom، xml، ctype، filter، hash، session، tokenizer و در صورت نیاز gd و sodium. برخی builtin هستند و نباید برای همه آن‌ها خط extension ساخت. فعال‌بودن دقیق با خروجی زیر سنجیده می‌شود:

```powershell
php --version
php --ini
php -m
```

### ۳. Composer

نصاب رسمی https://getcomposer.org/download/ را اجرا و مسیر PHP نصب‌شده را انتخاب کنید. از خاموش‌کردن TLS یا ابزار دانلود ناشناس استفاده نکنید. برای نصب خودکار در مرحله اول، installer رسمی با checksum رسمی بررسی خواهد شد.

```powershell
composer --version
composer diagnose
```

### ۴. MySQL 8.4 LTS

ابتدا بسته‌های موجود را ببینید:

```powershell
winget search MySQL
```

از صفحه رسمی https://dev.mysql.com/downloads/mysql/ نسخه Community Server 8.4 LTS و ابزار پیکربندی مناسب ویندوز را نصب کنید. نسخه نتیجه عمومی WinGet ممکن است شاخه دیگری باشد؛ قبل از نصب نسخه را بررسی کنید.

در پیکربندی توسعه، سرویس MySQL فعال، دسترسی شبکه محدود به دستگاه محلی و رمز root قوی انتخاب شود. پورت معمول 3306 است. اتصال برنامه با root انجام نخواهد شد؛ کاربر و دیتابیس `clinic` در مرحله اول ساخته می‌شوند. برای LAN هم کلاینت‌ها فقط به وب‌سرور وصل می‌شوند و نیازمند دسترسی مستقیم به پورت دیتابیس نیستند.

MySQL Workbench اختیاری است. رمز root را در پیام یا مستندات پروژه ننویسید.

### ۵. Node.js

Node و npm موجودند؛ نصب دوباره فعلاً لازم نیست. وضعیت LTS نسخه 26 و سازگاری آن با Vite و وابستگی‌های انتخابی در مرحله اول بررسی می‌شود. اگر سازگار نبود، نسخه LTS پشتیبانی‌شده انتخاب می‌شود؛ نسخه موجود بدون نیاز جایگزین نمی‌شود.

```powershell
node --version
npm.cmd --version
```

استفاده از `npm.cmd` مشکل محدودیت اجرای `npm.ps1` در PowerShell را بدون تغییر سراسری ExecutionPolicy دور می‌زند.

## کتابخانه‌های برنامه در زمان مربوط

| زمان | وابستگی پیشنهادی | کاربرد |
|---|---|---|
| مرحله ۱ | Laravel، Vite، Tailwind، Alpine.js، Vazirmatn محلی | هسته و رابط |
| مرحله ۲ | spatie/laravel-permission | نقش و مجوز؛ به همراه Policyهای پروژه |
| مرحله ۴ | SortableJS و parser محدود قابل بررسی | چیدمان و محاسبه فرم |
| مرحله ۵ | morilog/jalali یا معادل معتبر | تبدیل تقویم با تست |
| مرحله ۷ | HTTP client لاراول و adapterهای پروژه | اتصال AI؛ بدون وابستگی اجباری به SDK یک Provider |
| مرحله ۸ | Tesseract fas/eng، Poppler؛ FFmpeg در صورت نیاز | OCR، PDF و صوت محلی |
| مرحله ۹ | Chromium + ابزار PDF سازگار مانند Browsershot | PDF با shaping فارسی و RTL |
| گزارش‌ها | Chart.js | نمودار با دسترسی مجاز |

نسخه دقیق و مجوز کتابخانه‌ها هنگام نصب بررسی می‌شود. lockfileهای Composer و npm نگهداری می‌شوند. کلید سرویس AI، درگاه یا پیامک برای مرحله صفر و اول لازم نیست.

## معیار آماده بودن دستگاه

پس از تأیید طراحی و تکمیل نصب، اجرای `php --version`، `composer diagnose`، `mysql --version`، `git --version` و build پروژه باید موفق باشد. صرف نصب client نشان‌دهنده اتصال به سرور نیست؛ اتصال و migration نیز در مرحله اول جدا بررسی می‌شود. برای مرحله صفر فقط بررسی ابزارها انجام شده و هیچ build، تست Laravel یا اتصال دیتابیس قابل اجرا وجود ندارد.
