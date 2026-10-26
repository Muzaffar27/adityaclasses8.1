import { randomInt } from 'node:crypto';

export function joinPdfText(items) {
    let text = '';
    for (let i = 0; i < items.length; i++) {
        const previous = items[i - 1], current = items[i];
        const gap = previous ? current.x - previous.x - previous.width : 0;
        const separator = previous && gap > Math.max(0.8, previous.height * 0.12) && !/\s$/.test(text) && !/^\s/.test(current.text) ? ' ' : '';
        text += separator + current.text;
    }
    return text.replace(/\s+/g, ' ').trim();
}

export function textLines(items, height) {
    const rows = [];
    for (const item of items.filter(item => item.str?.trim())) {
        const y = height - item.transform[5];
        let row = rows.find(row => Math.abs(row.y - y) < 3);
        if (!row) rows.push(row = { y, items: [] });
        row.items.push({ text: item.str, x: item.transform[4], width: item.width, height: item.height });
    }
    return rows.sort((a, b) => a.y - b.y).map(row => ({ ...row, items: row.items.sort((a, b) => a.x - b.x) }));
}

export function questionRegions(items, width, height) {
    const lines = textLines(items, height);
    const pageText = lines.map(row => joinPdfText(row.items)).join('\n');
    // Worked examples/solutions are deliberately excluded, not interpreted as test questions.
    if (/\b(example|solution|worked example)s?\b/i.test(pageText)) return { regions: [], reason: 'worked_examples_or_solutions' };
    if (/revision\s+exercise/i.test(pageText)) return { regions: [], reason: 'mixed_revision_exercise' };
    const exercise = lines.find(row => /exercise\s*\d*/i.test(row.items.map(item => item.text).join('').replace(/\s+/g, '')));
    if (!exercise) return { regions: [], reason: 'no_exercise_heading' };
    const markers = [];
    for (const row of lines.filter(row => row.y > exercise.y + 8 && row.y < height - 45)) {
        const explicit = joinPdfText(row.items).match(/^Question\s*(\d{1,2})\s*\./i);
        if (explicit) {
            markers.push({ x: row.items[0].x, y: row.y, label: `${explicit[1]}.`, numeric: true, explicit: true });
            continue;
        }
        const chunks = [];
        for (const item of row.items) {
            const prior = chunks.at(-1);
            if (prior && item.x - prior.end < 3) { prior.text += item.text; prior.end = item.x + item.width; }
            else chunks.push({ ...item, end: item.x + item.width });
        }
        for (const chunk of chunks) {
            const match = chunk.text.trim().match(/^(\d{1,2}[.)]|\([a-z]\)|[a-z]\))(?=\s|$)/i);
            // A label starts its column. Numbers at the end of an equation/sentence are not labels.
            const columnStart = row.items.filter(item => (item.x < width / 2) === (chunk.x < width / 2))[0]?.x;
            if (match && chunk.x < width * 0.75 && Math.abs(chunk.x - columnStart) < 3) markers.push({ x: chunk.x, y: row.y, label: match[1], numeric: /^\d/.test(match[1]) });
        }
    }
    // Prefer a whole numbered question over separating its lettered subparts.
    const selected = markers.some(marker => marker.explicit) ? markers.filter(marker => marker.explicit)
        : markers.some(marker => marker.numeric) ? markers.filter(marker => marker.numeric) : markers;
    if (!selected.length) return { regions: [], reason: 'no_question_boundaries' };
    const columns = [];
    for (const marker of [...selected].sort((a, b) => a.x - b.x)) {
        let column = columns.find(column => Math.abs(column.x - marker.x) < 24);
        if (!column) columns.push(column = { x: marker.x, markers: [] });
        column.markers.push(marker);
    }
    if (columns.length > 2) return { regions: [], reason: 'ambiguous_columns' };
    const firstY = Math.min(...selected.map(marker => marker.y));
    const context = { x: 35, y: Math.max(0, exercise.y - 20), width: width - 70, height: firstY - exercise.y - 4 };
    // Match the instruction crop's lower edge; superscripts above the first question belong to its body.
    const contextBottom = context.y + context.height;
    const contextText = lines.filter(row => row.y >= exercise.y && row.y < contextBottom).map(row => joinPdfText(row.items)).join('\n');
    if (/following\s+(triangle|diagram|figure)|(?:diagram|figure)\s+(?:below|shown)/i.test(contextText)) {
        // Several subparts may refer to one diagram beside/below them. Keep the whole group intact.
        return { regions: [{ label: 'Shared diagram exercise', rect: { x: 35, y: firstY - 24, width: width - 60, height: height - 45 - (firstY - 24) }, context, contextText }], reason: null };
    }
    const regions = [];
    for (let c = 0; c < columns.length; c++) {
        const column = columns[c];
        const ordered = column.markers.sort((a, b) => a.y - b.y);
        for (let i = 0; i < ordered.length; i++) {
            const marker = ordered[i];
            const left = Math.max(20, column.x - 8);
            const right = columns[c + 1] ? columns[c + 1].x - 16 : width - 25;
            const top = Math.max(0, marker.y - 24);
            const bottom = ordered[i + 1] ? ordered[i + 1].y - 28 : height - 45;
            if (bottom - top < 20 || right - left < 60) continue;
            let rect = { x: left, y: top, width: right - left, height: bottom - top };
            let questionText = null;
            const preserveDiagram = /triangle|diagram|graph|shape|figure|draw|sketch|pythagoras/i.test(pageText);
            if (!preserveDiagram) {
                const body = lines.filter(row => row.y >= marker.y - 9 && row.y < bottom).flatMap(row => row.items.map(item => ({ ...item, y: row.y })))
                    .filter(item => item.x >= left && item.x + item.width <= right + 2 && !/^ans(?:wer)?\s*[:(]/i.test(item.text.trim()))
                    .filter(item => !(Math.abs(item.y - marker.y) < 3 && item.text.trim() === marker.label) && !/^[_—–]{3,}$/.test(item.text.trim()))
                    .filter(item => !marker.explicit || item.y > marker.y + 4);
                if (body.length) {
                    const prose = body.map(item => item.text).join(' ').replace(/\s+([.,:;!?])/g, '$1').replace(/\s+/g, ' ').trim();
                    const hasMathLayout = body.some(item => item.height < 10) || /[=×÷√^/⁰¹²³⁴⁵⁶⁷⁸⁹𝑥𝒙]/u.test(prose);
                    if (!hasMathLayout && prose.split(/\s+/).length >= 12) questionText = prose;
                    const minX = Math.min(...body.map(item => item.x));
                    const maxX = Math.max(...body.map(item => item.x + item.width));
                    const minY = Math.min(...body.map(item => item.y - item.height));
                    const maxY = Math.max(...body.map(item => item.y + 4));
                    rect = { x: Math.max(left, minX - 5), y: Math.max(top, marker.explicit ? marker.y + 4 : minY - 5), width: Math.min(right, maxX + 5) - Math.max(left, minX - 5), height: maxY + 5 - Math.max(top, marker.explicit ? marker.y + 4 : minY - 5) };
                }
            }
            regions.push({ label: marker.label, rect, context, contextText, questionText });
        }
    }
    return { regions, reason: regions.length ? null : 'invalid_regions' };
}

