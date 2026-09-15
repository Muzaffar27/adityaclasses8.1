export function normalizeVimeoUrl(value) {
    if (!value) return '';

    const text = String(value).trim();
    const iframeSrc = text.match(/<iframe[^>]*\ssrc=(["'])(.*?)\1/i)?.[2];
    const url = iframeSrc || text.match(/https?:\/\/[^\s"'<>]+/i)?.[0] || text;

    return decodeHtmlEntities(url).replace(/&amp;/g, '&').trim();
}

export function getVimeoPlayerUrl(value) {
    const baseUrl = normalizeVimeoUrl(value);
    if (!baseUrl) return '';

    const separator = baseUrl.includes('?') ? '&' : '?';
    return `${baseUrl}${separator}autoplay=1&muted=0&quality=360p`;
}

function decodeHtmlEntities(value) {
    if (typeof document === 'undefined') return value;

    const textarea = document.createElement('textarea');
    textarea.innerHTML = value;
    return textarea.value;
}
