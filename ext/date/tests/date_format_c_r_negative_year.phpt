--TEST--
date() "c" and "r" formats pad negative years to four digits like "Y"
--FILE--
<?php
date_default_timezone_set('UTC');

foreach (['-0044-03-15 12:00:00', '-0999-06-28', '-0001-01-01', '-12345-01-01', '0005-01-01'] as $str) {
    $d = new DateTimeImmutable($str);
    echo $d->format('c'), "\n";
    echo $d->format('r'), "\n";
    var_dump($d->format('c') === $d->format(DATE_ATOM));
    var_dump($d->format('r') === $d->format(DATE_RFC2822));
}

$d = new DateTimeImmutable('-0044-03-15 12:00:00');
try {
    var_dump(new DateTimeImmutable($d->format('c')) == $d);
} catch (DateMalformedStringException $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
-0044-03-15T12:00:00+00:00
Thu, 15 Mar -0044 12:00:00 +0000
bool(true)
bool(true)
-0999-06-28T00:00:00+00:00
Sun, 28 Jun -0999 00:00:00 +0000
bool(true)
bool(true)
-0001-01-01T00:00:00+00:00
Fri, 01 Jan -0001 00:00:00 +0000
bool(true)
bool(true)
-12345-01-01T00:00:00+00:00
Fri, 01 Jan -12345 00:00:00 +0000
bool(true)
bool(true)
0005-01-01T00:00:00+00:00
Sat, 01 Jan 0005 00:00:00 +0000
bool(true)
bool(true)
bool(true)
