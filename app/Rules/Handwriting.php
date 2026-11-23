<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Handwriting implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value) || count($value) !== 2 || ($value['v'] ?? null) !== 1
            || !isset($value['strokes']) || !is_array($value['strokes']) || !array_is_list($value['strokes'])
            || count($value['strokes']) > 200) {
            $fail('Handwriting must contain valid pen strokes.');
            return;
        }

        $points = 0;
        foreach ($value['strokes'] as $stroke) {
            if (!is_array($stroke) || !array_is_list($stroke) || count($stroke) < 1 || count($stroke) > 500) {
                $fail('A handwriting stroke must contain between 1 and 500 points.');
                return;
            }
            $points += count($stroke);
            if ($points > 5000) {
                $fail('Handwriting has reached its point limit.');
                return;
            }
            foreach ($stroke as $point) {
                if (!is_array($point) || !array_is_list($point) || count($point) !== 2) {
                    $fail('Handwriting points must contain two coordinates.');
                    return;
                }
                foreach ($point as $coordinate) {
                    if ((!is_int($coordinate) && !is_float($coordinate)) || !is_finite($coordinate)
                        || $coordinate < 0 || $coordinate > 1) {
                        $fail('Handwriting coordinates must be numbers between 0 and 1.');
                        return;
                    }
                }
            }
        }
    }
}
