# php-src contribution report

- Upstream repo: https://github.com/php/php-src (default branch `master`)
- Fork: https://github.com/b84330808/php-src
- Base branch for all bug fixes: **`PHP-8.4`** (see "Policy notes" for why)
- Build used for evidence: `PHP-8.4` @ `7f8c655ecca` (8.4.29-dev), NTS debug build
  (`./configure --disable-all --enable-debug --disable-cgi --disable-phpdbg
  --enable-ctype --enable-tokenizer --enable-filter --enable-calendar`)
- Nothing was posted to upstream (no PRs, comments, issues, reactions or pushes).

## Fix branches

| # | Branch (on fork) | Commit SHA | Base | Component |
|---|------------------|------------|------|-----------|
| 1 | `fix/date-format-trailing-backslash` | `6fb6db123c48e2fd194f211306573ce4f1a70774` | `upstream/PHP-8.4` | ext/date |
| 2 | `fix/mail-array-header-first-error` | `ec816cdf8abcf27fcbd377934719558d5c578658` | `upstream/PHP-8.4` | ext/standard (mail) |
| 3 | `fix/php-temp-maxmemory-error` | `3311cbf2bc09fa1b774ea4eaa7450f3a6e5fb900` | `upstream/PHP-8.4` | ext/standard (php:// wrapper) |
| 4 | `fix/unpack-hex-star-position` | `413bd72d0fb3f994f0b948529c5672ab5c060a30` | `upstream/PHP-8.4` | ext/standard (pack) |
| 5 | `fix/implode-frameless-array-null` | `3c89753b49828c4a3c17fba45beb60655a5f0785` | `upstream/PHP-8.4` | ext/standard (string, frameless call) |

Private finding, see local follow-up: ext/filter

Private finding (unverified leads, 2), see local follow-up: ext/standard

---

## Fix 1: date() emits a NUL byte for a trailing backslash in the format

- Branch: `fix/date-format-trailing-backslash`
- SHA: `6fb6db123c48e2fd194f211306573ce4f1a70774`
- Author: `b84330808 <b84330808@gmail.com>`; no trailers
- Files: `ext/date/php_date.c` (1 line), `ext/date/tests/date_format_trailing_backslash.phpt` (new)
- Found by: reading/testing code (no existing issue)

### PR title

    Fix date() emitting a NUL byte for a trailing backslash in the format

### PR body draft

    date_format() in ext/date/php_date.c handles a backslash by skipping to
    the next format character:

        case '\\': if (i < format_len) i++; ZEND_FALLTHROUGH;

    Inside the `for (i = 0; i < format_len; i++)` loop that check is always
    true, so when the backslash is the last character `i` moves past the end
    and `format[format_len]` (the string terminator) is appended to the output:

        var_dump(bin2hex(date('Y\\', 0)));    // "3139373000" -> "1970\0"
        var_dump(bin2hex(gmdate('\\', 0)));   // "00"

    The fix only skips ahead when another character follows, which is what the
    guard was meant to do, so a lone trailing backslash is now output as-is
    ("1970\"). This affects date(), gmdate(), date_format() and
    DateTimeInterface::format(). Escaped characters elsewhere in the format
    are unchanged.

    Targeting PHP-8.4 as the lowest branch receiving bug fixes.

### Test commands

    cd php-src            # checkout of fix/date-format-trailing-backslash
    ./buildconf --force && ./configure --disable-all --enable-debug --disable-cgi --disable-phpdbg && make -j4
    TEST_PHP_EXECUTABLE=sapi/cli/php sapi/cli/php run-tests.php -q --show-diff ext/date/tests/date_format_trailing_backslash.phpt
    TEST_PHP_EXECUTABLE=sapi/cli/php sapi/cli/php run-tests.php -q -j4 ext/date

Fail-before (test added, `php_date.c` change reverted):

    TEST 1/1 [ext/date/tests/date_format_trailing_backslash.phpt]
    ========DIFF========
    001- string(10) "313937305c"
    001+ string(10) "3139373000"
    002+ string(2) "00"
    003+ string(22) "323032342d30322d303300"
         string(2) "5c"
    003- string(22) "323032342d30322d30335c"
    004- string(2) "5c"
    005- string(2) "Y\"
    005+ string(2) "Y "          <- the "space" is a raw NUL byte
         string(6) "\2024\"
    ========DONE========
    FAIL date() and DateTime::format() with a trailing backslash in the format [ext/date/tests/date_format_trailing_backslash.phpt]

Pass-after:

    TEST 1/1 [ext/date/tests/date_format_trailing_backslash.phpt] PASS date() and DateTime::format() with a trailing backslash in the format

Whole ext/date suite after the fix:

    Number of tests :   705               693
    Tests skipped   :    12 (  1.7%) --------
    Tests warned    :     0 (  0.0%) (  0.0%)
    Tests failed    :     0 (  0.0%) (  0.0%)
    Tests passed    :   693 ( 98.3%) (100.0%)

### Same-pattern sweep

Grepped `ext/date`, `ext/standard`, `ext/json` (and `main`, `Zend`) for
`if (i < *len) i++`-style escape handling and every `case '\\':` branch.
`date_format()` is the only instance; `stripcslashes()`, `addcslashes()`,
browscap pattern conversion, the JSON scanner/encoder and
`DateInterval::format()` (uses `%`, not `\`) all bound-check correctly.

### Competing PR / issue search (php/php-src, all states, issues and PRs)

Queries via `gh api search/issues` with `repo:php/php-src is:issue` and
`is:pr`, no state filter:

- `date format trailing backslash`: 0 issues, 0 PRs
- `date backslash null byte`: 0 / 0
- `date() backslash end of format`: 0 / 0
- `date_format backslash`: 0 / 0
- `date format ends with backslash`: 0 / 0
- `DateTime::format backslash`: 0 / 0
- `date_format escape`: 1 unrelated issue (php/php-src#15070), 0 PRs
- `trailing backslash date`: 0 issues, 1 unrelated PR (php/php-src#7309)
- `date "\\" NUL`: only unrelated NUL-byte work, e.g. php/php-src#25153
  (strtotime() embedded NUL), php/php-src#22997 (date error messages with
  NUL, closed), php/php-src#24357 (ISO 8601 duration NUL); none touch the
  format escape.

### Security assessment

Not a vulnerability: the extra byte read is the zend_string's own NUL
terminator (in bounds), and the format string is normally
developer-controlled. Fine to fix in public.

### NEWS

Not edited (no GH issue number; maintainers usually add NEWS on merge). If
asked, a suggested entry under `- Date:` would be:
`. Fixed date() emitting a NUL byte for a trailing backslash in the format. (<name>)`

---

## Fix 2: mail() array header validation continues after an error

- Branch: `fix/mail-array-header-first-error`
- SHA: `ec816cdf8abcf27fcbd377934719558d5c578658`
- Author: `b84330808 <b84330808@gmail.com>`; no trailers
- Files: `ext/standard/mail.c` (+3 lines), `ext/standard/tests/mail/mail_array_header_first_error.phpt` (new)
- Found by: reading/testing code (no existing issue)

### PR title

    Fix mail() array header validation continuing after an error

### PR body draft

    php_mail_build_headers_elems() validates each value of an array-valued
    additional header by calling php_mail_build_headers_elem(), but never
    checks for an exception afterwards. Once one value is invalid, the rest
    are still validated, and each further error throws another exception
    chained on top of the first. The exception the caller sees then
    describes the *last* invalid value, not the first:

        mail('a@example.com', 's', 'm', ['X-Foo' => ["a\r\nb", 123]]);
        // TypeError: Header "X-Foo" must only contain values of type string, int found
        //   previous: ValueError: Header "X-Foo" contains CRLF characters ...

        mail('a@example.com', 's', 'm', ['X-Foo:' => ['a', 'b']]);
        // ValueError: Header name "X-Foo:" contains invalid characters
        //   previous: ValueError: Header name "X-Foo:" contains invalid characters

    This patch stops at the first error, as the top-level loop in
    php_mail_build_headers() and the other two checks in the same loop
    already do. mb_send_mail() uses the same helper and gets the same fix.

    Targeting PHP-8.4 as the lowest branch receiving bug fixes. master has
    the same code (only %pS formatting changes), so it merges up cleanly in
    intent.

### Test commands

    cd php-src            # checkout of fix/mail-array-header-first-error
    ./buildconf --force && ./configure --disable-all --enable-debug --disable-cgi --disable-phpdbg && make -j4
    TEST_PHP_EXECUTABLE=sapi/cli/php sapi/cli/php run-tests.php -q --show-diff ext/standard/tests/mail/mail_array_header_first_error.phpt
    TEST_PHP_EXECUTABLE=sapi/cli/php sapi/cli/php run-tests.php -q -j4 ext/standard/tests/mail

Fail-before (test added, `mail.c` change reverted):

    TEST 1/1 [ext/standard/tests/mail/mail_array_header_first_error.phpt]
    ========DIFF========
         ValueError: Header "X-Foo" contains CRLF characters that are used as a line separator and are not allowed in the header
    002- previous: none
    003- ValueError: Header "X-Foo" contains CRLF characters that are used as a line separator and are not allowed in the header
    004- previous: none
    002+ previous: ValueError: Header "X-Foo" contains CRLF characters that are used as a line separator and are not allowed in the header
    003+ TypeError: Header "X-Foo" must only contain values of type string, int found
    004+ previous: ValueError: Header "X-Foo" contains CRLF characters that are used as a line separator and are not allowed in the header
         ValueError: Header name "X-Foo:" contains invalid characters
    006- previous: none
    006+ previous: ValueError: Header name "X-Foo:" contains invalid characters
         ValueError: Header "X-Foo" contains NULL character that is not allowed in the header
         previous: none
    ========DONE========
    FAIL mail(): an invalid value in an array header stops at the first error [ext/standard/tests/mail/mail_array_header_first_error.phpt]

Pass-after:

    TEST 1/1 [ext/standard/tests/mail/mail_array_header_first_error.phpt] PASS mail(): an invalid value in an array header stops at the first error

Whole `ext/standard/tests/mail` directory after the fix:

    Number of tests :    28                21
    Tests skipped   :     7 ( 25.0%) --------    (Windows-only / needs SMTP)
    Tests failed    :     0 (  0.0%) (  0.0%)
    Tests passed    :    21 ( 75.0%) (100.0%)

### Same-pattern sweep

The other loops in `mail.c` already stop on error: the top-level loop in
`php_mail_build_headers()` checks `EG(exception)` after every header, and the
two in-loop checks in `php_mail_build_headers_elems()` `break`. The
`IS_ARRAY` branch of `php_mail_build_headers_elem()` can't be reached from
`_elems()` because non-string values are rejected first. The only external
caller is `mb_send_mail()` (`ext/mbstring/mbstring.c`), and this fix covers it.

### Competing PR / issue search (php/php-src, all states, issues and PRs)

- `php_mail_build_headers`: 0 issues, 0 PRs
- `mail additional_headers array exception`: 0 issues, 2 unrelated PRs (php/php-src#4851 stubs, php/php-src#1273 bug #68776)
- `mail headers chained exception`: 0 / 0
- `mail header array multiple exceptions`: only unrelated hits
- `mail() header empty name` / `mail additional_headers empty header name`: only unrelated hits

### Security assessment

Not a vulnerability. Only the error reporting changes (which exception
reaches the caller); no mail was sent before or after the fix, because the
function fails either way.

### Related, not fixed here

An empty header name (`['' => 'x']`) passes the field-name check (the loop
over zero characters succeeds) and then fails later with a misleading
`Warning: mail(): Multiple or malformed newlines found in additional_header`
and `false`, instead of the `ValueError: Header name "" contains invalid
characters` that other bad names get. It's a separate change, possibly for
master since it turns a warning into an exception. Not on any branch yet.

Full `ext/standard` run on the fix-2 build: 3189 run, 3181 passed, 7 failed,
1 warned. All 7 failures (`http/gh11274`, `http/gh16810`,
`http/ghsa-c5f2-jwm7-mmq2`, `streams/bug46024`, `streams/bug64433`,
`streams/gh10031`, `url/get_headers_error_003`) fail identically on a build
without the mail.c change. They need outbound HTTP, a local CLI server or
php-cgi, none of which the sandbox has.

---

## Fix 3: php://temp negative maxmemory error blames an unrelated argument

- Branch: `fix/php-temp-maxmemory-error`
- SHA: `3311cbf2bc09fa1b774ea4eaa7450f3a6e5fb900`
- Author: `b84330808 <b84330808@gmail.com>`; no trailers
- Files: `ext/standard/php_fopen_wrapper.c` (1 line), `ext/standard/tests/streams/temp_stream_negative_maxmemory.phpt` (new)
- Found by: reading/testing code (no existing issue)

### PR title

    Fix php://temp negative maxmemory error blaming an unrelated argument

### PR body draft

    When php://temp/maxmemory:<n> has a negative value, the php:// stream
    wrapper calls zend_argument_value_error(2, ...). The wrapper runs inside
    whichever function opened the URL, so the error names that function's
    second parameter, which has nothing to do with the URL:

        fopen('php://temp/maxmemory:-1', 'w+');
        // ValueError: fopen(): Argument #2 ($mode) must be greater than or equal to 0
        file_get_contents('php://temp/maxmemory:-1');
        // ValueError: file_get_contents(): Argument #2 ($use_include_path) must be ...
        file_put_contents('php://temp/maxmemory:-1', 'x');
        // ValueError: file_put_contents(): Argument #2 ($data) must be ...
        file('php://temp/maxmemory:-1');
        // ValueError: file(): Argument #2 ($flags) must be ...
        include 'php://temp/maxmemory:-1';
        // ValueError: {main}(): Argument #2 must be ...

    PHP 7.4 threw Error("Max memory must be >= 0") here. The PHP 8.0 conversion
    to the argument-error API picked argument 2, which is only right for
    callers that happen to have the URL as their second parameter.

    This keeps the ValueError but uses a message that describes the actual
    problem: "Max memory of php://temp must be greater than or equal to 0".
    No existing test covered this path.

    Targeting PHP-8.4 as the lowest branch receiving bug fixes. PHP-8.5,
    PHP-8.6 and master have the same line.

### Test commands

    cd php-src            # checkout of fix/php-temp-maxmemory-error
    ./buildconf --force && ./configure --disable-all --enable-debug --disable-cgi --disable-phpdbg && make -j4
    TEST_PHP_EXECUTABLE=sapi/cli/php sapi/cli/php run-tests.php -q --show-diff ext/standard/tests/streams/temp_stream_negative_maxmemory.phpt
    TEST_PHP_EXECUTABLE=sapi/cli/php sapi/cli/php run-tests.php -q -j4 ext/standard/tests/streams ext/standard/tests/file ext/spl/tests

Fail-before (test added, `php_fopen_wrapper.c` change reverted):

    TEST 1/1 [ext/standard/tests/streams/temp_stream_negative_maxmemory.phpt]
    ========DIFF========
    001- fopen: Max memory of php://temp must be greater than or equal to 0
    002- file_get_contents: Max memory of php://temp must be greater than or equal to 0
    003- file_put_contents: Max memory of php://temp must be greater than or equal to 0
    004- file: Max memory of php://temp must be greater than or equal to 0
    005- SplFileObject: Max memory of php://temp must be greater than or equal to 0
    001+ fopen: fopen(): Argument #2 ($mode) must be greater than or equal to 0
    002+ file_get_contents: file_get_contents(): Argument #2 ($use_include_path) must be greater than or equal to 0
    003+ file_put_contents: file_put_contents(): Argument #2 ($data) must be greater than or equal to 0
    004+ file: file(): Argument #2 ($flags) must be greater than or equal to 0
    005+ SplFileObject: SplFileObject::__construct(): Argument #2 ($mode) must be greater than or equal to 0
         bool(true)
    ========DONE========
    FAIL php://temp with a negative maxmemory does not blame an unrelated argument [ext/standard/tests/streams/temp_stream_negative_maxmemory.phpt]

Pass-after:

    TEST 1/1 [ext/standard/tests/streams/temp_stream_negative_maxmemory.phpt] PASS php://temp with a negative maxmemory does not blame an unrelated argument

`ext/standard/tests/streams` + `ext/standard/tests/file` + `ext/spl/tests` after the fix:

    Number of tests :  1847              1496
    Tests skipped   :   351 ( 19.0%) --------
    Tests failed    :     3 (  0.2%) (  0.2%)    (streams/bug46024, streams/bug64433, streams/gh10031)
    Expected fail   :     1 (  0.1%) (  0.1%)
    Tests passed    :  1492 ( 80.8%) ( 99.7%)

Those 3 failures are environmental: they also fail on the unpatched build
(see the fix-2 note).

### Same-pattern sweep

Grepped `main/streams/*.c`, `main/*.c`, `ext/standard/{php,ftp,http}_fopen_wrapper.c`,
`ext/standard/filters.c`, `ext/standard/user_filters.c`, `ext/zlib`, `ext/bz2`
and `ext/standard/streamsfuncs.c` for `zend_argument_*_error()` calls made
from wrapper or filter callbacks, where the "active function" is whatever
opened the stream. `php_fopen_wrapper.c:200` is the only one. Every other
hit is inside a real `PHP_FUNCTION`, where the argument number is correct.

### Competing PR / issue search (php/php-src, all states, issues and PRs)

- `php://temp maxmemory`: 2 unrelated issues (php/php-src#20530 debug prefix idea, php/php-src#23905 seek position), 1 unrelated PR (php/php-src#23907 seek position)
- `maxmemory negative`: same unrelated php/php-src#20530 / php/php-src#23907
- `php://temp/maxmemory error message`: 0 / 0
- `php_fopen_wrapper argument`: 0 / 0

### Security assessment

Not a vulnerability: only an error message changes. The negative value is
still rejected with a ValueError, as before.

---

## Fix 4: unpack() "h*" / "H*" does not advance the input position

- Branch: `fix/unpack-hex-star-position`
- SHA: `413bd72d0fb3f994f0b948529c5672ab5c060a30`
- Author: `b84330808 <b84330808@gmail.com>`; no trailers
- Files: `ext/standard/pack.c` (+3 lines), `ext/standard/tests/strings/unpack_hex_star_position.phpt` (new)
- Found by: code review/testing (no existing issue)

### PR title

    Fix unpack() "h*" and "H*" not advancing the input position

### PR body draft

    For the '*' repeater, the h/H case in unpack() leaves `size` at -1. The
    a/A/Z cases set `size` to the number of bytes they consumed; the hex case
    never does, so the common `inputpos += size` moves the position one byte
    *backwards* instead of to the end of the input. Any format code after
    h* / H* therefore re-reads data that was already consumed:

        unpack("H*a/H*b", "\xab\xcd");
        // ["a" => "abcd", "b" => "abcd"]   expected "b" => ""  (like "a*a/a*b")

        unpack("H*a/Cb", "\xab\xcd");
        // ["a" => "abcd", "b" => 171]       expected the usual
        // "Type C: not enough input values" warning and false (like "a*a/Cb")

        unpack("Ca/H*b/X/Cc", "\x01\xab\xcd");
        // "c" => 1 (re-reads byte 0)        expected 205 (last byte)

    The fix sets `size` to the number of input bytes the nibbles came from,
    `(len + 1) / 2`. That equals the value already computed for explicit
    counts, so only the '*' case changes. I checked H/h with counts 0 to 7 on
    a 4-byte input followed by `C`; results are unchanged.

    Targeting PHP-8.4 as the lowest branch receiving bug fixes. master has
    the same code.

### Test commands

    cd php-src            # checkout of fix/unpack-hex-star-position
    ./buildconf --force && ./configure --disable-all --enable-debug --disable-cgi --disable-phpdbg && make -j4
    TEST_PHP_EXECUTABLE=sapi/cli/php sapi/cli/php run-tests.php -q --show-diff ext/standard/tests/strings/unpack_hex_star_position.phpt
    TEST_PHP_EXECUTABLE=sapi/cli/php sapi/cli/php run-tests.php -q -j4 ext/standard/tests/strings

Fail-before (test added, `pack.c` change reverted):

    TEST 1/1 [ext/standard/tests/strings/unpack_hex_star_position.phpt]
    ========DIFF========
    --
           ["b"]=>
           string(4) "abcd"
           ["c"]=>
    007-   int(205)
    007+   int(1)
         }
         array(3) {
           ["a"]=>
    --
           ["b"]=>
           string(4) "badc"
           ["c"]=>
    015-   int(205)
    015+   int(1)
         }
         array(2) {
           ["a"]=>
           string(4) "abcd"
           ["b"]=>
    021-   string(0) ""
    021+   string(4) "abcd"
         }
    023-
    024- Warning: unpack(): Type C: not enough input values, need 1 values but only 0 were provided in %s on line %d
    025- bool(false)
    023+ array(2) {
    024+   ["a"]=>
    025+   string(4) "abcd"
    026+   ["b"]=>
    027+   int(171)
    028+ }
         array(2) {
           ["a"]=>
           string(3) "abc"
    --
    ========DONE========
    FAIL unpack() with "h*" and "H*" advances the position to the end of the input [ext/standard/tests/strings/unpack_hex_star_position.phpt]

Pass-after:

    TEST 1/1 [ext/standard/tests/strings/unpack_hex_star_position.phpt] PASS unpack() with "h*" and "H*" advances the position to the end of the input

Whole `ext/standard/tests/strings` directory (includes all pack/unpack tests) after the fix:

    Number of tests :   749               679
    Tests skipped   :    70 (  9.3%) --------
    Tests failed    :     0 (  0.0%) (  0.0%)
    Tests passed    :   679 ( 90.7%) (100.0%)

### Same-pattern sweep

In `unpack()`, every other code with variable consumption (`a`, `A`, `Z`)
assigns `size = len` inside its case. Fixed-width codes have constant sizes.
`X`/`@` use `size` deliberately (-1/0) and handle the position themselves.
`h`/`H` was the only variable-length case that didn't update `size`.

### Competing PR / issue search (php/php-src, all states, issues and PRs)

- `unpack hex`: 0 relevant issues; 1 PR, php/php-src#15615 (GH-15613, repeater
  overflow check, closed), which is a different problem
- `unpack hex string star`, `unpack hex position`, `unpack "H*" next format`,
  `unpack H format following`, `unpack hexadecimal`: no relevant hits
- `unpack H*`: only unrelated hits (spread/array-unpacking issues)
- bugs.php.net could not be searched from the sandbox (php.net blocked by
  the proxy); worth a quick look before opening the PR.

### Security assessment

Not a vulnerability. Before the fix the position only moved backwards and
was clamped to 0, so it never read outside the input. After the fix it
moves to exactly `inputlen`.

---

## Fix 5: implode($array, null) throws on the frameless call path

- Branch: `fix/implode-frameless-array-null`
- SHA: `3c89753b49828c4a3c17fba45beb60655a5f0785`
- Author: `b84330808 <b84330808@gmail.com>`; no trailers
- Files: `ext/standard/string.c` (+11 lines in `ZEND_FRAMELESS_FUNCTION(implode, 2)`), `ext/standard/tests/strings/implode_array_null_frameless.phpt` (new)
- Found by: code review/testing (no existing issue). This is PHP 8.4-only:
  frameless calls were added in 8.4.

### PR title

    Fix implode($array, null) throwing on the frameless call path

### PR body draft

    PHP_FUNCTION(implode) accepts an array as the first argument when the
    second argument is null; it's the same as implode($array), and the stub
    allows it (string|array $separator, ?array $array = null). The frameless
    variant that direct two-argument calls compile to parses argument 1 as a
    string unconditionally. So whether the call works depends on how it is
    made:

        $a = ['a', 'b'];
        implode($a, null);                    // TypeError: Argument #1 ($separator) must be of type string, array given
        call_user_func('implode', $a, null);  // "ab"
        join($a, null);                       // "ab" (the alias isn't frameless)

    For implode($array, 'x') it also blames argument 1, while the regular
    function says "Argument #2 ($array) must be of type ?array, string given".

    The fix handles an array first argument the way the regular function
    does. I ran a differential test that calls every frameless function in
    ext/standard, Zend and ext/pcre directly and via call_user_func() over
    10 argument kinds (8360 combinations). After this patch the only
    remaining implode difference is that implode(null) on the 1-arg frameless
    path doesn't emit the "Passing null to parameter #1" deprecation before
    the same TypeError. The in_array() differences only appear with all-literal
    arguments, which are evaluated at compile time.

    Targeting PHP-8.4 (frameless functions were introduced in 8.4). master
    has the same code.

### Test commands

    cd php-src            # checkout of fix/implode-frameless-array-null
    ./buildconf --force && ./configure --disable-all --enable-debug --disable-cgi --disable-phpdbg && make -j4
    TEST_PHP_EXECUTABLE=sapi/cli/php sapi/cli/php run-tests.php -q --show-diff ext/standard/tests/strings/implode_array_null_frameless.phpt
    TEST_PHP_EXECUTABLE=sapi/cli/php sapi/cli/php run-tests.php -q -j4 ext/standard/tests/strings Zend/tests/frameless* ext/opcache/tests

Fail-before (test added, `string.c` change reverted):

    TEST 1/1 [ext/standard/tests/strings/implode_array_null_frameless.phpt]
    ========DIFF========
    001- array, null (direct): 'ab'
    001+ array, null (direct): implode(): Argument #1 ($separator) must be of type string, array given
         array, null (dynamic): 'ab'
    003- array, null literal (direct): 'ab'
    003+ array, null literal (direct): implode(): Argument #1 ($separator) must be of type string, array given
         array, null literal (dynamic): 'ab'
    005- empty array, null (direct): ''
    005+ empty array, null (direct): implode(): Argument #1 ($separator) must be of type string, array given
         empty array, null (dynamic): ''
    007- array, string (direct): implode(): Argument #2 ($array) must be of type ?array, string given
    007+ array, string (direct): implode(): Argument #1 ($separator) must be of type string, array given
         array, string (dynamic): implode(): Argument #2 ($array) must be of type ?array, string given
         array, array (direct): implode(): Argument #1 ($separator) must be of type string, array given
         array, array (dynamic): implode(): Argument #1 ($separator) must be of type string, array given
    --
    ========DONE========
    FAIL implode() with an array first argument behaves the same in direct and dynamic calls [ext/standard/tests/strings/implode_array_null_frameless.phpt]

Pass-after:

    TEST 1/1 [ext/standard/tests/strings/implode_array_null_frameless.phpt] PASS implode() with an array first argument behaves the same in direct and dynamic calls

`ext/standard/tests/strings` + `Zend/tests/frameless*` + `ext/opcache/tests` after the fix:

    Number of tests :  1707               988
    Tests skipped   :   719 ( 42.1%) --------   (opcache not built in this --disable-all config)
    Tests failed    :     0 (  0.0%) (  0.0%)
    Tests passed    :   988 ( 57.9%) (100.0%)

Worth re-running with opcache/JIT enabled before opening the PR. The JIT
calls the same frameless handler, but this sandbox build didn't include
opcache.

### Same-pattern sweep

Differential test (direct frameless call vs `call_user_func`) over every
frameless function in 8.4: `implode/1,2`, `in_array/2,3`, `is_numeric`,
`dechex`, `dirname/1,2`, `str_contains`, `str_starts_with`, `strpos/2,3`,
`strstr/2,3`, `strtr/2,3`, `substr/2,3`, `trim/1,2`, `str_replace`,
`class_exists/1,2`, `property_exists`, `preg_match/2`, `preg_replace/3`.
Argument values were null, strings, arrays, int, float and bool. Only
`implode/2` differed in behavior. (A reviewer separately noted a `min()`
int/float tie-break difference on the frameless path, already covered by
php/php-src#23505 / issue #20221, so not touched.)

### Competing PR / issue search (php/php-src, all states, issues and PRs)

- `implode frameless`: php/php-src#21639 (open, volatile frameless args during
  `__toString` reentry; different), php/php-src#23204 (closed, UAF; different),
  PRs php/php-src#21815, php/php-src#23436, php/php-src#12461 (none touch the
  array-first case)
- `implode null TypeError` / `implode array null`: php/php-src#12682 / PR
  php/php-src#12683 (2023, error message for `implode('x', null)`; pre-frameless,
  different case), php/php-src#9667, php/php-src#10197 (named args), php/php-src#23207;
  none cover this
- `frameless implode`, `implode($array, null)`: nothing new

### Security assessment

Not a vulnerability: type-handling consistency only, no memory handling
changes (no new allocations, and the early-return path frees nothing).

---

## Policy notes (read from upstream `master`)

- **Base branch**: CONTRIBUTING.md says bug-fix PRs go against "the lowest
  actively supported branch of PHP that the bug affects", then get merged up.
  php.net/supported-versions.php was unreachable from the sandbox (proxy
  403), so this was inferred from branch activity: `PHP-8.2` and `PHP-8.3`
  only get CI/security commits, while bug fixes such as GH-25165 land on
  `PHP-8.4` and are merged into `PHP-8.5`, `PHP-8.6` and `master`. So
  **`PHP-8.4` is the bug-fix base**. Worth re-checking on php.net before
  opening PRs (8.4 active support is scheduled to end 31 Dec 2026).
- **PR template**: none (`.github/` has no pull_request_template; only issue
  templates).
- **CLA / DCO**: none. There is no sign-off requirement; CONTRIBUTING.md says
  that by submitting a PR you certify you have the rights to the work and
  license it under the Modified BSD License.
- **AI policy**: AI-assisted PRs are **not banned**. `AGENTS.md` asks agents to
  (1) check existing PRs, including closed ones, before starting and again
  before opening a PR, and not open duplicates, and (2) follow SECURITY.md
  and the security-classification policy when scanning for vulnerabilities.
  CONTRIBUTING.md "LLM usage in GitHub comments": when LLMs generate comments
  to maintainers (other than for translation), they "would highly appreciate"
  the relevant paragraphs being disclosed as such via markdown quote. This
  is a request, not a requirement, and it is about comments. Since a PR body
  is text written to maintainers, either write it in your own words or put
  LLM-written paragraphs in a `>` quote. The drafts here have no disclosure
  line.
- **Commit rules**: summary line ≤ 79 chars, body wrapped at 79, mention the
  function name (prefix `PHP` for userland functions in the old style), and
  mention `GH-<n>` when fixing a filed bug. New tests must not have a
  `--CREDITS--` section, and ZPP failure modes should not be tested.
- **Security**: report vulnerabilities privately via GitHub Security
  Advisories (or security@php.net). Malicious code (error handlers,
  destructors, `__toString`), malicious `unserialize()` input, FFI,
  open_basedir bypasses and similar are classified as bugs, not security
  issues.
- **Duplicates**: "first-come, first-served" when equivalent fixes exist.
- **Text aimed at AI agents**: `AGENTS.md` (above) is the only such text found.
  It was treated as project policy and is consistent with the task. No other
  repo, issue or PR text directed at agents was seen.

## Open issues triaged and skipped (already have PRs or are design/timelib)

Every open `Bug` issue labelled `Extension: standard|date|json|calendar|ctype|filter|bcmath|random|SPL|iconv|mbstring|uri|hash|tokenizer`
(74 issues) was checked for linked PRs via `repo:php/php-src is:pr <number>`.
Examples with existing PRs: GH-18051 (php/php-src#18054), GH-22505
(php/php-src#22507, php/php-src#23702), GH-20189 (php/php-src#20191), GH-22993
(php/php-src#22997, closed), GH-23876 (php/php-src#24560), GH-18120
(php/php-src#19346), GH-10229 (php/php-src#10235). The ones without PRs are
mostly timelib-internal date math (fixes belong upstream in derickr/timelib),
design questions, or memory-safety reports (deliberately avoided).
`number_format(-INF)` returning `"inf"` was also found, but open PR
php/php-src#10298 already covers it, so it was skipped.
