export default () => ({
    schema: {}, values: {}, rows: {},
    init() {
        this.schema = JSON.parse(this.$el.dataset.schema);
        this.values = JSON.parse(this.$el.dataset.values);
        for (const field of this.fields()) {
            if (field.type === 'repeater') this.rows[field.key] = this.values[field.key] ?? {};
            else if (this.values[field.key] === undefined) this.values[field.key] = field.default ?? (['checkbox', 'multi_select'].includes(field.type) ? [] : '');
        }
    },
    fields() { return this.schema.sections.flatMap(section => section.fields); },
    visible(key) {
        const field = this.fields().find(item => item.key === key);
        return field && field.active && this.condition(field.condition);
    },
    effective(key, depth = 0) {
        if (depth > 30) return null;
        const field = this.fields().find(item => item.key === key);
        if (!field?.active || !this.condition(field.condition, depth + 1)) return null;
        return field.type === 'calculated' ? this.calculate(key, depth + 1) : this.values[key];
    },
    condition(node, depth = 0) {
        if (!node) return true;
        if (depth > 30) return false;
        if (node.op === 'and') return node.conditions.every(child => this.condition(child, depth + 1));
        if (node.op === 'or') return node.conditions.some(child => this.condition(child, depth + 1));
        const value = this.effective(node.field, depth + 1);
        if (node.op === 'exists') return value !== '' && value != null && (!Array.isArray(value) || value.length > 0);
        if (node.op === 'equals') return String(value) === String(node.value);
        if (node.op === 'in') return node.value.map(String).includes(String(value));
        if (node.op === 'gt') return value !== '' && Number(value) > Number(node.value);
        return false;
    },
    calculate(key, depth = 0) {
        if (depth > 30) return null;
        const field = this.fields().find(item => item.key === key);
        if (!field?.active || !this.condition(field.condition, depth + 1)) return null;
        if (field.type !== 'calculated') return this.values[key] !== '' && this.values[key] != null && Number.isFinite(Number(this.values[key])) ? Number(this.values[key]) : null;
        return this.expression(field.calculation, depth + 1);
    },
    expression(node, depth) {
        if (!node || depth > 40) return null;
        if (node.field) return this.calculate(node.field, depth + 1);
        if (node.number !== undefined) return Number(node.number);
        const left = this.expression(node.left, depth + 1), right = this.expression(node.right, depth + 1);
        if (left === null || right === null || (node.op === '/' && right === 0)) return null;
        const result = node.op === '+' ? left + right : node.op === '-' ? left - right : node.op === '*' ? left * right : left / right;
        return Number.isFinite(result) && Math.abs(result) <= 1e9 ? Math.round(result * 1e6) / 1e6 : null;
    },
    addRow(key) {
        if (Object.keys(this.rows[key]).length >= 30) return;
        const field = this.fields().find(item => item.key === key);
        this.rows[key][`row_${crypto.randomUUID()}`] = Object.fromEntries(field.children.map(child => [child.key, child.default ?? (['checkbox', 'multi_select'].includes(child.type) ? [] : '')]));
    },
    moveRow(key, id, direction) {
        const entries = Object.entries(this.rows[key]);
        const index = entries.findIndex(([rowId]) => rowId === id), target = index + direction;
        if (target < 0 || target >= entries.length) return;
        [entries[index], entries[target]] = [entries[target], entries[index]];
        this.rows[key] = Object.fromEntries(entries);
    },
});
