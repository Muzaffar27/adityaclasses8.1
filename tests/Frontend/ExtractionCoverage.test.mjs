import test from 'node:test';
import assert from 'node:assert/strict';
import { skippedPageEvidence } from '../../scripts/questions/structured-pdf-source.mjs';
import { questionRegions, withoutPageFooter } from '../../scripts/questions/pdf-question-core.mjs';
const item = (str, y=100) => ({str,width:str.length*6,height:12,transform:[12,0,0,12,50,840-y]});
test('skipped continuation pages with question labels are flagged without requiring an exercise heading',()=>{
    const result=skippedPageEvidence([item('(d) Find x.'),item('(e) Find y.',300)],840,3);
    assert.equal(result.audit_hint,'question_markers_found');
    assert.match(result.raw_text,/\(d\) Find x/);
    assert.equal('resolved' in result,false);
});
test('covers, exercises, revision text and unreadable pages remain distinct inspection hints',()=>{
    assert.equal(skippedPageEvidence([item('Student Name: Grade 8')],840,1).audit_hint,'possible_cover');
    assert.equal(skippedPageEvidence([item('Exercise 1')],840,2).audit_hint,'exercise_heading_found');
    assert.equal(skippedPageEvidence([item('Worked solution')],840,2).audit_hint,'examples_or_revision');
    assert.equal(skippedPageEvidence([],840,2).audit_hint,'no_readable_text');
    assert.equal(skippedPageEvidence([item('Some other content')],840,2).audit_hint,'inspection_required');
});
test('raw audit previews are bounded and question markers take priority over cover/revision wording',()=>{
    assert.equal(skippedPageEvidence([item('Revision'),item('1. Solve this.',150)],840,1).audit_hint,'question_markers_found');
    assert.equal(skippedPageEvidence([item('x'.repeat(4000))],840,2).raw_text.length,3000);
});

test('only isolated bottom-right page numbers with a known page offset are removed',()=>{
    const at=(str,x,y)=>({...item(str,y),transform:[12,0,0,12,x,840-y]});
    const footer=at('1',560,780), denominator=at('1',110,780), otherNumber=at('7',560,740);
    const result=withoutPageFooter([at('EXERCISE 1',50,90),at('1.',50,650),denominator,footer,otherNumber],595,840,2);
    // A row containing another mathematical item is not an isolated footer.
    assert.ok(result.includes(footer));
    const isolated=withoutPageFooter([at('1.',50,150),footer,otherNumber],595,840,2);
    assert.ok(!isolated.includes(footer)); assert.ok(isolated.includes(otherNumber));
});
test('continuation requires increasing labels in matching columns and known prior instructions',()=>{
    const first=questionRegions([item('EXERCISE 1',60),item('Simplify:',90),item('1.',140),item('2.',300)],595,840);
    const next=questionRegions([item('3.',80),item('4.',250)],595,840,first.continuation);
    assert.equal(next.regions.length,2);assert.match(next.regions[0].contextText,/Simplify/);
    assert.equal(questionRegions([item('1.',80)],595,840,first.continuation).reason,'continuation_not_confirmed');
    assert.equal(questionRegions([item('Solve these instead',30),item('3.',100)],595,840,first.continuation).reason,'new_instructions_without_heading');
    assert.equal(questionRegions([item('(c)',80)],595,840,first.continuation).reason,'continuation_not_confirmed');
});
