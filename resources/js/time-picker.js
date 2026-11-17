const digits = value => String(value ?? '').replace(/[۰-۹]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))).replace(/[٠-٩]/g, digit => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)));
export default () => ({
    open: false, hour: '09', minute: '00', draft: '', error: '',
    show(value) { this.draft = digits(value); const match = this.draft.match(/^(\d{2}):(\d{2})$/); this.hour = match?.[1] ?? '09'; this.minute = match?.[2] ?? '00'; this.error = ''; this.open = true; this.$nextTick(() => this.$refs.hour.focus()); },
    apply() { const value = `${this.hour}:${this.minute}`; if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(value)) { this.error = 'ساعت معتبر نیست.'; return; } this.$dispatch('time-selected', value); this.open = false; },
    typed(event) { const value = digits(event.target.value); event.target.value = value; this.error = value && !/^([01]\d|2[0-3]):[0-5]\d$/.test(value) ? 'ساعت را به شکل ۲۴ ساعته مانند ۱۳:۳۰ وارد کنید.' : ''; event.target.setCustomValidity(this.error); this.$dispatch('time-selected', value); },
});
