--TEST--
extract() with EXTR_PREFIX_IF_EXISTS does not create variables that do not exist
--FILE--
<?php
function test(int $flags) {
    $b = 'orig';
    $arr = ['a' => 1, 'b' => 2];
    $n = extract($arr, $flags, 'p');
    // $a is a compiled variable here, but it is undefined before extract()
    var_dump($n, isset($a), isset($p_a), $b, $p_b);
}

test(EXTR_PREFIX_IF_EXISTS);
test(EXTR_PREFIX_IF_EXISTS | EXTR_REFS);

// EXTR_IF_EXISTS already skips undefined compiled variables
function test_if_exists() {
    $n = extract(['a' => 1], EXTR_IF_EXISTS);
    var_dump($n, isset($a));
}
test_if_exists();
?>
--EXPECT--
int(1)
bool(false)
bool(false)
string(4) "orig"
int(2)
int(1)
bool(false)
bool(false)
string(4) "orig"
int(2)
int(0)
bool(false)
