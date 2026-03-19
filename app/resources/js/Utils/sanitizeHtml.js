import DOMPurify from 'dompurify';

/**
 * Sanitize untrusted HTML (e.g. inbound email bodies) for safe v-html rendering.
 * Strips scripts, event handlers, and other dangerous constructs while keeping
 * legitimate email formatting intact.
 */
export function sanitizeHtml(dirty) {
    if (!dirty) return '';

    return DOMPurify.sanitize(dirty, {
        ALLOWED_TAGS: [
            'p', 'br', 'b', 'i', 'u', 'em', 'strong', 'a', 'img',
            'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'table', 'thead', 'tbody', 'tr', 'td', 'th',
            'div', 'span', 'blockquote', 'pre', 'code', 'hr',
            'sup', 'sub', 'small', 'font', 'center',
        ],
        ALLOWED_ATTR: [
            'href', 'src', 'alt', 'title', 'class', 'style',
            'width', 'height', 'align', 'valign', 'border',
            'cellpadding', 'cellspacing', 'bgcolor', 'color',
            'target', 'rel', 'colspan', 'rowspan', 'face', 'size',
        ],
        ALLOW_DATA_ATTR: false,
        ADD_ATTR: ['target'],
    });
}
