import Alpine from 'alpinejs';
import formDesigner from './form-designer';
import formRuntime from './form-runtime';
import persianDatePicker from './persian-date-picker';
import clinicTimePicker from './time-picker';
import schedulePlanner from './schedule-planner';

window.Alpine = Alpine;
Alpine.data('formDesigner', formDesigner);
Alpine.data('formRuntime', formRuntime);
Alpine.data('persianDatePicker', persianDatePicker);
Alpine.data('clinicTimePicker', clinicTimePicker);
Alpine.data('schedulePlanner', schedulePlanner);
Alpine.start();

function updateDate() {
    document.querySelectorAll('[data-current-date]').forEach((element) => {
        element.textContent = new Intl.DateTimeFormat(document.documentElement.lang === 'fa' ? 'fa-IR-u-ca-persian' : 'en-GB', {
            dateStyle: 'full', timeZone: 'Asia/Tehran',
        }).format(new Date());
    });
}
updateDate();
document.querySelectorAll('[data-patient-birth]').forEach((element) => {
    element.textContent = new Intl.DateTimeFormat(document.documentElement.lang === 'fa' ? 'fa-IR-u-ca-persian' : 'en-GB', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${element.dataset.patientBirth}T12:00:00Z`));
});

document.querySelectorAll('[data-preference-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button');
        const status = form.querySelector('[data-preference-status]');
        const oldLocale = document.documentElement.lang;
        const oldTheme = document.documentElement.dataset.theme;
        button.disabled = true;
        status.textContent = oldLocale === 'fa' ? 'در حال ذخیره…' : 'Saving…';
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form),
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('preferences');
            const result = await response.json();
            document.documentElement.lang = result.locale;
            document.documentElement.dir = result.locale === 'fa' ? 'rtl' : 'ltr';
            const pageKey = document.querySelector('h2[data-i18n="login"]')?.dataset.i18n
                ?? document.querySelector('h1[data-i18n]')?.dataset.i18n;
            if (pageKey && result.messages[pageKey]) document.title = `${result.messages[pageKey]} · ${result.messages.brand}`;
            window.applyClinicTheme(result.theme);
            document.querySelectorAll('[data-i18n]').forEach((element) => {
                const value = result.messages[element.dataset.i18n];
                if (typeof value === 'string') element.textContent = value;
            });
            document.querySelectorAll('[data-staff-i18n]').forEach((element) => {
                const value = result.staffMessages?.[element.dataset.staffI18n];
                if (typeof value === 'string') element.textContent = value;
            });
            updateDate();
            status.textContent = result.messages.saved;
        } catch {
            form.elements.locale.value = oldLocale;
            form.elements.theme.value = oldTheme;
            status.textContent = oldLocale === 'fa' ? 'ذخیره انجام نشد. دوباره تلاش کنید.' : 'Could not save. Please try again.';
        } finally {
            button.disabled = false;
        }
    });
});
