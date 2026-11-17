export default () => ({
    schema: { sections: [] }, advanced: '', error: '', drag: null,
    init() {
        const parsed = JSON.parse(this.$el.dataset.schema);
        this.schema = typeof parsed === 'string' ? JSON.parse(parsed) : parsed;
    },
    newField() {
        return { key: `field_${crypto.randomUUID().replaceAll('-', '').slice(0, 12)}`, type: 'short_text', label: document.documentElement.lang === 'fa' ? 'فیلد جدید' : 'New field', description: '', width: 1, required: false, active: true, default: null, min: null, max: null, options: [], unit: '', condition: null, calculation: null, children: [], confidentiality: 'normal', assistant_editable: false, receptionist_visible: false, exportable: true, ai: { enabled: false, methods: [], task: '', review_required: true } };
    },
    addSection() {
        this.schema.sections.push({ key: `section_${crypto.randomUUID().slice(0, 8)}`, title: document.documentElement.lang === 'fa' ? 'بخش جدید' : 'New section', columns: 2, fields: [] });
    },
    move(items, index, direction) {
        const target = index + direction;
        if (target < 0 || target >= items.length) return;
        [items[index], items[target]] = [items[target], items[index]];
    },
    drop(si, fi) {
        if (!this.drag) return;
        const [field] = this.schema.sections[this.drag.si].fields.splice(this.drag.fi, 1);
        this.schema.sections[si].fields.splice(fi, 0, field);
        this.drag = null;
    },
    changeType(field) {
        if (field.type === 'repeater' && !field.children.length) field.children.push(this.newField());
        if (field.type !== 'repeater') field.children = [];
        if (field.type === 'calculated' && !field.calculation) field.calculation = { number: 0 };
        if (['select', 'radio', 'checkbox', 'multi_select'].includes(field.type) && !field.options.length) field.options = ['Yes', 'No'];
    },
    applyAdvanced() {
        try {
            const parsed = JSON.parse(this.advanced);
            if (!Array.isArray(parsed.sections)) throw new Error();
            this.schema = parsed;
            this.error = '';
        } catch { this.error = document.documentElement.lang === 'fa' ? 'JSON معتبر نیست.' : 'Invalid JSON.'; }
    },
    serialize() { this.$refs.draft.querySelector('[name="schema"]').value = JSON.stringify(this.schema); },
});
