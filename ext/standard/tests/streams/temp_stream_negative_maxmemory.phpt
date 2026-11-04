--TEST--
php://temp with a negative maxmemory does not blame an unrelated argument
--FILE--
<?php
$url = 'php://temp/maxmemory:-1';

$calls = [
    'fopen' => fn() => fopen($url, 'w+'),
    'file_get_contents' => fn() => file_get_contents($url),
    'file_put_contents' => fn() => file_put_contents($url, 'data'),
    'file' => fn() => file($url),
    'SplFileObject' => fn() => new SplFileObject($url, 'w+'),
];

foreach ($calls as $name => $call) {
    try {
        $call();
    } catch (ValueError $e) {
        echo $name, ": ", $e->getMessage(), "\n";
    }
}

var_dump(fopen('php://temp/maxmemory:0', 'w+') !== false);
?>
--EXPECT--
fopen: Max memory of php://temp must be greater than or equal to 0
file_get_contents: Max memory of php://temp must be greater than or equal to 0
file_put_contents: Max memory of php://temp must be greater than or equal to 0
file: Max memory of php://temp must be greater than or equal to 0
SplFileObject: Max memory of php://temp must be greater than or equal to 0
bool(true)
