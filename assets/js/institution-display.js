(() => {
    const institute = document.currentScript.dataset.institutionType === 'institute';
    const terms = institute
        ? {'دانش‌آموزان': 'فراگیران', 'دانش آموزان': 'فراگیران', 'دانشآموزان': 'فراگیران', 'دانش‌آموز': 'فراگیر', 'دانش آموز': 'فراگیر', 'دانشآموز': 'فراگیر', 'پایه': 'دوره'}
        : {'فراگیران': 'دانش‌آموزان', 'فراگیر': 'دانش‌آموز', 'دوره': 'پایه'};
    const pattern = new RegExp(Object.keys(terms).join('|'), 'g');
    const translate = value => value.replace(pattern, word => terms[word]);
    const attributes = ['title', 'aria-label', 'placeholder', 'alt', 'data-tooltip'];
    const excluded = 'script,style,textarea,[contenteditable]';
    function normalize(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            if (!node.parentElement || node.parentElement.closest(excluded)) return;
            const value = translate(node.nodeValue);
            if (value !== node.nodeValue) node.nodeValue = value;
            return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE || node.closest(excluded)) return;
        for (const attribute of attributes) {
            if (!node.hasAttribute(attribute)) continue;
            const before = node.getAttribute(attribute);
            const after = translate(before);
            if (before !== after) node.setAttribute(attribute, after);
        }
        for (const child of node.childNodes) normalize(child);
    }
    normalize(document.body);
    new MutationObserver(records => {
        for (const record of records) {
            if (record.type === 'childList') record.addedNodes.forEach(normalize);
            else normalize(record.target);
        }
    }).observe(document.body, {subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: attributes});
})();
