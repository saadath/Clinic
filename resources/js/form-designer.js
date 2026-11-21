const newKey = prefix => `${prefix}_${Array.from(crypto.getRandomValues(new Uint8Array(8)), byte => byte.toString(16).padStart(2, '0')).join('')}`;
const hasOptionsLayout = type => ['radio', 'checkbox'].includes(type);

export default () => ({
    schema: { sections: [] }, drag: null,
    init() {
        const parsed = JSON.parse(this.$el.dataset.schema);
        this.schema = typeof parsed === 'string' ? JSON.parse(parsed) : parsed;
        this.prepareFields(this.schema.sections);
    },
    prepareFields(sections) {
        sections.forEach(section => section.fields.forEach(field => {
            field.optionsText = (field.options ?? []).join('\n');
            if (hasOptionsLayout(field.type)) field.options_layout ??= 'horizontal';
            if (field.children?.length) this.prepareFields([{ fields: field.children }]);
        }));
    },
    newField() {
        return { key: newKey('field'), type: 'short_text', label: document.documentElement.lang === 'fa' ? 'فیلد جدید' : 'New field', description: '', width: 1, required: false, active: true, default: null, min: null, max: null, options: [], optionsText: '', unit: '', condition: null, calculation: null, children: [], confidentiality: 'normal', assistant_editable: false, receptionist_visible: false, exportable: true, ai: { enabled: false, methods: [], task: '', review_required: true } };
    },
    addSection() {
        this.schema.sections.push({ key: newKey('section'), title: document.documentElement.lang === 'fa' ? 'بخش جدید' : 'New section', columns: 2, fields: [] });
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
        field.default = null;
        field.calculation = null;
        if (hasOptionsLayout(field.type)) field.options_layout ??= 'horizontal';
        else delete field.options_layout;
        if (field.type === 'repeater' && !field.children.length) field.children.push(this.newField());
        if (field.type !== 'repeater') field.children = [];
        if (field.type === 'slider') {
            field.min ??= 0;
            field.max ??= 100;
        }
    },
    schemaJson(indent) {
        return JSON.stringify(this.schema, (key, value) => {
            if (key === 'optionsText') return undefined;
            if (value && typeof value.optionsText === 'string') {
                return { ...value, options: value.optionsText.split('\n').map(option => option.trim()).filter(Boolean) };
            }
            return value;
        }, indent);
    },
    serialize() { this.$refs.draft.querySelector('[name="schema"]').value = this.schemaJson(); },
});
