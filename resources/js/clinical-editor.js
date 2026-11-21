document.querySelectorAll('[data-clinical-editor]').forEach(root => {
    const input = root.querySelector('[data-editor-input]');
    const body = root.querySelector('[data-editor-body]');
    // Stored content is sanitized by the server; pasted content is inserted as plain text.
    body.innerHTML = input.value;
    input.hidden = true;
    body.hidden = false;
    const sync = () => { input.value = body.innerHTML; };
    body.addEventListener('input', sync);
    body.addEventListener('paste', event => {
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        sync();
    });
    root.querySelectorAll('[data-command]').forEach(button => {
        button.addEventListener('mousedown', event => event.preventDefault());
        button.addEventListener('click', () => {
            body.focus();
            const command = button.dataset.command;
            if (command === 'createLink') {
                const url = window.prompt(root.lang === 'en' ? 'Link URL (https://)' : 'آدرس پیوند (https://)');
                if (url && /^https?:\/\//i.test(url)) document.execCommand(command, false, url);
            } else if (command === 'table') {
                document.execCommand('insertHTML', false, '<table><tbody><tr><th>—</th><th>—</th></tr><tr><td>—</td><td>—</td></tr><tr><td>—</td><td>—</td></tr></tbody></table><p><br></p>');
            } else document.execCommand(command, false);
            sync();
        });
    });
    root.querySelectorAll('[data-direction]').forEach(button => {
        button.addEventListener('mousedown', event => event.preventDefault());
        button.addEventListener('click', () => {
            body.focus();
            setDirection(body, button.dataset.direction);
            sync();
        });
    });
    root.querySelector('[data-block]').addEventListener('change', event => {
        body.focus(); document.execCommand('formatBlock', false, event.target.value); sync();
    });
    root.closest('form').addEventListener('submit', sync);
});

const blockSelector = 'p,div,h1,h2,h3,h4,h5,h6,li,blockquote,pre,td,th';

function setDirection(body, direction) {
    const selection = window.getSelection();

    if (!selection || !selection.rangeCount) {
        return;
    }

    let range = selection.getRangeAt(0);
    let blocks = [...body.querySelectorAll(blockSelector)].filter(block => {
        try {
            return range.intersectsNode(block);
        } catch {
            return false;
        }
    });

    if (!blocks.length) {
        const currentBlock = closestBlock(selection.anchorNode, body);

        if (currentBlock) {
            blocks = [currentBlock];
        } else {
            document.execCommand('formatBlock', false, 'div');
            range = selection.rangeCount ? selection.getRangeAt(0) : range;
            const formattedBlock = closestBlock(selection.anchorNode, body);
            if (formattedBlock) {
                blocks = [formattedBlock];
            }
        }
    }

    blocks.forEach(block => block.setAttribute('dir', direction));
}

function closestBlock(node, root) {
    let element = node?.nodeType === Node.ELEMENT_NODE ? node : node?.parentElement;

    while (element && element !== root) {
        if (element.matches(blockSelector)) {
            return element;
        }
        element = element.parentElement;
    }

    return null;
}
