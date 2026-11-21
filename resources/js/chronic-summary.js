export default () => ({
    editing: false, saving: false, hasSummary: false, status: '', error: '',
    init() {
        this.hasSummary = this.$el.dataset.hasSummary === '1';
        this.error = this.$el.dataset.summaryError;
        this.editing = Boolean(this.error);
    },
    async save(form) {
        if (this.saving) return;

        const data = new FormData(form);
        this.saving = true;
        this.status = '';
        this.error = '';

        try {
            const response = await fetch(form.action, {
                method: 'POST', body: data,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (response.status === 422 && result.errors?.summary?.[0]) {
                this.error = result.errors.summary[0];
                return;
            }
            if (!response.ok || typeof result.html !== 'string') throw new Error('Summary not saved');

            // Render only the server-sanitized version, never the submitted draft.
            this.$refs.summary.innerHTML = result.html;
            this.$refs.summary.dir = result.direction;
            form.querySelector('[data-editor-body]').innerHTML = result.html;
            form.elements.summary.value = result.html;
            this.hasSummary = true;
            this.editing = false;
            this.status = result.message;
        } catch {
            this.error = this.$el.dataset.saveError;
        } finally {
            this.saving = false;
        }
    },
});
