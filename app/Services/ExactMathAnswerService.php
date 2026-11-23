<?php

namespace App\Services;

use DomainException;

/** Bounded rational arithmetic; no eval, symbolic guessing, network or AI calls. */
class ExactMathAnswerService
{
    public const VERSION = 'local-exact-maths-v1';
    private const LIMIT = 1000000000;
    private int $nodes = 0;
    private array $steps = [];

    public function suggest(array $content): array
    {
        $this->nodes = 0; $this->steps = [];
        try {
            if (!empty($content['diagrams']) || !empty($content['text']) || !isset($content['math'])) {
                throw new DomainException('This question needs tutor or reviewed AI interpretation.');
            }
            if (!preg_match('/^\s*(?:Evaluate|Calculate|Simplify|Work out)(?: the following)?\s*[:.]?\s*$/iu', $content['instructions'] ?? '')) {
                throw new DomainException('The instruction requires interpretation or a particular answer form.');
            }
            $value = $this->node($content['math'], 0);
            $answer = $this->format($value);
            $steps = $this->steps ?: ['The given value is '.$answer.'.'];
            return ['supported' => true, 'generator' => self::VERSION, 'answer' => ['final_answer' => $answer,
                'accepted_answers' => [$answer], 'working' => $steps, 'marks' => null, 'marking_method' => 'exact_numeric',
                'answer_form' => preg_match('/^\s*Simplify/i', $content['instructions']) ? 'lowest_terms_fraction' : 'equivalent_numeric',
                'unit' => null, 'rubric' => []], 'exact_value' => ['numerator' => $value[0], 'denominator' => $value[1]]];
        } catch (DomainException $e) {
            return ['supported' => false, 'generator' => self::VERSION, 'reason' => $e->getMessage()];
        }
    }

