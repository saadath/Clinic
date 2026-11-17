# مرحله اول — زیرساخت

این مرحله شامل Laravel، MySQL، Tailwind، رابط فارسی/انگلیسی، تم و ورود کارکنان است. ماژول‌های بیماران، کارکنان و نوبت‌دهی در مراحل بعد اجرا می‌شوند.

ابزارهای کاربری در `%LOCALAPPDATA%\ClinicTools` قرار می‌گیرند. پس از نصب برای به‌روزرسانی PATH ترمینال جدید باز کنید. فایل‌های `.cmd` پوشه scripts برای اجرای محلی بدون تغییر ExecutionPolicy در نظر گرفته شده‌اند.

## خروجی و نسخه‌ها

- PHP 8.4.27 و Composer 2.11.2 در مسیر کاربری نصب شده‌اند؛ checksum بسته PHP و نصاب Composer بررسی شد.
- Laravel 13.42.0، Boost 2.10.4 و PHPUnit 12.5.44؛ جزئیات در composer.lock.
- Tailwind 4، Vite 8، Alpine.js و Vazirmatn محلی؛ جزئیات دقیق در package-lock.json.
- MySQL Community Server 8.4.9 از MSI رسمی با SHA256 تأییدشده استخراج شده است؛ در این دستگاه بدون Windows Service اجرا می‌شود.
- دیتابیس‌های clinic و clinic_testing ساخته و migrationهای برنامه روی MySQL اجرا شده‌اند.
- کاربر برنامه به همین دو دیتابیس دسترسی دارد؛ رمز تصادفی در `.env` و رمز root در `%LOCALAPPDATA%\ClinicTools\mysql-root.cnf` است. این فایل‌ها را به اشتراک نگذارید.

نصب سیستمی Visual C++ به درخواست UAC رسید. برای اجرای فعلی PHP، DLLهای runtime موجود Python 3.13 روی همین دستگاه در کنار PHP قرار گرفتند. برای انتقال یا نصب مجدد دستگاه از runtime رسمی Microsoft استفاده کنید. هیچ تنظیم امنیتی سراسری PowerShell تغییر نکرده است.

## اجرای همین دستگاه

در پوشه پروژه، اگر دیتابیس از قبل اجرا نیست:

```powershell
.\scripts\mysql-start.cmd
```

در ترمینال دیگر:

```powershell
.\scripts\serve.cmd
```

نشانی صفحه ورود: http://127.0.0.1:8000/login

برای توقف دیتابیس از `scripts\mysql-stop.cmd` استفاده کنید. بستن سرور وب با Ctrl+C انجام می‌شود. این مسیر برای توسعه محلی است؛ استقرار کلینیک و HTTPS در مرحله استقرار انجام خواهد شد.

## ساخت اولین حساب

ترمینال جدید باز کنید تا PATH ابزارها به‌روز شود؛ سپس:

```powershell
php artisan clinic:create-user staff@example.test --name="Clinic Staff"
```

فرمان دو بار رمز را به‌صورت مخفی می‌پرسد؛ حداقل ۱۲ کاراکتر لازم است. ایمیل مثال را با ایمیل دلخواه جایگزین کنید. رمز روی خط فرمان یا در کد ذخیره نمی‌شود. ثبت‌نام عمومی بسته است. در این مرحله حساب کارکنان به خانه و تنظیمات دسترسی دارد؛ نقش مدیریتی و انتساب‌ها در مرحله دوم ساخته می‌شوند.

اگر ترمینال PATH جدید را نگرفت، PHP با مسیر کامل قابل اجراست:

```powershell
& "$env:LOCALAPPDATA\ClinicTools\php\php.exe" artisan clinic:create-user staff@example.test --name="Clinic Staff"
```

## آزمون و ساخت

```powershell
php artisan test --compact
npm.cmd run build
composer check-platform-reqs
composer audit
```

پیش‌فرض تست‌ها SQLite در حافظه است و داده توسعه پاک نمی‌شود. برای اجرای همان تست‌ها روی دیتابیس مجزای MySQL:

```powershell
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='clinic_testing'
php artisan test --compact
Remove-Item Env:DB_CONNECTION
Remove-Item Env:DB_DATABASE
```

هیچ‌وقت DB_DATABASE تست را به clinic یا دیتابیس واقعی تغییر ندهید؛ trait تست جدول‌های دیتابیس انتخاب‌شده را بازسازی می‌کند.

ورود موفق، خروج، حساب غیرفعال، محدودسازی ورود، خطاهای فارسی، جلوگیری از XSS در نام، تنظیمات هر کاربر و جلوگیری از تغییر حساب دیگر پوشش داده شده‌اند. در مرورگر، تغییر زبان و تم بدون reload و حفظ مقدار فرم ورود تأیید شد. گزارش نهایی تعداد آزمون‌ها در PROJECT_STATUS.md ثبت می‌شود.

## تغییر در چیدمان پیشنهادی

برای حفظ ساختار تولیدشده Laravel، Controller و FormRequest هویت فعلاً زیر `app/Http/.../Modules/Identity` و User در `app/Models` قرار دارند. این زیرساخت کوچک هنوز نیازمند جابه‌جایی هسته احراز هویت نیست؛ مرزهای حوزه‌های عملیاتی از مرحله دوم طبق طراحی ماژولار حفظ می‌شوند.

## خطاهای محتمل

- خطای اتصال 2002: MySQL را با فایل start اجرا کنید؛ اگر پورت 3306 اشغال است، نمونه دوم اجرا نکنید.
- خطای Vite manifest: `npm.cmd run build` اجرا شود.
- خطای 419: صفحه را تازه کنید؛ session منقضی‌شده نیازمند ورود مجدد است.
- فرمان php پیدا نمی‌شود: ترمینال تازه یا مسیر کامل بالا را استفاده کنید.
- `npm.ps1` مسدود است: از `npm.cmd` استفاده کنید.
- MySQL با اجرای دوباره start خطای قفل فایل می‌دهد: نمونه قبلی در حال اجرا است.
