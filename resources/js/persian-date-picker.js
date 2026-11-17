export default () => ({
    value: '', open: false, year: 1405, month: 1, day: 1, time: '09:00', withTime: false,
    months: ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'],
    init() {
        this.value = this.$el.dataset.value;
        this.withTime = this.$el.dataset.withTime === '1';
        const today = new Intl.DateTimeFormat('en-US-u-ca-persian', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: 'Asia/Tehran' }).formatToParts(new Date());
        const part = type => Number(today.find(item => item.type === type).value);
        this.year = part('year'); this.month = part('month'); this.day = part('day');
        const match = this.value.match(/^(\d{4})\/(\d{2})\/(\d{2})(?: (\d{2}:\d{2}))?$/);
        if (match) { this.year = Number(match[1]); this.month = Number(match[2]); this.day = Number(match[3]); this.time = match[4] ?? '09:00'; }
    },
    days() {
        if (this.month <= 6) return 31;
        if (this.month < 12) return 30;
        const format = new Intl.DateTimeFormat('en-US-u-ca-persian', { year: 'numeric', month: 'numeric', day: 'numeric', timeZone: 'UTC' });
        for (let day = 19; day <= 22; day++) {
            const parts = format.formatToParts(new Date(Date.UTC(Number(this.year) + 622, 2, day)));
            const get = type => Number(parts.find(item => item.type === type).value);
            if (get('year') === Number(this.year) && get('month') === 12 && get('day') === 30) return 30;
        }
        return 29;
    },
    selectDate() {
        if (!Number.isInteger(Number(this.year)) || this.year < 1200 || this.year > 1600) return;
        this.day = Math.min(this.day, this.days());
        const pad = number => String(number).padStart(2, '0');
        this.value = `${this.year}/${pad(this.month)}/${pad(this.day)}${this.withTime ? ` ${this.time}` : ''}`;
        this.open = false;
    },
});
