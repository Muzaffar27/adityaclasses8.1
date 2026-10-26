import { createServer } from 'node:http';
import { readFile, realpath } from 'node:fs/promises';
import path from 'node:path';

const root = await realpath(path.resolve(process.argv[2] || 'storage/app/ocr-samples/grade-8-mathematics'));
const types = { '.html': 'text/html; charset=utf-8', '.png': 'image/png', '.json': 'application/json; charset=utf-8' };
createServer(async (request, response) => {
    try {
        if (request.url === '/favicon.ico') { response.writeHead(204).end(); return; }
        const file = await realpath(path.resolve(root, '.' + decodeURIComponent(new URL(request.url, 'http://localhost').pathname)));
        const type = types[path.extname(file)];
        if (!file.startsWith(root + path.sep) || !type || !['GET', 'HEAD'].includes(request.method)) {
            response.writeHead(404).end(); return;
        }
        const bytes = await readFile(file);
        response.writeHead(200, { 'Content-Type': type, 'Content-Length': bytes.length, 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff' });
        response.end(request.method === 'HEAD' ? undefined : bytes);
    } catch { response.writeHead(404).end(); }
}).listen(8091, '127.0.0.1', () => console.log('Local previews: http://127.0.0.1:8091/tests/<test-id>/index.html'));
