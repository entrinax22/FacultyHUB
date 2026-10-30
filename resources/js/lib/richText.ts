const allowedTags = new Set([
    'A',
    'BR',
    'EM',
    'H2',
    'H3',
    'LI',
    'OL',
    'P',
    'STRONG',
    'U',
    'UL',
]);

const droppedTags = new Set([
    'SCRIPT',
    'STYLE',
    'SVG',
    'MATH',
    'IFRAME',
    'OBJECT',
    'EMBED',
    'TEMPLATE',
    'NOSCRIPT',
    'TEXTAREA',
    'TITLE',
    'XMP',
    'PLAINTEXT',
    'NOEMBED',
    'NOFRAMES',
]);

const htmlPattern =
    /<\/?(a|br|em|h[1-6]|li|ol|p|strong|u|ul|div|span|section|article|b|i|s|code|pre|blockquote|table|tbody|tr|td|th|img|input|form|button|select|option|video|audio|source|link|meta|base|script|style|svg|math|iframe|object|embed|template|noscript|textarea|title|xmp|plaintext|noembed|noframes)\b|<!--|^\s*<[^>]+>\s*$/i;

const escapeHtml = (value: string): string =>
    value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

export function sanitizeRichText(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    // Node SSR has no DOM parser. Escape content until the browser can sanitize it.
    if (typeof DOMParser === 'undefined') {
        return escapeHtml(value);
    }

    const parser = new DOMParser();

    if (
        !htmlPattern.test(value) &&
        !/<([a-z][a-z0-9:-]*)\b[^>]*>[\s\S]*<\/\1\s*>/i.test(value)
    ) {
        // Decode entities without interpreting legacy programming examples as tags.
        const text = parser.parseFromString(
            escapeHtml(value).replaceAll('&amp;', '&'),
            'text/html',
        );

        return escapeHtml(text.body.textContent ?? '').replaceAll('\n', '<br>');
    }

    const document = parser.parseFromString(value, 'text/html');
    const clean = (node: Node, depth = 0): string => {
        if (depth >= 64) {
            return '';
        }

        let result = '';

        for (const child of Array.from(node.childNodes)) {
            if (child.nodeType === Node.TEXT_NODE) {
                result += escapeHtml(child.textContent ?? '');
                continue;
            }

            if (child.nodeType !== Node.ELEMENT_NODE) {
                continue;
            }

            const element = child as HTMLElement;

            const tag = element.tagName.toUpperCase();

            if (droppedTags.has(tag)) {
                continue;
            }

            const content = clean(element, depth + 1);

            if (!allowedTags.has(tag)) {
                result += content;
                continue;
            }

            let attributes = '';

            if (tag === 'A') {
                for (const name of ['href', 'title']) {
                    const value = element.getAttribute(name)?.trim() ?? '';

                    if (
                        !value ||
                        (name === 'href' &&
                            (!/^(https?:|mailto:)/i.test(value) ||
                                /[\u0000-\u0020\u007f]/.test(value)))
                    ) {
                        continue;
                    }

                    attributes += ` ${name}="${escapeHtml(value)}"`;
                }

                attributes +=
                    ' target="_blank" rel="noopener noreferrer nofollow"';
            }

            const name = tag.toLowerCase();
            result +=
                tag === 'BR'
                    ? '<br>'
                    : `<${name}${attributes}>${content}</${name}>`;
        }

        return result;
    };

    return clean(document.body);
}

export function richTextToPlainText(value: string | null | undefined): string {
    if (typeof DOMParser === 'undefined') {
        return value ?? '';
    }

    const sanitized = sanitizeRichText(value);
    const document = new DOMParser().parseFromString(
        sanitized.replace(/<\/(p|h2|h3|li)>|<br>/g, ' '),
        'text/html',
    );

    return (document.body.textContent ?? '')
        .replace(/[\s\p{Z}\p{Cf}]+/gu, ' ')
        .trim();
}
