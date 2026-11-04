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
