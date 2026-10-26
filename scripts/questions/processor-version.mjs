import { readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';

export async function currentProcessorVersion() {
    const files = ['processor-version.mjs', 'structured-pdf-source.mjs', 'structured-maths.mjs', 'pdf-question-core.mjs', 'lesson-question-bank.mjs'];
    const hash = createHash('sha256');
    for (const file of files) hash.update(await readFile(new URL(file, import.meta.url)));
    return hash.digest('hex');
}