export function cleanInstructions(text = '') {
    return text.split('\n').map(line => line.replace(/^\s*(?:revision\s+)?e\s*x\s*e\s*r\s*c\s*i\s*s\s*e\s*\d*\s*(?:[.–—:-]\s*)?/i, '').trim())
        .filter(line => line && !/^question\s*\d+\s*\.?$/i.test(line) && !/^website\s*:/i.test(line)).join('\n');
}

export function selectTest(questions, topics, count = 15, choose = randomInt) {
    if (topics.length !== 3 || new Set(topics).size !== 3) throw new Error('Choose exactly three different topics.');
    if (!Number.isInteger(count) || count < 10 || count > 15) throw new Error('Question count must be between 10 and 15.');
    const scopes = new Set(questions.filter(q => topics.includes(q.topic)).map(q => `${q.grade_id}:${q.subject_id}`));
    if (scopes.size !== 1) throw new Error('All three topics must belong to one grade and subject.');
    const slots = [];
    for (let i = 0; i < topics.length; i++) {
        const quota = Math.floor(count / 3) + (i < count % 3 ? 1 : 0);
        const pool = [...new Map(questions.filter(q => q.topic === topics[i]).map(q => [q.id, q])).values()];
        if (pool.length < quota) throw new Error(`Insufficient questions for ${topics[i]}: need ${quota}, found ${pool.length}.`);
        for (let j = pool.length - 1; j > 0; j--) {
            const k = choose(j + 1);
            [pool[j], pool[k]] = [pool[k], pool[j]];
        }
        for (let j = 0; j < quota; j++) slots.push({ pool });
    }
    // A shared question may belong to multiple topics. Reassign earlier choices rather than falsely reporting a shortage.
    const owners = new Map(), assigned = new Map();
    function assign(slot, visited) {
        for (const question of slots[slot].pool) {
            if (visited.has(question.id)) continue;
            visited.add(question.id);
            if (!owners.has(question.id) || assign(owners.get(question.id), visited)) {
                owners.set(question.id, slot); assigned.set(slot, question); return true;
            }
        }
        return false;
    }
    for (let i = 0; i < slots.length; i++) if (!assign(i, new Set())) throw new Error('Insufficient distinct questions across the three topic pools.');
    const selected = slots.map((_, i) => assigned.get(i));
    // Mix topics as well as randomly choosing their questions.
    for (let i = selected.length - 1; i > 0; i--) {
        const j = choose(i + 1);
        [selected[i], selected[j]] = [selected[j], selected[i]];
    }
    return selected;
}
