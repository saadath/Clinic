export default () => ({
    previews: [], opened: null, canPreviewPdf: navigator.pdfViewerEnabled,
    destroy() {
        this.previews.forEach(preview => URL.revokeObjectURL(preview.url));
        this.previews = [];
    },
    syncFiles(input) {
        const transfer = new DataTransfer();
        this.previews.forEach(({ file }) => transfer.items.add(file));
        input.files = transfer.files;
    },
    updateValidity(input) {
        const overLimit = input.files.length > 10 || [...input.files].some(file => file.size > 10 * 1024 * 1024);
        input.setCustomValidity(overLimit ? input.dataset.limitMessage : this.previews.some(preview => !preview.ready) ? input.dataset.invalidMessage : '');
    },
    previewFiles(event) {
        const input = event.target;
        const pdf = input.dataset.previewKind === 'pdf';
        const selected = [...input.files].map(file => ({
            file,
            name: file.name,
            url: URL.createObjectURL(pdf ? file.slice(0, file.size, 'application/pdf') : file),
            ready: !pdf,
        }));
        if (!input.multiple) this.destroy();
        this.previews.push(...selected);
        if (input.multiple) this.syncFiles(input);

        if (pdf) {
            selected.forEach(async ({ file, url }) => {
                let ready;
                try {
                    ready = (await file.slice(0, 5).text()) === '%PDF-';
                } catch {
                    ready = false;
                }
                const preview = this.previews.find(item => item.url === url);
                if (!preview) return;
                preview.ready = ready;
                this.updateValidity(input);
            });
        }
        this.updateValidity(input);
        if (!pdf || input.files.length > 10 || [...input.files].some(file => file.size > 10 * 1024 * 1024)) input.reportValidity();
    },
    removePreview(index) {
        const input = this.$refs.input;
        URL.revokeObjectURL(this.previews[index].url);
        this.previews.splice(index, 1);
        this.syncFiles(input);
        this.updateValidity(input);
    },
    openPreview(preview) {
        if (this.$refs.input.dataset.previewKind === 'pdf') {
            window.open(preview.url, '_blank', 'noopener,noreferrer');
            return;
        }
        this.opened = preview;
        this.$refs.dialog.showModal();
    },
});
