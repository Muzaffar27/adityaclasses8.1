import test from 'node:test';
import assert from 'node:assert/strict';
import { OPS } from 'pdfjs-dist/legacy/build/pdf.mjs';
import { drawingBounds, diagramRegions } from '../../scripts/questions/diagram-extraction.mjs';
import { questionFingerprint } from '../../scripts/questions/lesson-question-bank.mjs';
import { diagramMarkup, renderStructuredTest } from '../../scripts/questions/structured-maths.mjs';

const item = (str, x, y) => ({str,width:str.length*6,height:12,transform:[12,0,0,12,x,840-y]});
test('diagram bounds apply saved transforms and omit clip paths and page backgrounds', () => {
    const graphics = drawingBounds({fnArray:[OPS.save,OPS.transform,OPS.constructPath,OPS.restore,OPS.constructPath,OPS.constructPath],
        argsArray:[null,[1,0,0,1,100,200],[OPS.stroke,[],[0,0,50,60]],null,[OPS.endPath,[],[0,0,200,200]],[OPS.fill,[],[0,0,595,840]]]},
    {width:595,height:840,transform:[1,0,0,-1,0,840]});
    assert.deepEqual(graphics,[{x:100,y:580,width:50,height:60}]);
});
test('roman subparts remain with their parent diagram question', () => {
    const result=diagramRegions([item('EXERCISE 5',50,70),item('(a) A rectangle has length 8 and width 6.',54,115),item('(i) Find its diagonal.',78,135),item('(b) A ladder leans against a wall.',54,550)],595,840);
    assert.deepEqual(result.regions.map(r=>r.label),['(a)','(b)']);
    assert.ok(result.regions[0].rect.width>500);
});
test('shared diagrams retain subparts and known instructions continue onto the next page', () => {
    const result=diagramRegions([item('EXERCISE 3',50,70),item('Consider the following triangle.',50,95),item('(a) Find x.',50,140),item('(b) Find y.',50,180)],595,840);
    assert.equal(result.regions.length,1);
    assert.equal(result.regions[0].label,'Shared diagram exercise');
    const continuation=diagramRegions([item('(d)',50,70),item('(e)',50,300)],595,840,'Find the unknown x.');
    assert.equal(continuation.instructions,'Find the unknown x.');
    assert.equal(continuation.regions.length,2);
    assert.equal(diagramRegions([item('(d)',50,70)],595,840).regions.length,0);
});
test('worked diagram examples cannot enter the question bank',()=>{
    assert.equal(diagramRegions([item('Exercise 1',50,60),item('Worked example',50,90),item('(a)',50,130)],595,840).regions.length,0);
});
test('diagram identity is included in approval fingerprint and unsafe asset paths are rejected',()=>{
    const a='a'.repeat(64), b='b'.repeat(64);
    const question={grade_id:8,subject_id:1,instructions:'Find x.',text:'Diagram:',diagrams:[{sha256:a,path:`structured/diagrams/${a}.png`,width:100,height:100}]};
    const changed={...question,diagrams:[{...question.diagrams[0],sha256:b,path:`structured/diagrams/${b}.png`}]};
    assert.notEqual(questionFingerprint(question),questionFingerprint(changed));
    assert.throws(()=>questionFingerprint({...question,diagrams:[{...question.diagrams[0],path:'../outside.png'}]}),/Invalid diagram/);
});
test('standalone structured previews retain diagram references with safe paths',()=>{
    const diagrams=[{sha256:'a'.repeat(64)}];
    const html=renderStructuredTest({count:1,questions:[{text:'Find the diagonal.',diagrams}]});
    assert.match(html,/<img src="diagrams\/a{64}\.png"/);
    assert.throws(()=>diagramMarkup([{sha256:'<script>'}]),/Invalid diagram hash/);
});
