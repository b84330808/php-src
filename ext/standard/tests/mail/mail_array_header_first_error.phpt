--TEST--
mail(): an invalid value in an array header stops at the first error
--INI--
sendmail_path={MAIL:mail_array_header_first_error.out}
mail.add_x_header = Off
--FILE--
<?php
function test(array $headers) {
    try {
        var_dump(mail('user@example.com', 'Test Subject', 'A Message', $headers));
    } catch (TypeError|ValueError $e) {
        echo get_class($e), ": ", $e->getMessage(), "\n";
        $previous = $e->getPrevious();
        echo "previous: ", $previous ? get_class($previous) . ": " . $previous->getMessage() : "none", "\n";
    }
}

test(['X-Foo' => ["bar\r\nbaz", "qux\r\nquux"]]);
test(['X-Foo' => ["bar\r\nbaz", 123]]);
test(['X-Foo:' => ['bar', 'baz']]);
test(['X-Foo' => ['bar', "baz\0", 'qux']]);
?>
--CLEAN--
<?php
@unlink('mail_array_header_first_error.out');
?>
--EXPECT--
ValueError: Header "X-Foo" contains CRLF characters that are used as a line separator and are not allowed in the header
previous: none
ValueError: Header "X-Foo" contains CRLF characters that are used as a line separator and are not allowed in the header
previous: none
ValueError: Header name "X-Foo:" contains invalid characters
previous: none
ValueError: Header "X-Foo" contains NULL character that is not allowed in the header
previous: none
