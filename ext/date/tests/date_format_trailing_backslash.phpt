--TEST--
date() and DateTime::format() with a trailing backslash in the format
--FILE--
<?php
date_default_timezone_set('UTC');

$d = new DateTimeImmutable('2024-02-03 04:05:06');

var_dump(bin2hex(date('Y\\', 0)));
var_dump(bin2hex(gmdate('\\', 0)));
var_dump(bin2hex($d->format('Y-m-d\\')));
var_dump(bin2hex(date_format(new DateTime('@0'), '\\\\')));
var_dump($d->format('\\Y\\'));
var_dump($d->format('\\\\Y\\\\'));
?>
--EXPECT--
string(10) "313937305c"
string(2) "5c"
string(22) "323032342d30322d30335c"
string(2) "5c"
string(2) "Y\"
string(6) "\2024\"