    public function literal(string $text): array
    {
        $text = trim(str_replace(['−', '–'], '-', $text));
        if (preg_match('/^(-?)(\d{1,10})\s+(\d{1,10})\/(\d{1,10})$/D', $text, $m)) {
            $fraction = $this->rational((int) $m[3], (int) $m[4]);
            if ($fraction[0] < 0 || $fraction[0] >= $fraction[1]) throw new DomainException('Use a proper fractional part in a mixed number.');
            $value = $this->add([(int) $m[2], 1], $fraction);
            return $m[1] === '-' ? [-$value[0], $value[1]] : $value;
        }
        if (preg_match('/^(-?\d{1,10})\/(-?\d{1,10})$/D', $text, $m)) return $this->rational((int) $m[1], (int) $m[2]);
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,6}))?$/D', $text, $m)) throw new DomainException('Use a bounded number, decimal, fraction or mixed number.');
        $denominator = 10 ** strlen($m[3] ?? '');
        $numerator = $this->safeMultiply((int) $m[2], $denominator) + (int) ($m[3] ?? 0);
        return $this->rational($m[1] === '-' ? -$numerator : $numerator, $denominator);
    }

    public function acceptsForm(string $text, string $form): bool
    {
        $text = trim($text);
        $value = $this->literal($text);
        if ($form === 'equivalent_numeric') return true;
        if ($form === 'decimal') return (bool) preg_match('/^-?\d+(?:\.\d+)?$/D', $text);
        if ($form !== 'lowest_terms_fraction') return false;
        if ($text === $this->format($value)) return true;
        if (preg_match('/^-?\d+\s+(\d+)\/(\d+)$/D', $text, $m)) return $this->gcd((int) $m[1], (int) $m[2]) === 1;
        return false;
    }

    private function node(mixed $node, int $depth): array
    {
        if (!is_array($node)) throw new DomainException('Malformed maths needs tutor review.');
        if ($depth > 16 || ++$this->nodes > 150) throw new DomainException('Expression is beyond the local solver limits.');
        switch ($node['type'] ?? '') {
            case 'number': return $this->literal((string) ($node['value'] ?? ''));
            case 'fraction':
                $numerator = $this->node($node['numerator'] ?? null, $depth + 1);
                $denominator = $this->node($node['denominator'] ?? null, $depth + 1);
                if (($node['numerator']['type'] ?? '') === 'number' && ($node['denominator']['type'] ?? '') === 'number') {
                    return $this->multiply($numerator, $this->rational($denominator[1], $denominator[0]));
                }
                return $this->record('/', $numerator, $denominator);
            case 'power':
                $base = $this->node($node['base'] ?? null, $depth + 1); $power = $this->node($node['exponent'] ?? null, $depth + 1);
                if ($power[1] !== 1 || abs($power[0]) > 12 || ($base[0] === 0 && $power[0] === 0)) throw new DomainException('Power needs tutor review.');
                $result = [1, 1];
                for ($i = 0; $i < abs($power[0]); $i++) $result = $this->multiply($result, $base);
                if ($power[0] < 0) $result = $this->rational($result[1], $result[0]);
                $this->steps[] = $this->format($base).'^'.$power[0].' = '.$this->format($result);
                return $result;
            case 'expression': return $this->expression($node['children'] ?? [], $depth + 1);
            default: throw new DomainException('Variables, roots and unsupported notation need tutor or reviewed AI input.');
        }
    }

    private function expression(mixed $children, int $depth): array
    {
        if (!is_array($children) || !array_is_list($children) || !$children || count($children) > 100
            || array_filter($children, fn ($n) => !is_array($n) || !isset($n['type']))) throw new DomainException('Expression is malformed or too large.');
        if (($children[array_key_last($children)]['type'] ?? '') === 'operator'
            && ($children[array_key_last($children)]['value'] ?? '') === '=') array_pop($children);
        $position = 0;
        $parse = function (int $minimum = 0, int $nesting = 0) use (&$parse, &$position, $children, $depth): array {
            if ($nesting > 16) throw new DomainException('Too many nested brackets.');
            $token = $children[$position++] ?? null;
            if (!$token) throw new DomainException('Incomplete expression.');
            $operator = $token['type'] === 'operator' ? $this->operator($token['value']) : null;
            if ($operator === '+' || $operator === '-') {
                $left = $parse(30, $nesting + 1);
                if ($operator === '-') $left[0] = -$left[0];
            } elseif ($operator === '(') {
                $left = $parse(0, $nesting + 1);
                if (($children[$position]['type'] ?? '') !== 'operator' || ($children[$position++]['value'] ?? '') !== ')') throw new DomainException('Unbalanced brackets.');
            } elseif ($operator !== null) throw new DomainException('Unexpected operator.');
            else {
                $left = $this->node($token, $depth);
                $next = $children[$position] ?? [];
                // Extracted mixed numbers are adjacent whole-number and fraction nodes.
                if ($token['type'] === 'number' && preg_match('/^\d+$/D', $token['value']) && ($next['type'] ?? '') === 'fraction') {
                    $fraction = $this->node($next, $depth);
                    if ($fraction[0] < 0 || $fraction[0] >= $fraction[1]) throw new DomainException('Ambiguous adjacent number/fraction.');
                    $left = $this->record('+', $left, $fraction); $position++;
                }
            }
            while (isset($children[$position])) {
                $next = $children[$position];
                if (($next['type'] ?? '') !== 'operator') throw new DomainException('Implicit multiplication or ambiguous layout needs review.');
                $operation = $this->operator($next['value']);
                $precedence = ['+' => 10, '-' => 10, '*' => 20, '/' => 20][$operation] ?? -1;
                if ($operation === ')' || $precedence < $minimum) break;
                if ($precedence < 0) throw new DomainException('Unsupported operator.');
                $position++;
                $right = $parse($precedence + 1, $nesting + 1);
                $left = $this->record($operation, $left, $right);
            }
            return $left;
        };
        $result = $parse();
        if ($position !== count($children)) throw new DomainException('The complete expression could not be interpreted.');
        return $result;
    }

    private function operator(string $value): string
    {
        return strtr($value, ['×' => '*', '÷' => '/', '−' => '-', '–' => '-']);
    }

    private function record(string $operation, array $a, array $b): array
    {
        $result = match ($operation) {
            '+' => $this->add($a, $b), '-' => $this->add($a, [-$b[0], $b[1]]),
            '*' => $this->multiply($a, $b), '/' => $this->multiply($a, $this->rational($b[1], $b[0])),
        };
        if (in_array($operation, ['+', '-'], true) && $a[1] !== $b[1]) {
            $common = $this->safeMultiply($a[1], intdiv($b[1], $this->gcd($a[1], $b[1])));
            $this->steps[] = 'Use a common denominator of '.$common.': '
                .$this->safeMultiply($a[0], intdiv($common, $a[1])).'/'.$common.' '.$operation.' '
                .$this->safeMultiply($b[0], intdiv($common, $b[1])).'/'.$common.'.';
        }
        $this->steps[] = '('.$this->format($a).') '.$operation.' ('.$this->format($b).') = '.$this->format($result);
        if (count($this->steps) > 40) throw new DomainException('Too many working steps.');
        return $result;
    }

    private function add(array $a, array $b): array
    {
        $gcd = $this->gcd($a[1], $b[1]);
        return $this->rational($this->safeMultiply($a[0], intdiv($b[1], $gcd)) + $this->safeMultiply($b[0], intdiv($a[1], $gcd)),
            $this->safeMultiply($a[1], intdiv($b[1], $gcd)));
    }

    private function multiply(array $a, array $b): array
    {
        $x = $this->gcd(abs($a[0]), $b[1]); $y = $this->gcd(abs($b[0]), $a[1]);
        return $this->rational($this->safeMultiply(intdiv($a[0], $x), intdiv($b[0], $y)),
            $this->safeMultiply(intdiv($a[1], $y), intdiv($b[1], $x)));
    }

    private function safeMultiply(int $a, int $b): int
    {
        if ($b !== 0 && abs($a) > intdiv(self::LIMIT, abs($b))) throw new DomainException('Number is beyond exact local solver limits.');
        return $a * $b;
    }

    private function rational(int $n, int $d): array
    {
        if ($d === 0) throw new DomainException('Division by zero needs review.');
        if (abs($n) > self::LIMIT || abs($d) > self::LIMIT) throw new DomainException('Number is beyond exact local solver limits.');
        if ($d < 0) { $n = -$n; $d = -$d; }
        $gcd = $this->gcd(abs($n), $d);
        return [intdiv($n, $gcd), intdiv($d, $gcd)];
    }

    private function gcd(int $a, int $b): int
    {
        while ($b) { [$a, $b] = [$b, $a % $b]; }
        return $a ?: 1;
    }

    private function format(array $value): string
    {
        return $value[1] === 1 ? (string) $value[0] : $value[0].'/'.$value[1];
    }
}
