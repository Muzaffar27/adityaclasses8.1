import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { mkdtemp, mkdir, readFile, writeFile, unlink, rmdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { OPS } from 'pdfjs-dist/legacy/build/pdf.mjs';
import { cleanInstructions, joinPdfText, textLines } from './pdf-question-core.mjs';

const multiply = (a, b) => [a[0]*b[0]+a[2]*b[1], a[1]*b[0]+a[3]*b[1], a[0]*b[2]+a[2]*b[3], a[1]*b[2]+a[3]*b[3], a[0]*b[4]+a[2]*b[5]+a[4], a[1]*b[4]+a[3]*b[5]+a[5]];
const point = (m, x, y) => [m[0]*x+m[2]*y+m[4], m[1]*x+m[3]*y+m[5]];
const inside = (x, y, r) => x >= r.x && x <= r.x+r.width && y >= r.y && y <= r.y+r.height;

// PDF drawing bounds, including nested transforms. Clip-only paths and page borders are not diagrams.
export function drawingBounds(operators, viewport) {
    let matrix = [1,0,0,1,0,0]; const stack = [], bounds = [];
    for (let i = 0; i < operators.fnArray.length; i++) {
        const op = operators.fnArray[i], args = operators.argsArray[i];
        if (op === OPS.save) stack.push([...matrix]);
        else if (op === OPS.restore) matrix = stack.pop() || [1,0,0,1,0,0];
        else if (op === OPS.transform) matrix = multiply(matrix, args);
        else {
            let box;
            if (op === OPS.constructPath && args[0] !== OPS.endPath) box = args[2];
            else if ([OPS.paintImageXObject, OPS.paintInlineImageXObject, OPS.paintImageMaskXObject].includes(op)) box = [0,0,1,1];
            if (!box || box.length !== 4 || !Array.from(box).every(Number.isFinite)) continue;
            const transform = multiply(viewport.transform, matrix);
            const points = [[box[0],box[1]],[box[0],box[3]],[box[2],box[1]],[box[2],box[3]]].map(([x,y]) => point(transform,x,y));
            const x = Math.min(...points.map(p=>p[0])), y = Math.min(...points.map(p=>p[1]));
            const width = Math.max(...points.map(p=>p[0]))-x, height = Math.max(...points.map(p=>p[1]))-y;
            if (width < 8 || height < 8 || width > viewport.width*.85 || height > viewport.height*.85) continue;
            bounds.push({x,y,width,height});
        }
    }
    return bounds;
}

// Keep a shared figure and all its subparts together. Continuations are accepted only with known instructions.
export function diagramRegions(items, width, height, previousInstructions = '') {
    const lines = textLines(items, height);
    const text = lines.map(row => joinPdfText(row.items)).join('\n');
    if (/\b(examples?|solutions?|worked example)\b|revision\s+exercise/i.test(text)) return {regions:[], reason:'worked_examples_or_revision'};
    const heading = lines.find(row => /exercise\s*\d*/i.test(joinPdfText(row.items)));
    if (!heading && !previousInstructions) return {regions:[], reason:'no_exercise_heading'};
    const candidates = lines.filter(row => row.y > (heading?.y ?? 0)+8 && row.y < height-45)
        .flatMap(row => row.items.filter(item => item.x < width*.75).map(item => {
            const match = item.text.trim().match(/^(?:Question\s*)?(\d{1,2}[.)]|\([a-z]\)|[a-z]\))(?=\s|$)/i);
            return match ? {x:item.x,y:row.y,label:match[1],numeric:/^\d/.test(match[1])} : null;
        }).filter(Boolean));
    const primary = candidates.some(m=>m.numeric) ? candidates.filter(m=>m.numeric) : candidates;
    const left = Math.min(...primary.map(m=>m.x));
    const markers = primary.filter(m=>!(primary.some(p=>p.label==='(a)') && m.label==='(i)' && m.x>left+8 && m.x<left+width*.3));
    if (!markers.length) return {regions:[], reason:'no_question_boundaries'};
    const firstY = Math.min(...markers.map(m=>m.y));
    const instructions = heading ? cleanInstructions(lines.filter(row=>row.y>=heading.y && row.y<firstY-10).map(row=>joinPdfText(row.items)).join('\n')) : previousInstructions;
    if (/following\s+(triangle|diagram|figure)|(?:diagram|figure)\s+(?:below|shown)/i.test(instructions)) {
        return {instructions, regions:[{label:'Shared diagram exercise',rect:{x:20,y:firstY-24,width:width-30,height:height-45-(firstY-24)}}]};
    }
    const columns = [];
    for (const marker of markers.sort((a,b)=>a.x-b.x)) {
        let column = columns.find(c=>Math.abs(c.x-marker.x)<24);
        if (!column) columns.push(column={x:marker.x,markers:[]});
        column.markers.push(marker);
    }
    if (columns.length>2) return {regions:[],reason:'ambiguous_columns'};
    const regions=[];
    for(let c=0;c<columns.length;c++) {
        const ordered=columns[c].markers.sort((a,b)=>a.y-b.y);
        for(let i=0;i<ordered.length;i++) {
            const m=ordered[i],x=Math.max(20,m.x-8),y=Math.max(0,m.y-24);
            regions.push({label:m.label,rect:{x,y,width:(columns[c+1]?.x-16 || width-10)-x,height:(ordered[i+1]?.y-28 || height-45)-y}});
        }
    }
    return {instructions,regions};
}

