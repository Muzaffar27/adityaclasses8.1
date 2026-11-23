<?php

namespace Tests\Unit;

use App\Services\ExactMathAnswerService;
use PHPUnit\Framework\TestCase;

class ExactMathAnswerTest extends TestCase
{
    private function n(string $value): array { return ['type' => 'number', 'value' => $value]; }
    private function op(string $value): array { return ['type' => 'operator', 'value' => $value]; }
    private function f(string $n, string $d): array { return ['type' => 'fraction', 'numerator' => $this->n($n), 'denominator' => $this->n($d)]; }
    private function solve(array $children, string $instruction = 'Evaluate the following:'): array
    {
        return (new ExactMathAnswerService)->suggest(['instructions' => $instruction, 'math' => ['type' => 'expression', 'children' => $children]]);
    }

    public function test_fractions_decimals_and_alternatives_are_exact(): void
    {
        $result = $this->solve([$this->f('3', '4'), $this->op('+'), $this->f('1', '2'), $this->op('=')]);
        $this->assertTrue($result['supported']);
        $this->assertSame('5/4', $result['answer']['final_answer']);
        $maths = new ExactMathAnswerService;
        foreach (['1.25', '5/4', '1 1/4', '10/8'] as $answer) $this->assertSame([5, 4], $maths->literal($answer));
        $result = $this->solve([$this->n('0.3'), $this->op('÷'), $this->n('2')]);
        $this->assertSame('3/20', $result['answer']['final_answer']);
        $this->assertNull($result['answer']['marks']);
        $this->assertFalse($maths->acceptsForm('10/8', 'lowest_terms_fraction'));
        $this->assertTrue($maths->acceptsForm('5/4', 'lowest_terms_fraction'));
        $this->assertFalse($maths->acceptsForm('1.25', 'lowest_terms_fraction'));
        $this->assertTrue($maths->acceptsForm('1.25', 'decimal'));
    }

    public function test_precedence_brackets_unary_signs_and_mixed_numbers(): void
    {
        $result = $this->solve([$this->n('5'), $this->op('×'), $this->n('4'), $this->op('-'), $this->op('('),
            $this->n('5'), $this->op('-'), $this->n('6'), $this->op('÷'), $this->n('3'), $this->op(')'), $this->op('×'), $this->n('2')]);
        $this->assertSame('14', $result['answer']['final_answer']);
        $result = $this->solve([$this->n('5'), $this->f('1', '3'), $this->op('+'), $this->n('3'), $this->f('1', '3')]);
        $this->assertSame('26/3', $result['answer']['final_answer']);
        $result = $this->solve([$this->op('-'), $this->n('2'), $this->f('1', '3'), $this->op('+'), $this->n('1')]);
        $this->assertSame('-4/3', $result['answer']['final_answer']);
    }

    public function test_numeric_powers_are_bounded_and_required_index_form_is_not_ignored(): void
    {
        $power = ['type' => 'power', 'base' => $this->n('10'), 'exponent' => $this->n('9')];
        $result = $this->solve([$power]);
        $this->assertSame('1000000000', $result['answer']['final_answer']);
        $this->assertSame([1000000000, 1], (new ExactMathAnswerService)->literal($result['answer']['final_answer']));
        $this->assertFalse($this->solve([$power], 'Simplify leaving your answer in index form')['supported']);
        $power['exponent'] = $this->n('10');
        $this->assertFalse($this->solve([$power])['supported']);
    }

    public function test_unsafe_incomplete_or_ambiguous_input_is_rejected_without_execution(): void
    {
        foreach ([[$this->n('1'), $this->op('÷'), $this->n('0')], [$this->n('2'), $this->op('='), $this->n('3')],
            [$this->op('('), $this->n('1')], [$this->n('1'), $this->n('2')], [['type' => 'variable', 'value' => 'x']],
            [$this->n('system("whoami")')], [['type' => 'fraction']], ['malformed']] as $nodes) {
            $this->assertFalse($this->solve($nodes)['supported']);
        }
        $this->assertFalse((new ExactMathAnswerService)->suggest(['instructions' => 'Evaluate', 'text' => 'Read a diagram', 'math' => $this->n('5')])['supported']);
    }
}
