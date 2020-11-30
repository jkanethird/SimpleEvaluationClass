<?php

class Evaluator {
    private array $_token_fns;

    private static function filter($var){
        return $var !== null;
    }

    private static array $_find = ['/(\()/', '/^/', '/(\))/', '/$/', '/(&&)/', '/(\|\|)/'];
    private static array $_repl = ['$1 ( (', '( ( ', ') ) $1', ' ) )', ') $1 (', ') ) $1 ( (' ];

    public function __construct() {
        $value_fns = [
            'TRUE' => function($carry) {
                array_shift($carry);
                return [true, ...$carry];
            },
            'FALSE' => function($carry) {
                array_shift($carry);
                return [false, ...$carry];
            },
            '&&' => function ($carry) {
                $actor = array_shift($carry);
                return [function ($next_carry) use ($actor) {
                    return $actor && $next_carry;
                }, ...$carry];
            },
            '||' => function ($carry) {
                $actor = array_shift($carry);
                return [function ($next_carry) use ($actor) {
                    return $actor || $next_carry;
                }, ...$carry];
            },
            '!' => function ($carry) {
                array_shift($carry);
                return [function ($next_carry) { return !$next_carry; }, ...$carry];
            },
            '(' => function ($carry) { return [null, $carry]; },
            ')' => function ($carry) {
                $value = array_shift($carry);
                $prev_carry = array_shift($carry);
                return is_callable($prev_carry) ?
                    [$prev_carry($value), ...$carry] :
                    array_filter([$value] + $prev_carry + $carry, 'Evaluator::filter');
            },
        ];

        $this->_token_fns = [
            'boolean' => $value_fns,
            'NULL' => $value_fns,
            'object' => [ // PHP says callables/callback functions are objects
                'TRUE' => function ($carry) {
                    $actor = array_shift($carry);
                    return [$actor(true), ...$carry];
                },
                'FALSE' => function ($carry) {
                    $actor = array_shift($carry);
                    return [$actor(false), ...$carry];
                },
                '!' => function ($carry) {
                    $actor = array_shift($carry);
                    return [function ($next_carry) use ($actor) { return $actor(!$next_carry); }, ...$carry];
                },
                '(' => function ($carry) { return [null, ...$carry]; },
            ],
        ];
    }

    private function reducer($carry, $item) {
        return $this->_token_fns[(gettype($carry[0]))][$item]($carry);
    }

    public function strictEval($expression) {
        $fixed_expr = preg_replace(static::$_find, static::$_repl, trim($expression));
        $expr_array = preg_split('/\s+/', $fixed_expr, -1, PREG_SPLIT_NO_EMPTY);
        return array_reduce($expr_array, [$this, 'reducer'], [null])[0];
    }
}

function tester ($expression) {
    $eval = New Evaluator();
    return $eval->strictEval($expression) === eval("return {$expression};");
}

var_dump(tester('TRUE && ! TRUE || FALSE && TRUE'));

var_dump(tester('( TRUE && ! ( FALSE || ! TRUE ) )'));

var_dump(tester('( TRUE && ! TRUE ) || ( ( ( FALSE || TRUE ) || ( TRUE && FALSE ) ) || FALSE ) || FALSE && TRUE'));

var_dump(tester('TRUE && ! FALSE || TRUE || TRUE && FALSE'));