export async function extractDiagramRegion({root, bytes, page, region, graphics, items, instructions, width, height}) {
    if (!root) throw new Error('diagram_storage_unavailable');
    const selected = graphics.filter(g=>inside(g.x+g.width/2,g.y+g.height/2,region.rect));
    if (!selected.length) throw new Error('diagram_not_located');
    if (selected.some(g=>g.x<region.rect.x-2 || g.x+g.width>region.rect.x+region.rect.width+2 || g.y<region.rect.y-2 || g.y+g.height>region.rect.y+region.rect.height+2)) throw new Error('diagram_crosses_question_boundary');
    const rows = textLines(items,height).map(row=>({...row,items:row.items.filter(item=>inside(item.x+item.width/2,row.y,region.rect))}));
    const sentences = rows.map(row=> {
        const prefix=[]; let end=null;
        for (const item of row.items) {
            if (end!==null && item.x-end>20) break;
            prefix.push(item); end=item.x+item.width;
        }
        const line=joinPdfText(prefix);
        return line.split(/\s+/).length>=6 && !/^ans(?:wer)?\b/i.test(line) ? {...row,items:prefix,line,end} : null;
    }).filter(Boolean);
    const groups=[];
    for (const graphic of selected.sort((a,b)=>a.y-b.y)) {
        const group=groups.find(g=>graphic.y<=g.bottom+45 && graphic.y+graphic.height>=g.top-45);
        if(group) {group.items.push(graphic);group.bottom=Math.max(group.bottom,graphic.y+graphic.height);group.top=Math.min(group.top,graphic.y);}
        else groups.push({items:[graphic],top:graphic.y,bottom:graphic.y+graphic.height});
    }
    if(groups.length>12) throw new Error('too_many_diagram_groups');
    const crops=groups.map(group=> {
        const minX=Math.min(...group.items.map(g=>g.x));
        let x=Math.max(0,Math.floor(minX-28)), y=Math.max(region.rect.y,Math.floor(group.top-28));
        let right=Math.min(width,Math.ceil(Math.max(...group.items.map(g=>g.x+g.width))+28));
        let bottom=Math.min(region.rect.y+region.rect.height,height,Math.ceil(group.bottom+28));
        // Keep prompt text outside the picture, including units at the end of long sentences.
        for(const sentence of sentences) if(sentence.y>=y && sentence.y<=bottom && sentence.end+6<minX) x=Math.max(x,Math.ceil(sentence.end+6));
        // Include complete short labels that would otherwise touch a crop edge.
        for(const row of rows.filter(row=>!sentences.some(s=>s.y===row.y))) for(const item of row.items) {
            if(item.x<right && item.x+item.width>x && row.y>=y && row.y-item.height<=bottom) {
                x=Math.max(0,Math.min(x,Math.floor(item.x-3)));
                right=Math.min(width,Math.max(right,Math.ceil(item.x+item.width+3)));
                y=Math.max(region.rect.y,Math.min(y,Math.floor(row.y-item.height-3)));
                bottom=Math.min(region.rect.y+region.rect.height,Math.max(bottom,Math.ceil(row.y+4)));
            }
        }
        return {x,y,width:right-x,height:bottom-y};
    });
    const proseLines=rows.map(row=> {
        const sentence=sentences.find(s=>s.y===row.y);
        return (sentence ? sentence.line : joinPdfText(row.items.filter(item=>item.text.trim()!==region.label && !crops.some(c=>inside(item.x+item.width/2,row.y,c)))));
    }).filter(line=>line && !/^ans(?:wer)?\b|^[_\s]+$/i.test(line));
    const combined=proseLines.join('\n').trim();
    const text=combined.startsWith(region.label) ? combined.slice(region.label.length).trim() : combined;
    if (!text && !instructions) throw new Error('diagram_question_instructions_missing');
    const temporary=await mkdtemp(path.join(tmpdir(),'aditya-diagram-'));
    const input=path.join(temporary,'source.pdf'),output=path.join(temporary,'diagram');
    const diagrams=[];
    try {
        await writeFile(input,bytes);
        await mkdir(path.join(root,'structured/diagrams'),{recursive:true});
        for(const crop of crops) {
            execFileSync('pdftoppm',['-f',String(page),'-l',String(page),'-r','144','-x',String(Math.floor(crop.x*2)),'-y',String(Math.floor(crop.y*2)),'-W',String(Math.ceil(crop.width*2)),'-H',String(Math.ceil(crop.height*2)),'-singlefile','-png',input,output],{stdio:['ignore','ignore','pipe'],timeout:60000,windowsHide:true});
            const png=await readFile(output+'.png');
            const sha256=createHash('sha256').update(png).digest('hex');
            const relative='structured/diagrams/'+sha256+'.png';
            await writeFile(path.join(root,relative),png);
            diagrams.push({sha256,path:relative,width:png.readUInt32BE(16),height:png.readUInt32BE(20)});
        }
    } finally {
        await unlink(input).catch(()=>{}); await unlink(output+'.png').catch(()=>{}); await rmdir(temporary);
    }
    return {text:text || 'Diagram:', diagrams};
}
