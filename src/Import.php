<?php
declare(strict_types=1);

namespace GridKit;

/**
 * Import — a CSV file into rows a page can store, with the columns matched to fields (1.119.0).
 *
 *   $import = (new Import('customers'))
 *       ->field('email', 'E-mail', ['type' => 'email', 'required' => true, 'unique' => true, 'aliases' => ['E-Mail', 'Mail']])
 *       ->field('name', 'Name', ['required' => true, 'aliases' => ['Nachname']])
 *       ->field('revenue', 'Revenue', ['type' => 'money'])
 *       ->handle();                                  // reads $_POST / $_FILES of this importer
 *   if ($import->step() === 'done') save($import->result()['rows']);
 *   $import->render();
 *
 * Why it exists: the CMS, the newsletter and the invoicing among GridKit's users all take lists in —
 * recipients, customers, articles — and the list arrives as whatever Excel wrote: semicolons, a
 * decimal comma, Windows-1252 umlauts, a date as 31.12.2026. Each of them grew an upload field and a
 * fgetcsv() loop that knew one of those shapes. This is the one place for it.
 *
 * The flow is a form in three steps that works without any script: the file (upload or pasted
 * text) → the columns matched to fields, a preview with every error in words → import the valid
 * rows or cancel. Between the steps the file is kept on the server under an unguessable token (or,
 * with storage(false), travels in the form); either way every step reads the file again and checks
 * every row again — no row the browser sends is ever taken as it is. js/import.js (a file of its
 * own, like js/tree.js) reads the file in the browser instead: the preview is there the moment a
 * file is chosen or dropped, and follows every change of the mapping; the form then posts the file
 * and the mapping once, and the server reads and checks it all again.
 *
 * Every misuse the class can see (an unknown option or type, a field defined twice, a limit out of
 * range) is an E_USER_WARNING with a fallback; nothing here throws.
 */
class Import
{
    /** The field types and what their cells may hold. */
    public const TYPES = ['text', 'email', 'number', 'money', 'date', 'bool', 'select'];

    private const FIELD_OPTS = ['type', 'required', 'unique', 'aliases', 'options'];

    /** The separators GridKit tries, in the order a tie goes to: German Excel writes the first. */
    private const DELIMITERS = [';' => 'semicolon', ',' => 'comma', "\t" => 'tab', '|' => 'pipe'];

    /** The encodings a person may choose; 'ISO-8859-1' is read as its superset Windows-1252 (as browsers do). */
    private const ENCODINGS = ['UTF-8', 'Windows-1252', 'UTF-16LE', 'UTF-16BE'];

    private const MAX_COLUMNS = 200;
    private const HARD_ROWS = 100000;
    private const HARD_BYTES = 20971520;
    /** How many records the separator is guessed from. */
    private const SAMPLE_RECORDS = 50;

    /** Yes and no as a sheet writes them (case does not count). An empty cell is no. */
    private const TRUE_WORDS = ['1', 'x', 'ja', 'j', 'yes', 'y', 'true', 'wahr', 'on'];
    private const FALSE_WORDS = ['0', 'nein', 'n', 'no', 'false', 'falsch', 'off'];

    /** Windows-1252 bytes 0x80–0x9F (WHATWG Encoding Standard); the rest of the upper half is Latin-1. */
    private const CP1252 = [
        0x80 => 0x20AC, 0x81 => 0x0081, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E, 0x85 => 0x2026,
        0x86 => 0x2020, 0x87 => 0x2021, 0x88 => 0x02C6, 0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039,
        0x8C => 0x0152, 0x8D => 0x008D, 0x8E => 0x017D, 0x8F => 0x008F, 0x90 => 0x0090, 0x91 => 0x2018,
        0x92 => 0x2019, 0x93 => 0x201C, 0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014,
        0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A, 0x9C => 0x0153, 0x9D => 0x009D,
        0x9E => 0x017E, 0x9F => 0x0178,
    ];

    /**
     * How a column name and a field name are compared: lower case, umlauts and accents folded, every
     * sign that is not a letter or a digit dropped ("E-Mail" = "email", "Straße" = "Strasse"). ä is
     * folded both ways — "ae" and "a" — so "Müller" finds "Mueller" and "Muller". js/import.js has
     * the same table.
     */
    private const FOLD = [
        'ß' => 'ss', 'ẞ' => 'ss', 'æ' => 'ae', 'Æ' => 'ae', 'œ' => 'oe', 'Œ' => 'oe', 'þ' => 'th', 'Þ' => 'th',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Å' => 'a',
        'ç' => 'c', 'Ç' => 'c', 'ð' => 'd', 'Ð' => 'd', 'ñ' => 'n', 'Ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'y', 'Ÿ' => 'y',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ø' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u',
    ];
    private const UMLAUT_AE = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue'];
    private const UMLAUT_A = ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'Ä' => 'a', 'Ö' => 'o', 'Ü' => 'u'];

    private string $name;
    /**
     * @var array<string, array{key:string,label:string,type:string,required:bool,unique:bool,aliases:list<string>,options:list<array{0:string,1:string}>}>
     */
    private array $fields = [];
    private string $label = '';
    private ?string $locale = null;
    private int $maxRows = 10000;
    private int $maxBytes = 5242880;
    private int $previewRows = 20;
    private string $action = '';
    /** @var array<string,string> */
    private array $hidden = [];
    /** '' = the default temporary directory, false = nothing on the server: the file travels in the form. */
    private string|false $storageDir = '';
    private int $ttl = 3600;
    private string $delimiter = 'auto';
    private string $enclosure = 'auto';
    private string $encoding = 'auto';
    private ?bool $header = null;
    /** @var array<int|string, ?string>|null what map() was given */
    private ?array $defaultMap = null;

    // ── State of one request ──
    private string $step = 'upload';
    private ?string $source = null;
    private string $filename = '';
    private ?string $token = null;
    private ?string $refusal = null;
    private ?string $notice = null;
    /** Said in the panel, above the mapping: a choice that could not be applied. */
    private ?string $warning = null;
    /** Text pasted and refused, shown again in its box. */
    private string $text = '';
    /** @var array<int,string>|null the mapping a person posted: column => field key ('' = ignore) */
    private ?array $postedMap = null;
    private ?string $postedSig = null;
    /** Tests replace this; it is is_uploaded_file() everywhere else. */
    private ?\Closure $uploadCheck = null;
    /** @var array<string,mixed>|null */
    private ?array $parsedCache = null;
    private string $parsedKey = '';
    /** @var array<string,mixed>|null */
    private ?array $resultCache = null;

    public function __construct(string $name)
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $name) !== 1) {
            $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $name) ?? '';
            $safe = preg_match('/^[A-Za-z]/', $safe) === 1 ? substr($safe, 0, 64) : 'import' . substr(md5($name), 0, 8);
            trigger_error("GridKit: import '$name': the name is the field name the form posts under — letters, digits, _ and -, starting with a letter. It is '$safe' now.", E_USER_WARNING);
            $name = $safe;
        }
        $this->name = $name;
    }

    // ── Defining what is imported ───────────────────────────────────────────

    /**
     * A field a column can fill. Options: 'type' (text, email, number, money, date, bool, select),
     * 'required', 'unique' (no two rows of the file may hold the same value), 'aliases' (other column
     * names that mean this field: ['E-Mail', 'Mail']), 'options' (select: value => label, or a list).
     *
     * @param array<string,mixed> $opts
     */
    public function field(string $key, string $label, array $opts = []): static
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,63}$/', $key) !== 1) {
            $this->warn("field('$key'): a key is letters, digits, _ . and -, starting with a letter or _. The field was left out.");
            return $this;
        }
        if (isset($this->fields[$key])) {
            $this->warn("field('$key') is defined twice — the second was left out.");
            return $this;
        }
        foreach (array_keys($opts) as $k) {
            if (!in_array($k, self::FIELD_OPTS, true)) {
                $this->warn("field('$key') does not know the option '$k' (it knows " . implode(', ', self::FIELD_OPTS) . '). It was ignored.');
            }
        }
        $type = $opts['type'] ?? 'text';
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            $this->warn("field('$key') has the type " . self::shown($type) . ' — it is one of ' . implode(', ', self::TYPES) . ". It is 'text' now.");
            $type = 'text';
        }
        $f = ['key' => $key, 'label' => $label !== '' ? $label : $key, 'type' => $type,
              'required' => false, 'unique' => false, 'aliases' => [], 'options' => []];
        foreach (['required', 'unique'] as $flag) {
            if (!array_key_exists($flag, $opts) || $opts[$flag] === null) continue;
            $value = self::flag($opts[$flag]);
            if ($value === null) {
                $this->warn("field('$key') has '$flag' => " . self::shown($opts[$flag]) . ' — it takes true or false. It is false now.');
            } else {
                $f[$flag] = $value;
            }
        }
        $aliases = $opts['aliases'] ?? [];
        if (is_string($aliases)) $aliases = [$aliases];
        if (!is_array($aliases)) {
            $this->warn("field('$key') has 'aliases' => " . self::shown($aliases) . ' — it takes a list of column names. It has none now.');
            $aliases = [];
        }
        foreach ($aliases as $alias) {
            if (is_string($alias) && $alias !== '') $f['aliases'][] = $alias;
            elseif (is_int($alias)) $f['aliases'][] = (string) $alias;
        }
        if (array_key_exists('options', $opts) && $type !== 'select') {
            $this->warn("field('$key') has 'options', which only a 'select' reads. They were ignored.");
        }
        if ($type === 'select') {
            $options = $opts['options'] ?? null;
            if (is_array($options)) {
                $isList = array_is_list($options);
                foreach ($options as $value => $text) {
                    if (!is_scalar($text) || is_bool($text)) continue;
                    $f['options'][] = $isList ? [(string) $text, (string) $text] : [(string) $value, (string) $text];
                }
            }
            if ($f['options'] === []) {
                $this->warn("field('$key') is a 'select' without 'options' (value => label). It is 'text' now.");
                $f['type'] = 'text';
            }
        }
        $this->fields[$key] = $f;
        $this->resultCache = null;
        return $this;
    }

    /** The importer's accessible name ("Import customers"). Default: the catalogue's "CSV import". */
    public function label(string $text): static
    {
        $this->label = $text;
        return $this;
    }

    /**
     * How numbers are read where a lone separator is ambiguous: 'de' reads 1.500 as fifteen hundred,
     * 'en' as one and a half (1.234,50 and 1,234.50 are the same in both). Default: Lang::locale().
     */
    public function locale(string $locale): static
    {
        $family = strtolower(substr($locale, 0, 2));
        if (!in_array($family, ['de', 'en'], true)) {
            $this->warn("locale('$locale') — 'de' and 'en' (with their regions) are known. Numbers are read the English way.");
            $family = 'en';
        }
        $this->locale = $family;
        $this->resultCache = null;
        return $this;
    }

    /**
     * The most a file may hold: data rows (default 10,000, at most 100,000) and bytes (default 5 MB,
     * at most 20 MB). A larger file is refused with a sentence that says so, never cut short.
     */
    public function limits(int $rows = 10000, int $bytes = 5242880): static
    {
        if ($rows < 1 || $rows > self::HARD_ROWS) {
            $clamped = max(1, min(self::HARD_ROWS, $rows));
            $this->warn("limits(): $rows rows — between 1 and " . self::HARD_ROWS . ". It is $clamped now.");
            $rows = $clamped;
        }
        if ($bytes < 1 || $bytes > self::HARD_BYTES) {
            $clamped = max(1, min(self::HARD_BYTES, $bytes));
            $this->warn("limits(): $bytes bytes — between 1 and " . self::HARD_BYTES . ". It is $clamped now.");
            $bytes = $clamped;
        }
        $this->maxRows = $rows;
        $this->maxBytes = $bytes;
        $this->parsedCache = $this->resultCache = null;
        return $this;
    }

    /** How many rows the preview shows (default 20, 1 to 500). Every row is checked either way. */
    public function preview(int $rows): static
    {
        if ($rows < 1 || $rows > 500) {
            $clamped = max(1, min(500, $rows));
            $this->warn("preview($rows) — between 1 and 500. It is $clamped now.");
            $rows = $clamped;
        }
        $this->previewRows = $rows;
        return $this;
    }

    /** The separator: 'auto' (default), ';', ',', "\t" (or 'tab') or '|'. */
    public function delimiter(string $delimiter): static
    {
        $d = $delimiter === 'tab' ? "\t" : $delimiter;
        if ($d !== 'auto' && !isset(self::DELIMITERS[$d])) {
            $this->warn("delimiter(" . self::shown($delimiter) . ") — 'auto', ';', ',', tab or '|'. It is 'auto' now.");
            $d = 'auto';
        }
        $this->delimiter = $d;
        $this->parsedCache = $this->resultCache = null;
        return $this;
    }

    /** The quotation mark around a cell: 'auto' (default), '"' or "'". */
    public function enclosure(string $quote): static
    {
        if (!in_array($quote, ['auto', '"', "'"], true)) {
            $this->warn("enclosure(" . self::shown($quote) . ") — 'auto', '\"' or \"'\". It is 'auto' now.");
            $quote = 'auto';
        }
        $this->enclosure = $quote;
        $this->parsedCache = $this->resultCache = null;
        return $this;
    }

    /**
     * The encoding: 'auto' (default: a byte order mark decides, then valid UTF-8 is UTF-8 and
     * anything else Windows-1252), 'UTF-8', 'Windows-1252' (alias 'ISO-8859-1', read as its
     * superset), 'UTF-16LE', 'UTF-16BE'.
     */
    public function encoding(string $encoding): static
    {
        $enc = self::encodingName($encoding);
        if ($enc === null) {
            $this->warn("encoding('$encoding') — 'auto', " . implode(', ', self::ENCODINGS) . " or 'ISO-8859-1'. It is 'auto' now.");
            $enc = 'auto';
        }
        $this->encoding = $enc;
        $this->parsedCache = $this->resultCache = null;
        return $this;
    }

    /** Whether the first row holds the column names: true, false, or null (default) to decide from the file. */
    public function header(?bool $hasHeader = null): static
    {
        $this->header = $hasHeader;
        $this->parsedCache = $this->resultCache = null;
        return $this;
    }

    /**
     * The mapping to start from instead of matching by name — a mapping saved from the last import,
     * say. Keys: a column's index (0, 1, …) or its name; values: a field key, or null to ignore it.
     * Columns it does not name are ignored. What the person chooses on the page wins over it.
     *
     * @param array<int|string, ?string> $mapping
     */
    public function map(array $mapping): static
    {
        foreach ($mapping as $column => $key) {
            if ($key !== null && $key !== '' && !isset($this->fields[$key])) {
                $this->warn("map(): '" . (is_scalar($key) ? (string) $key : get_debug_type($key)) . "' is no field of this import — that column is ignored. Define the fields first.");
                $mapping[$column] = null;
            }
        }
        $this->defaultMap = $mapping;
        $this->resultCache = null;
        return $this;
    }

    /** Where the form posts to. Default: the page itself. */
    public function action(string $url): static
    {
        if (preg_match('/^\s*(javascript|data|vbscript):/i', $url) === 1) {
            $this->warn("action(): a '" . strtok($url, ':') . ":' URL is refused. The form posts to the page itself.");
            $url = '';
        }
        $this->action = $url;
        return $this;
    }

    /**
     * Hidden fields the form carries through every step — the CSRF token of the page, say:
     * ->hidden(['_token' => $csrf]). Checking it is the page's job, before handle().
     *
     * @param array<string,scalar> $fields
     */
    public function hidden(array $fields): static
    {
        foreach ($fields as $k => $v) {
            if (!is_string($k) || $k === '' || !is_scalar($v)) {
                $this->warn('hidden() takes name => value pairs of text; ' . self::shown($k) . ' was left out.');
                continue;
            }
            $this->hidden[$k] = (string) $v;
        }
        return $this;
    }

    /**
     * Where the file waits between the steps. A directory (default: the system's temporary directory,
     * /gridkit-import) and how long a file is kept in seconds (default 3600) — the form carries only
     * an unguessable token. false: nothing is kept on the server; the file travels in the form
     * (base64), for several servers without a shared directory or a public demo. Either way every
     * step reads and checks the file again.
     */
    public function storage(string|false $dir, int $ttl = 3600): static
    {
        if ($ttl < 60 || $ttl > 86400) {
            $clamped = max(60, min(86400, $ttl));
            $this->warn("storage(): a file is kept $ttl seconds — between 60 and 86400. It is $clamped now.");
            $ttl = $clamped;
        }
        $this->storageDir = $dir;
        $this->ttl = $ttl;
        return $this;
    }

    // ── Reading what arrived ────────────────────────────────────────────────

    /**
     * Reads the POST of this importer and moves it on a step: 'upload' takes the file (or the pasted
     * text), 'map' checks again with the posted mapping, 'confirm' checks once more and — when a row
     * is valid and every required field has its column — ends in step 'done', 'cancel' goes back to
     * the start. Without arguments it reads $_POST and $_FILES. Check the page's CSRF token first.
     *
     * @param array<string,mixed>|null $post
     * @param array<string,mixed>|null $files
     */
    public function handle(?array $post = null, ?array $files = null): static
    {
        if ($post === null && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST ?? []) === []
            && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && str_starts_with((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data')) {
            // Larger than post_max_size: PHP dropped the whole request, the importer's fields too.
            $this->refusal = Lang::t('import.err_too_large', ['max' => self::size($this->maxBytes)]);
            return $this;
        }
        $post ??= $_POST ?? [];
        $files ??= $_FILES ?? [];
        $in = $post[$this->name] ?? null;
        if (!is_array($in)) return $this;
        $action = is_string($in['step'] ?? null) ? $in['step'] : '';
        if (!in_array($action, ['upload', 'map', 'confirm', 'cancel', 'new'], true)) return $this;
        $this->collectGarbage();
        $this->resultCache = $this->parsedCache = null;

        if ($action === 'cancel' || $action === 'new') {
            $this->forget(self::str($in['token'] ?? null));
            $this->step = 'upload';
            if ($action === 'cancel') $this->notice = Lang::t('import.cancelled');
            return $this;
        }

        // What the person set on the page: the file options, the mapping and its column signature.
        $own = [$this->delimiter, $this->encoding, $this->header];
        $d = self::str($in['delimiter'] ?? null);
        if ($d === 'tab') $d = "\t";
        if (isset(self::DELIMITERS[$d])) $this->delimiter = $d;
        $enc = self::encodingName(self::str($in['encoding'] ?? null));
        if ($enc !== null && $enc !== 'auto') $this->encoding = $enc;
        $h = self::str($in['header'] ?? null);
        if ($h === '0' || $h === '1') $this->header = $h === '1';
        if (is_array($in['map'] ?? null)) {
            $this->postedMap = [];
            foreach ($in['map'] as $i => $key) {
                if (is_int($i) && $i >= 0 && $i < self::MAX_COLUMNS && is_string($key)) $this->postedMap[$i] = $key;
            }
            $this->postedSig = self::str($in['cols'] ?? null);
        }

        // The source: a file or text sent now wins over the one kept from the step before.
        $source = $this->readSent($in, $files);
        if ($source === false) {
            $this->step = 'upload';
            return $this;
        }
        if ($source === null) {
            if ($action === 'upload') {
                $this->refusal = Lang::t('import.err_none');
                return $this;
            }
            $kept = $this->readKept($in);
            if ($kept === null) {
                $this->step = 'upload';
                return $this;
            }
            [$bytes, $name, $token] = $kept;
            $this->load($bytes, $name);
            $this->token = $token;
        } else {
            [$bytes, $name] = $source;
            $this->forget(self::str($in['token'] ?? null));
            $this->load($bytes, $name);
        }

        $parsed = $this->parsed();
        if ($parsed !== null && $parsed['error'] !== null && $own !== [$this->delimiter, $this->encoding, $this->header]) {
            // A choice on the page made the file unreadable (UTF-8 for a Windows-1252 file): read it as
            // the page was told to, and say why the choice did not hold — the file is not lost.
            // The person's mapping still holds where the columns come out the same (the signature says).
            $this->warning = $parsed['error'];
            [$this->delimiter, $this->encoding, $this->header] = $own;
            $parsed = $this->parsed();
        }
        if ($parsed !== null && $parsed['error'] !== null) {
            $this->refusal = $parsed['error'];
            $this->forget($this->token);
            $this->step = 'upload';
            $this->source = null;
            return $this;
        }
        $this->step = 'map';
        if ($action === 'confirm') {
            $r = $this->result();
            if ($r['missing'] === [] && $r['valid'] > 0) {
                $this->step = 'done';
                $this->forget($this->token);
                $this->token = null;
            }
        }
        return $this;
    }

    /**
     * A CSV given as a string — from an API, a cron job, or a sample file. The importer is then in
     * step 'map'; result() checks it, render() shows the mapping and the preview.
     */
    public function fromString(string $bytes, string $filename = ''): static
    {
        $this->load($bytes, $filename);
        $this->token = null;
        $this->step = 'map';
        $parsed = $this->parsed();
        if ($parsed !== null && $parsed['error'] !== null) {
            $this->refusal = $parsed['error'];
            $this->step = 'upload';
        }
        return $this;
    }

    /** A CSV file on the server's disk; refused (result()['error']) when it is larger than limits() allow. */
    public function fromFile(string $path): static
    {
        if (!is_file($path) || !is_readable($path)) {
            $this->warn("fromFile(): '$path' is not a readable file.");
            $this->refusal = Lang::t('import.err_none');
            return $this;
        }
        $size = (int) filesize($path);
        if ($size > $this->maxBytes) {
            $this->refusal = $this->sizeError($size);
            $this->step = 'upload';
            return $this;
        }
        return $this->fromString((string) file_get_contents($path), basename($path));
    }

    /** Where the importer stands: 'upload', 'map' or 'done' (the person confirmed; take result()['rows']). */
    public function step(): string
    {
        return $this->step;
    }

    /**
     * What the file holds, checked:
     *   rows     — the valid rows, keyed by their row number in the sheet (the header is row 1):
     *              [2 => ['email' => 'anna@example.at', 'revenue' => '1234.50', …], …]. Only mapped
     *              fields are there; an empty cell is null (bool: false). Numbers and amounts are
     *              strings in machine form, dates 'Y-m-d', bool true/false, select the option's value.
     *   errors   — row number => [field key => message], for every row that is not valid
     *   total, valid, invalid — counts of data rows (empty lines are not rows)
     *   columns  — the column names (or "Column 1" … without a header row)
     *   mapping  — column index => field key, or null when the column is ignored
     *   missing  — required fields that no column fills
     *   notes    — what the person should know (a field chosen twice, a quote never closed)
     *   meta     — filename, bytes, encoding, bom, delimiter, enclosure, lineEnding, header, sep, unclosed
     *   error    — why the file was refused (too large, too many rows, not text), or null
     *
     * @return array<string,mixed>
     */
    public function result(): array
    {
        if ($this->resultCache !== null) return $this->resultCache;
        $p = $this->parsed();
        $r = ['rows' => [], 'errors' => [], 'total' => 0, 'valid' => 0, 'invalid' => 0, 'columns' => [],
              'mapping' => [], 'missing' => [], 'notes' => [], 'meta' => $p['meta'] ?? null,
              'error' => $this->refusal ?? ($p['error'] ?? null)];
        if ($p === null || $r['error'] !== null) return $this->resultCache = $r;

        $r['meta']['filename'] = $this->filename;
        $r['columns'] = $p['columns'];
        [$mapping, $notes] = $this->mapping($p['columns'], $p['meta']['header']);
        $r['mapping'] = $mapping;
        if ($p['meta']['unclosed'] !== null) {
            $r['notes'][] = Lang::t('import.unclosed', ['row' => self::num((int) $p['meta']['unclosed'])]);
        }
        $r['notes'] = array_merge($r['notes'], $notes);
        $colOf = [];
        foreach ($mapping as $i => $key) if ($key !== null) $colOf[$key] = $i;
        foreach ($this->fields as $key => $f) {
            if ($f['required'] && !isset($colOf[$key])) $r['missing'][] = $key;
        }

        $seen = [];
        foreach ($p['rows'] as $row => $cells) {
            $typed = [];
            $errors = [];
            foreach ($this->fields as $key => $f) {
                if (!isset($colOf[$key])) {
                    if ($f['required']) $errors[$key] = Lang::t('import.err_required', ['field' => $f['label']]);
                    continue;
                }
                [$value, $message] = $this->typed($f, self::trim($cells[$colOf[$key]] ?? ''));
                if ($message !== null) {
                    $errors[$key] = $message;
                    continue;
                }
                if ($f['unique'] && $value !== null) {
                    $u = self::uniqueKey($f['type'], $value);
                    if (isset($seen[$key][$u])) {
                        $errors[$key] = Lang::t('import.err_duplicate', ['row' => self::num($seen[$key][$u])]);
                        continue;
                    }
                    $seen[$key][$u] = $row;
                }
                $typed[$key] = $value;
            }
            $r['total']++;
            if ($errors === []) {
                $r['rows'][$row] = $typed;
                $r['valid']++;
            } else {
                $r['errors'][$row] = $errors;
                $r['invalid']++;
            }
        }
        return $this->resultCache = $r;
    }

    // ── Reading a CSV ───────────────────────────────────────────────────────

    /**
     * Reads a CSV without any field: decodes it, finds the separator, the quotation mark, the line
     * ending and the header row, and returns
     *   columns — the column names, rows — row number => cells (padded to the columns; empty lines
     *   left out), meta (see result()), error (a refusal, or null).
     * Options: delimiter, enclosure, encoding, header (as the setters), maxRows, maxBytes, names
     * (column names that mark a header row: the fields' keys, labels and aliases).
     *
     * @param array<string,mixed> $opts
     * @return array{columns:list<string>,rows:array<int,list<string>>,meta:array<string,mixed>,error:?string}
     */
    public static function parseCsv(string $bytes, array $opts = []): array
    {
        $opts += ['delimiter' => 'auto', 'enclosure' => 'auto', 'encoding' => 'auto', 'header' => null,
                  'maxRows' => 10000, 'maxBytes' => 5242880, 'names' => []];
        $maxRows = max(1, min(self::HARD_ROWS, (int) $opts['maxRows']));
        $maxBytes = max(1, min(self::HARD_BYTES, (int) $opts['maxBytes']));
        $meta = ['bytes' => strlen($bytes), 'encoding' => null, 'bom' => false, 'delimiter' => null, 'enclosure' => null,
                 'lineEnding' => null, 'header' => false, 'sep' => false, 'unclosed' => null];
        $out = ['columns' => [], 'rows' => [], 'meta' => $meta, 'error' => null];
        if (strlen($bytes) > $maxBytes) {
            $out['error'] = Lang::t('import.err_size', ['size' => self::size(strlen($bytes)), 'max' => self::size($maxBytes)]);
            return $out;
        }

        $encoding = self::encodingName(is_string($opts['encoding']) ? $opts['encoding'] : 'auto') ?? 'auto';
        $decoded = self::decode($bytes, $encoding);
        $meta['encoding'] = $decoded['encoding'];
        $meta['bom'] = $decoded['bom'];
        if ($decoded['error'] !== null) {
            $out['meta'] = $meta;
            $out['error'] = $decoded['error'];
            return $out;
        }
        $text = $decoded['text'];

        // Excel's own hint: a first line "sep=;" names the separator and is not a row.
        $delimiter = $opts['delimiter'] === 'tab' ? "\t" : $opts['delimiter'];
        if (preg_match('/^sep=([;,\t|])(\r\n|\n|\r|$)/', $text, $m) === 1) {
            $meta['sep'] = true;
            if (!isset(self::DELIMITERS[$delimiter])) $delimiter = $m[1];
            $text = (string) substr($text, strlen($m[0]));
        }
        $meta['lineEnding'] = self::lineEnding($text);

        $quote = in_array($opts['enclosure'], ['"', "'"], true) ? $opts['enclosure'] : self::detectQuote($text);
        if (!is_string($delimiter) || !isset(self::DELIMITERS[$delimiter])) $delimiter = self::detectDelimiter($text, $quote);
        $meta['enclosure'] = $quote;
        $meta['delimiter'] = $delimiter;

        // Two rows more than allowed: the header, and one to know that there are too many.
        $unclosed = null;
        $tooWide = false;
        $records = self::records($text, $delimiter, $quote, $maxRows + 2, $unclosed, $tooWide);
        $meta['unclosed'] = $unclosed;
        if ($tooWide) {
            $out['meta'] = $meta;
            $out['error'] = Lang::t('import.err_columns', ['max' => self::num(self::MAX_COLUMNS)]);
            return $out;
        }
        $hasHeader = $records === [] ? false
            : (is_bool($opts['header']) ? $opts['header'] : self::looksLikeHeader($records[0][1], is_array($opts['names']) ? $opts['names'] : []));
        $meta['header'] = $hasHeader;
        $head = $hasHeader ? array_shift($records)[1] : [];
        if (count($records) > $maxRows) {
            $out['meta'] = $meta;
            $out['error'] = Lang::t('import.err_rows', ['max' => self::num($maxRows)]);
            return $out;
        }

        $width = count($head);
        foreach ($records as [, $cells]) $width = max($width, count($cells));
        // Trailing columns with no name and nothing in them (Excel's ";;;;" at the end of a line) go.
        while ($width > 0) {
            $i = $width - 1;
            if (self::trim($head[$i] ?? '') !== '') break;
            foreach ($records as [, $cells]) if (self::trim($cells[$i] ?? '') !== '') break 2;
            $width--;
        }
        $columns = [];
        for ($i = 0; $i < $width; $i++) {
            $name = self::trim($head[$i] ?? '');
            $columns[] = $name !== '' ? $name : Lang::t('import.column_n', ['n' => $i + 1]);
        }
        $rows = [];
        foreach ($records as [$row, $cells]) {
            $rows[$row] = array_pad(array_slice($cells, 0, $width), $width, '');
        }
        return ['columns' => $columns, 'rows' => $rows, 'meta' => $meta, 'error' => null];
    }

    /**
     * The records of a text, each with its row number as a sheet counts it (an empty line is a row;
     * a line break inside quotes is not). Records whose cells are all empty are left out. Stops after
     * $limit records. Quotes are RFC 4180: a cell that starts with the quotation mark runs to the
     * next one that is not doubled; a quote inside an unquoted cell is a character.
     *
     * @return list<array{0:int,1:list<string>}>
     */
    private static function records(string $text, string $d, string $q, int $limit, ?int &$unclosed = null, bool &$tooWide = false): array
    {
        $unclosed = null;
        $tooWide = false;
        $len = strlen($text);
        $pos = 0;
        $row = 0;
        $out = [];
        $stops = $d . "\r\n";
        while ($pos < $len && count($out) < $limit) {
            $row++;
            $cells = [];
            $empty = true;
            while (true) {
                if ($pos < $len && $text[$pos] === $q) {
                    $pos++;
                    $cell = '';
                    while (true) {
                        $next = strpos($text, $q, $pos);
                        if ($next === false) {
                            $cell .= substr($text, $pos);
                            $pos = $len;
                            $unclosed ??= $row;
                            break;
                        }
                        $cell .= substr($text, $pos, $next - $pos);
                        $pos = $next + 1;
                        if ($pos < $len && $text[$pos] === $q) {
                            $cell .= $q;
                            $pos++;
                            continue;
                        }
                        break;
                    }
                    // Whatever follows the closing quote up to the separator belongs to the cell.
                    $n = strcspn($text, $stops, $pos);
                    $cell .= substr($text, $pos, $n);
                    $pos += $n;
                    $cell = str_replace(["\r\n", "\r"], "\n", $cell);
                } else {
                    $n = strcspn($text, $stops, $pos);
                    $cell = substr($text, $pos, $n);
                    $pos += $n;
                }
                $cells[] = $cell;
                if ($empty && self::trim($cell) !== '') $empty = false;
                if (count($cells) > self::MAX_COLUMNS) {
                    $tooWide = true;
                    return $out;
                }
                if ($pos >= $len) break;
                $c = $text[$pos];
                $pos++;
                if ($c === $d) {
                    if ($pos >= $len) {
                        $cells[] = '';
                        break;
                    }
                    continue;
                }
                if ($c === "\r" && $pos < $len && $text[$pos] === "\n") $pos++;
                break;
            }
            if (!$empty) $out[] = [$row, $cells];
        }
        return $out;
    }

    /** The quotation mark: ' only when the file opens and closes more cells with it than with ". */
    private static function detectQuote(string $text): string
    {
        $score = [];
        foreach (['"', "'"] as $q) {
            $qq = preg_quote($q, '/');
            $opens = (str_starts_with($text, $q) ? 1 : 0) + preg_match_all('/[;,\t|\r\n]' . $qq . '/', $text);
            $closes = (str_ends_with($text, $q) ? 1 : 0) + preg_match_all('/' . $qq . '[;,\t|\r\n]/', $text);
            $score[$q] = min($opens, $closes);
        }
        return $score["'"] > $score['"'] && $score["'"] >= 2 ? "'" : '"';
    }

    /**
     * The separator: the one that gives the first 50 records the same number of cells most often
     * (more than one), then the most cells, then the order ; , tab |. When none splits the lines, the
     * one that occurs least — so a list of names with a comma in one of them stays one column.
     */
    private static function detectDelimiter(string $text, string $quote): string
    {
        $best = null;
        $bestScore = null;
        foreach (array_keys(self::DELIMITERS) as $d) {
            $counts = [];
            $unclosed = null;
            $tooWide = false;
            foreach (self::records($text, (string) $d, $quote, self::SAMPLE_RECORDS, $unclosed, $tooWide) as [, $cells]) $counts[] = count($cells);
            // A separator that splits a row into more columns than allowed splits it best: it is the
            // one, and the file is refused for its width instead of being read as one column.
            if ($tooWide) $counts[] = self::MAX_COLUMNS + 1;
            if ($counts === []) continue;
            $freq = array_count_values($counts);
            $mode = 0;
            $hits = 0;
            foreach ($freq as $width => $n) {
                if ($n > $hits || ($n === $hits && $width > $mode)) {
                    $mode = (int) $width;
                    $hits = $n;
                }
            }
            if ($mode < 2) continue;
            $score = [$hits, count($counts), $mode];
            if ($bestScore === null || self::betterScore($score, $bestScore)) {
                $best = (string) $d;
                $bestScore = $score;
            }
        }
        if ($best !== null) return $best;
        $fewest = ';';
        $least = null;
        foreach (array_keys(self::DELIMITERS) as $d) {
            $n = substr_count($text, (string) $d);
            if ($least === null || $n < $least) {
                $least = $n;
                $fewest = (string) $d;
            }
        }
        return $fewest;
    }

    /** a beats b: a larger share of records with the usual width (compared as fractions), then more cells. */
    private static function betterScore(array $a, array $b): bool
    {
        $left = $a[0] * $b[1];
        $right = $b[0] * $a[1];
        if ($left !== $right) return $left > $right;
        return $a[2] > $b[2];
    }

    private static function lineEnding(string $text): string
    {
        $crlf = substr_count($text, "\r\n");
        $lf = substr_count($text, "\n") - $crlf;
        $cr = substr_count($text, "\r") - $crlf;
        if ($crlf + $lf + $cr === 0) return '';
        if ($crlf >= $lf && $crlf >= $cr) return 'CRLF';
        return $lf >= $cr ? 'LF' : 'CR';
    }

    /**
     * Is the first record a header? Yes when a cell names a field; no when a cell holds what a data
     * row holds (a number, a date, an e-mail address); yes otherwise.
     *
     * @param list<string> $cells
     * @param list<string> $names
     */
    private static function looksLikeHeader(array $cells, array $names): bool
    {
        $known = [];
        foreach ($names as $n) if (is_string($n)) foreach (self::folds($n) as $f) $known[$f] = true;
        foreach ($cells as $cell) {
            foreach (self::folds($cell) as $f) if (isset($known[$f])) return true;
        }
        foreach ($cells as $cell) {
            $t = self::trim($cell);
            if ($t === '') continue;
            if (LineItems::parseNumber($t, 'en') !== null || self::date($t) !== null || self::isEmail($t)) return false;
        }
        return true;
    }

    /**
     * Bytes to UTF-8 text. 'auto': a byte order mark decides (UTF-8, UTF-16 LE or BE); a file with
     * NUL bytes in every other place is UTF-16 without one; valid UTF-8 is UTF-8; anything else is
     * Windows-1252 — what German Excel writes, and a superset of ISO-8859-1. The browser reads the
     * same with TextDecoder.
     *
     * @return array{text:string,encoding:string,bom:bool,error:?string}
     */
    private static function decode(string $bytes, string $encoding): array
    {
        $bom = false;
        $enc = $encoding;
        if (str_starts_with($bytes, "\xEF\xBB\xBF") && ($enc === 'auto' || $enc === 'UTF-8')) {
            $bom = true;
            $bytes = substr($bytes, 3);
            if ($enc === 'auto') $enc = preg_match('//u', $bytes) === 1 ? 'UTF-8' : 'Windows-1252';
        } elseif (str_starts_with($bytes, "\xFF\xFE") && ($enc === 'auto' || $enc === 'UTF-16LE')) {
            $bom = true;
            $bytes = substr($bytes, 2);
            $enc = 'UTF-16LE';
        } elseif (str_starts_with($bytes, "\xFE\xFF") && ($enc === 'auto' || $enc === 'UTF-16BE')) {
            $bom = true;
            $bytes = substr($bytes, 2);
            $enc = 'UTF-16BE';
        }
        if ($enc === 'auto') {
            $enc = self::guessUtf16($bytes) ?? (preg_match('//u', $bytes) === 1 ? 'UTF-8' : 'Windows-1252');
        }
        $text = match ($enc) {
            'UTF-8' => preg_match('//u', $bytes) === 1 ? $bytes : null,
            'Windows-1252' => self::fromCp1252($bytes),
            default => self::fromUtf16($bytes, $enc === 'UTF-16BE'),
        };
        if ($text === null) {
            return ['text' => '', 'encoding' => $enc, 'bom' => $bom, 'error' => Lang::t('import.err_utf8')];
        }
        if (str_contains($text, "\0")) {
            return ['text' => '', 'encoding' => $enc, 'bom' => $bom, 'error' => Lang::t('import.err_binary')];
        }
        return ['text' => $text, 'encoding' => $enc, 'bom' => $bom, 'error' => null];
    }

    /** UTF-16 without a byte order mark: in the first 4 KB, NUL in most odd places and almost no even one (LE), or the reverse. */
    private static function guessUtf16(string $bytes): ?string
    {
        $n = min(strlen($bytes), 4096) & ~1;
        if ($n < 4 || !str_contains(substr($bytes, 0, $n), "\0")) return null;
        $even = $odd = 0;
        for ($i = 0; $i < $n; $i += 2) {
            if ($bytes[$i] === "\0") $even++;
            if ($bytes[$i + 1] === "\0") $odd++;
        }
        $pairs = $n / 2;
        if ($odd * 10 >= $pairs * 3 && $even * 20 <= $pairs) return 'UTF-16LE';
        if ($even * 10 >= $pairs * 3 && $odd * 20 <= $pairs) return 'UTF-16BE';
        return null;
    }

    private static function fromCp1252(string $bytes): string
    {
        static $table = null;
        if ($table === null) {
            $table = [];
            for ($b = 0x80; $b <= 0xFF; $b++) $table[chr($b)] = self::utf8(self::CP1252[$b] ?? $b);
        }
        return strtr($bytes, $table);
    }

    /** UTF-16 to UTF-8; an unpaired surrogate or a last odd byte becomes U+FFFD, as TextDecoder does. */
    private static function fromUtf16(string $bytes, bool $bigEndian): string
    {
        $out = '';
        $len = strlen($bytes);
        $even = $len & ~1;
        $lead = null;
        for ($start = 0; $start < $even; $start += 65536) {
            $units = unpack(($bigEndian ? 'n' : 'v') . '*', substr($bytes, $start, min(65536, $even - $start))) ?: [];
            foreach ($units as $u) {
                if ($lead !== null) {
                    if ($u >= 0xDC00 && $u <= 0xDFFF) {
                        $out .= self::utf8(0x10000 + (($lead - 0xD800) << 10) + ($u - 0xDC00));
                        $lead = null;
                        continue;
                    }
                    $out .= "\u{FFFD}";
                    $lead = null;
                }
                if ($u >= 0xD800 && $u <= 0xDBFF) $lead = $u;
                elseif ($u >= 0xDC00 && $u <= 0xDFFF) $out .= "\u{FFFD}";
                else $out .= self::utf8($u);
            }
        }
        // One replacement for whatever is left open at the end — a lead surrogate, an odd byte or both.
        if ($lead !== null || ($len & 1)) $out .= "\u{FFFD}";
        return $out;
    }

    private static function utf8(int $cp): string
    {
        if ($cp < 0x80) return chr($cp);
        if ($cp < 0x800) return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
        if ($cp < 0x10000) return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
        return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    }

    private static function encodingName(string $encoding): ?string
    {
        $e = strtoupper(str_replace('_', '-', trim($encoding)));
        return match ($e) {
            'AUTO', '' => 'auto',
            'UTF-8', 'UTF8' => 'UTF-8',
            'WINDOWS-1252', 'CP1252', 'ISO-8859-1', 'LATIN1', 'LATIN-1' => 'Windows-1252',
            'UTF-16LE', 'UTF-16' => 'UTF-16LE',
            'UTF-16BE' => 'UTF-16BE',
            default => null,
        };
    }

    // ── Matching and checking ───────────────────────────────────────────────

    /**
     * Which field each column fills, and what the person should know about it. The person's own
     * choice (posted for these very columns), else map(), else by name: key and label first, then
     * the aliases, columns from left to right. A field chosen for two columns keeps the first.
     *
     * @param list<string> $columns
     * @return array{0:array<int,?string>,1:list<string>}
     */
    private function mapping(array $columns, bool $hasHeader): array
    {
        $map = array_fill(0, count($columns), null);
        if ($this->postedMap !== null && $this->postedSig === self::signature($columns)) {
            foreach ($map as $i => $_) {
                $key = $this->postedMap[$i] ?? '';
                $map[$i] = isset($this->fields[$key]) ? $key : null;
            }
        } elseif ($this->defaultMap !== null) {
            foreach ($this->defaultMap as $column => $key) {
                if ($key === null || $key === '' || !isset($this->fields[$key])) continue;
                $i = is_int($column) ? $column : array_search((string) $column, $columns, true);
                if (is_int($i) && $i >= 0 && $i < count($columns)) $map[$i] = $key;
            }
        } elseif ($hasHeader) {
            $taken = [];
            foreach ([false, true] as $aliases) {
                foreach ($columns as $i => $column) {
                    if ($map[$i] !== null) continue;
                    $folds = self::folds($column);
                    if ($folds === []) continue;
                    foreach ($this->fields as $key => $f) {
                        if (isset($taken[$key])) continue;
                        foreach ($aliases ? $f['aliases'] : [$key, $f['label']] as $name) {
                            if (array_intersect($folds, self::folds($name)) !== []) {
                                $map[$i] = $key;
                                $taken[$key] = true;
                                continue 3;
                            }
                        }
                    }
                }
            }
        }
        $notes = [];
        $first = [];
        foreach ($map as $i => $key) {
            if ($key === null) continue;
            if (isset($first[$key])) {
                $notes[] = Lang::t('import.twice', ['field' => $this->fields[$key]['label'], 'column' => $columns[$first[$key]]]);
                $map[$i] = null;
                continue;
            }
            $first[$key] = $i;
        }
        return [$map, $notes];
    }

    /**
     * One cell as its field's type: [value, null] or [null, message].
     *
     * @param array{key:string,label:string,type:string,required:bool,unique:bool,aliases:list<string>,options:list<array{0:string,1:string}>} $f
     * @return array{0:mixed,1:?string}
     */
    private function typed(array $f, string $raw): array
    {
        if ($raw === '') {
            if ($f['required']) return [null, Lang::t('import.err_required', ['field' => $f['label']])];
            return [$f['type'] === 'bool' ? false : null, null];
        }
        $de = $this->localeFamily() === 'de';
        switch ($f['type']) {
            case 'email':
                if (!self::isEmail($raw)) return [null, Lang::t('import.err_email')];
                $at = (int) strrpos($raw, '@');
                return [substr($raw, 0, $at) . '@' . strtolower(substr($raw, $at + 1)), null];
            case 'number':
                $n = LineItems::parseNumber($raw, $this->localeFamily());
                return $n === null ? [null, Lang::t('import.err_number', ['example' => $de ? '1.234,5' : '1,234.5'])] : [$n, null];
            case 'money':
                $n = LineItems::parseNumber($raw, $this->localeFamily());
                if ($n === null) return [null, Lang::t('import.err_money', ['example' => $de ? '1.234,50' : '1,234.50'])];
                $dot = strpos($n, '.');
                $frac = $dot === false ? '' : substr($n, $dot + 1);
                if (strlen($frac) > 2) return [null, Lang::t('import.err_decimals')];
                return [($dot === false ? $n : substr($n, 0, $dot)) . '.' . str_pad($frac, 2, '0'), null];
            case 'date':
                $date = self::date($raw);
                return $date === null ? [null, Lang::t('import.err_date')] : [$date, null];
            case 'bool':
                $w = strtolower($raw);
                if (in_array($w, self::TRUE_WORDS, true)) return [true, null];
                if (in_array($w, self::FALSE_WORDS, true)) return [false, null];
                return [null, Lang::t('import.err_bool')];
            case 'select':
                foreach ($f['options'] as [$value]) if ($value === $raw) return [$value, null];
                $folds = self::folds($raw);
                foreach ($f['options'] as [$value, $label]) {
                    if (array_intersect($folds, array_merge(self::folds($value), self::folds($label))) !== []) return [$value, null];
                }
                $labels = array_map(static fn (array $o): string => $o[1], array_slice($f['options'], 0, 8));
                return [null, Lang::t('import.err_option', ['options' => implode(', ', $labels) . (count($f['options']) > 8 ? ', …' : '')])];
        }
        return [$raw, null];
    }

    /** An e-mail address as mail servers take it: ASCII, a dot in the domain, a TLD of letters (or xn--). */
    private static function isEmail(string $s): bool
    {
        return preg_match('/^(?=.{1,254}$)(?=[^@]{1,64}@)[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*'
            . '@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+(?:[A-Za-z]{2,63}|xn--[A-Za-z0-9-]{1,59})$/D', $s) === 1;
    }

    /** d.m.Y, Y-m-d or d/m/Y (day and month with or without a leading zero) as 'Y-m-d', or null. */
    private static function date(string $s): ?string
    {
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/D', $s, $m) === 1) [$d, $mo, $y] = [$m[1], $m[2], $m[3]];
        elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/D', $s, $m) === 1) [$y, $mo, $d] = [$m[1], $m[2], $m[3]];
        elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/D', $s, $m) === 1) [$d, $mo, $y] = [$m[1], $m[2], $m[3]];
        else return null;
        [$d, $mo, $y] = [(int) $d, (int) $mo, (int) $y];
        if ($y < 1 || !checkdate($mo, $d, $y)) return null;
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    /** What makes two values the same for 'unique': an address and a text ignore case. */
    private static function uniqueKey(string $type, mixed $value): string
    {
        if (is_bool($value)) return $value ? '1' : '0';
        $s = (string) $value;
        if ($type === 'email') return strtolower($s);
        if ($type === 'text') return self::lower($s);
        return $s;
    }

    /** Lower case for A–Z and À–Þ — the same in PHP with or without mbstring, and in the script. */
    private static function lower(string $s): string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            for ($cp = 0xC0; $cp <= 0xDE; $cp++) if ($cp !== 0xD7) $map[self::utf8($cp)] = self::utf8($cp + 0x20);
        }
        return strtr(strtolower($s), $map);
    }

    /** @return list<string> the name folded with ä → ae and with ä → a; empty when nothing is left. */
    private static function folds(string $s): array
    {
        $out = [];
        foreach ([self::UMLAUT_AE, self::UMLAUT_A] as $umlauts) {
            $f = (string) preg_replace('/[^a-z0-9]+/', '', strtolower(strtr($s, $umlauts + self::FOLD)));
            if ($f !== '' && !in_array($f, $out, true)) $out[] = $f;
        }
        return $out;
    }

    /** trim() with the no-break space Excel likes, and nothing else — the script trims the very same. */
    private static function trim(string $s): string
    {
        if ($s === '') return $s;
        if (strpos(" \t\n\r\x0B\f\xC2", $s[0]) === false && strpos(" \t\n\r\x0B\f\xA0", $s[strlen($s) - 1]) === false) return $s;
        return (string) preg_replace('/^(?:[ \t\n\r\x0B\f]|\xC2\xA0)+|(?:[ \t\n\r\x0B\f]|\xC2\xA0)+$/D', '', $s);
    }

    /** The columns a mapping was drawn for: FNV-1a of their names, so a mapping for other columns is not applied. */
    private static function signature(array $columns): string
    {
        return hash('fnv1a32', count($columns) . "\x1e" . implode("\x1f", $columns));
    }

    // ── Keeping the file between the steps ──────────────────────────────────

    private function load(string $bytes, string $filename): void
    {
        $this->source = $bytes;
        // A name to show, never a path to use: the last part, no control characters, 120 characters.
        $name = basename(str_replace('\\', '/', $filename));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $name);
        if (preg_match('//u', $name) !== 1) $name = self::fromCp1252($name);
        preg_match('/^.{0,120}/su', $name, $m);
        $this->filename = $m[0] ?? '';
        $this->refusal = null;
        $this->parsedCache = $this->resultCache = null;
    }

    /**
     * A file or text sent with this request: [bytes, name], null when none was sent, false when one
     * was sent and refused (the reason is in $this->refusal).
     *
     * @param array<string,mixed> $in
     * @param array<string,mixed> $files
     * @return array{0:string,1:string}|null|false
     */
    private function readSent(array $in, array $files): array|null|false
    {
        $f = $files[$this->name] ?? null;
        if (is_array($f)) {
            $pick = static fn (string $part): mixed => is_array($f[$part] ?? null) ? ($f[$part]['file'] ?? null) : null;
            $error = $pick('error');
            $tmp = $pick('tmp_name');
            $name = $pick('name');
            if (is_int($error) || (is_string($error) && ctype_digit($error))) {
                $error = (int) $error;
                if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                    $this->refusal = Lang::t('import.err_too_large', ['max' => self::size($this->maxBytes)]);
                    return false;
                }
                if ($error !== UPLOAD_ERR_NO_FILE && $error !== UPLOAD_ERR_OK) {
                    $this->refusal = Lang::t('import.err_upload', ['code' => $error]);
                    return false;
                }
                if ($error === UPLOAD_ERR_OK && is_string($tmp) && $tmp !== '') {
                    $check = $this->uploadCheck ?? static fn (string $path): bool => is_uploaded_file($path);
                    if (!$check($tmp)) {
                        $this->refusal = Lang::t('import.err_upload', ['code' => 'tmp']);
                        return false;
                    }
                    $size = (int) @filesize($tmp);
                    if ($size > $this->maxBytes) {
                        $this->refusal = $this->sizeError($size);
                        return false;
                    }
                    return [(string) file_get_contents($tmp), is_string($name) ? $name : ''];
                }
            }
        }
        $text = $in['text'] ?? null;
        if (is_string($text) && trim($text) !== '') {
            if (strlen($text) > $this->maxBytes) {
                $this->refusal = $this->sizeError(strlen($text));
                return false;
            }
            $this->text = $text;
            return [$text, ''];
        }
        return null;
    }

    /**
     * The file of the step before: kept under its token, or carried in the form (storage(false)).
     *
     * @param array<string,mixed> $in
     * @return array{0:string,1:string,2:?string}|null
     */
    private function readKept(array $in): ?array
    {
        $token = self::str($in['token'] ?? null);
        if ($token !== '' && $this->storageDir !== false) {
            $path = $this->tokenPath($token);
            $blob = $path !== null && is_file($path) && filemtime($path) >= time() - $this->ttl && $this->owns($token)
                ? (string) @file_get_contents($path) : null;
            $cut = $blob === null ? false : strpos($blob, "\n");
            $meta = $cut === false ? null : json_decode(substr((string) $blob, 0, $cut), true);
            if (!is_array($meta)) {
                $this->refusal = Lang::t('import.err_expired', ['minutes' => (string) intdiv($this->ttl, 60)]);
                return null;
            }
            return [substr((string) $blob, (int) $cut + 1), is_string($meta['name'] ?? null) ? $meta['name'] : '', $token];
        }
        $data = self::str($in['data'] ?? null);
        if ($data !== '' && $this->storageDir === false) {
            // Larger than any file may be: not even decoded. Within that, parseCsv() says the exact size.
            if (strlen($data) > intdiv(self::HARD_BYTES * 4, 3) + 8) {
                $this->refusal = $this->sizeError(intdiv(strlen($data) * 3, 4));
                return null;
            }
            $bytes = base64_decode($data, true);
            if ($bytes !== false) return [$bytes, self::str($in['filename'] ?? null), null];
        }
        $this->refusal = Lang::t('import.err_expired', ['minutes' => (string) intdiv($this->ttl, 60)]);
        return null;
    }

    /** Writes the source under a new token (once), for the next step. */
    private function keep(): void
    {
        if ($this->token !== null || $this->source === null || $this->storageDir === false) return;
        $dir = $this->dir();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            $this->warn("storage(): '$dir' cannot be created — the file cannot wait for the next step.");
            $this->refusal = Lang::t('import.err_store');
            $this->step = 'upload';
            return;
        }
        $token = bin2hex(random_bytes(16));
        $path = $dir . '/gk-import-' . $token . '.csv';
        $meta = (string) json_encode(['name' => $this->filename, 'import' => $this->name], JSON_UNESCAPED_UNICODE);
        if (@file_put_contents($path, $meta . "\n" . $this->source, LOCK_EX) === false) {
            $this->warn("storage(): '$dir' is not writable — the file cannot wait for the next step.");
            $this->refusal = Lang::t('import.err_store');
            $this->step = 'upload';
            return;
        }
        @chmod($path, 0600);
        $this->token = $token;
        // With a session the token belongs to it: another visitor's browser cannot use it.
        if (session_status() === PHP_SESSION_ACTIVE) {
            $list = is_array($_SESSION['gk_import'] ?? null) ? $_SESSION['gk_import'] : [];
            $list[] = $token;
            $_SESSION['gk_import'] = array_slice($list, -20);
        }
    }

    private function owns(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return true;
        return in_array($token, is_array($_SESSION['gk_import'] ?? null) ? $_SESSION['gk_import'] : [], true);
    }

    private function forget(?string $token): void
    {
        if ($token === null || $token === '' || $this->storageDir === false) return;
        $path = $this->tokenPath($token);
        if ($path !== null && is_file($path) && $this->owns($token)) @unlink($path);
    }

    /** Files older than the time they are kept, removed when an importer is handled. */
    private function collectGarbage(): void
    {
        if ($this->storageDir === false) return;
        foreach (glob($this->dir() . '/gk-import-*.csv') ?: [] as $path) {
            if (preg_match('/\/gk-import-[0-9a-f]{32}\.csv$/', $path) === 1 && (int) @filemtime($path) < time() - $this->ttl) @unlink($path);
        }
    }

    private function tokenPath(string $token): ?string
    {
        return preg_match('/^[0-9a-f]{32}$/D', $token) === 1 ? $this->dir() . '/gk-import-' . $token . '.csv' : null;
    }

    private function dir(): string
    {
        return $this->storageDir === '' || $this->storageDir === false
            ? rtrim(sys_get_temp_dir(), '/\\') . '/gridkit-import' : rtrim($this->storageDir, '/\\');
    }

    // ── Rendering ───────────────────────────────────────────────────────────

    /** Prints the importer: the step it stands at. */
    public function render(): void
    {
        echo $this->html();
    }

    /** The importer as a string — the twin of render(). */
    public function html(): string
    {
        $e = self::escaper();
        $id = 'gk-import-' . $this->name;
        if ($this->step === 'map') $this->keep();
        $r = $this->step === 'upload' ? null : $this->result();
        if ($r !== null && $r['error'] !== null) $this->step = 'upload';
        $label = $this->label !== '' ? $this->label : Lang::t('import.label');
        $html = '<form class="gk-import" id="' . $e($id) . '" method="post" enctype="multipart/form-data" action="' . $e($this->action) . '"'
            . ' aria-label="' . $e($label) . '" data-gk-import="' . $e($this->name) . '" data-gk-import-step="' . $this->step . '">'
            . '<script type="application/json" data-gk-import-config>' . $this->configJson() . '</script>';
        foreach ($this->hidden as $k => $v) $html .= '<input type="hidden" name="' . $e($k) . '" value="' . $e($v) . '">';
        $html .= $this->stepsHtml();
        if ($this->notice !== null) {
            $html .= self::message('info', 'info', $this->notice, 'status');
        }
        if ($this->step === 'map' && $r !== null) {
            $html .= $this->panelHtml($r);
        } elseif ($this->step === 'done' && $r !== null) {
            $html .= $this->doneHtml($r);
        } else {
            $html .= $this->uploadHtml($r['error'] ?? $this->refusal);
        }
        return $html . '</form>';
    }

    private function stepsHtml(): string
    {
        $e = self::escaper();
        $at = ['upload' => 0, 'map' => 1, 'done' => 2][$this->step] ?? 0;
        $html = '<ol class="gk-steps" aria-label="' . $e(Lang::t('import.steps')) . '">';
        foreach (['file', 'map', 'done'] as $i => $s) {
            $text = $e(Lang::t('import.step_' . $s));
            if ($i < $at) {
                $html .= '<li class="gk-steps-item gk-steps-done">' . $text . '<span class="gk-sr-only"> ' . $e(Lang::t('import.step_done_sr')) . '</span></li>';
            } elseif ($i === $at) {
                $html .= '<li class="gk-steps-item gk-steps-current" aria-current="step">' . $text . '</li>';
            } else {
                $html .= '<li class="gk-steps-item">' . $text . '</li>';
            }
        }
        return $html . '</ol>';
    }

    private function uploadHtml(?string $error): string
    {
        $e = self::escaper();
        $base = 'gk-import-' . $this->name;
        $html = '';
        if ($error !== null) $html .= self::message('error', 'error', $error, 'alert');
        $html .= '<div class="gk-import-upload">'
            . '<div class="gk-import-drop">'
            . '<span class="material-icons gk-import-drop-icon" aria-hidden="true">upload_file</span>'
            . '<label class="gk-import-drop-text" for="' . $e($base . '-file') . '">' . $e(Lang::t('import.drop')) . '</label>'
            . '<input type="file" id="' . $e($base . '-file') . '" name="' . $e($this->name) . '[file]"'
            . ' accept=".csv,.txt,.tsv,text/csv,text/plain,text/tab-separated-values" aria-describedby="' . $e($base . '-hint') . '">'
            . '<span class="gk-import-hint" id="' . $e($base . '-hint') . '">'
            . $e(Lang::t('import.hint', ['size' => self::size($this->maxBytes), 'rows' => $this->rowsText($this->maxRows)])) . '</span>'
            . '</div>'
            . '<details class="gk-disclosure gk-import-paste"' . ($this->text !== '' ? ' open' : '') . '>'
            . '<summary>' . $e(Lang::t('import.paste')) . '</summary>'
            . '<label class="gk-label-text" for="' . $e($base . '-text') . '">' . $e(Lang::t('import.paste_label')) . '</label>'
            . '<textarea class="gk-input gk-import-text" id="' . $e($base . '-text') . '" name="' . $e($this->name) . '[text]" rows="6"'
            . ' spellcheck="false" autocomplete="off">' . $e(strlen($this->text) <= $this->maxBytes ? $this->text : '') . '</textarea>'
            . '</details>'
            . '</div>'
            . '<div class="gk-import-actions" data-gk-import-start>'
            . self::submit($this->name, 'upload', Lang::t('import.next'), 'filled', 'primary', 'arrow_forward')
            . '</div>';
        return $html;
    }

    /** @param array<string,mixed> $r */
    private function panelHtml(array $r): string
    {
        $e = self::escaper();
        $base = 'gk-import-' . $this->name;
        $meta = $r['meta'];
        $columns = $r['columns'];
        $mapping = $r['mapping'];
        $p = $this->parsed();
        $rows = $p['rows'] ?? [];

        $html = '<div class="gk-import-panel">';
        // What was read, in one line.
        $what = $this->filename !== '' ? $this->filename : Lang::t('import.pasted');
        $html .= '<p class="gk-import-source"><span class="material-icons" aria-hidden="true">description</span>'
            . '<strong>' . $e($what) . '</strong> · ' . $e(self::size((int) $meta['bytes'])) . ' · '
            . $e($this->rowsText((int) $r['total'])) . '</p>';
        if ($this->warning !== null) $html .= self::message('error', 'error', $this->warning, 'alert');
        if ($r['notes'] !== []) {
            $html .= '<div class="gk-message gk-message-warning gk-message-sm gk-import-notes"><span class="material-icons" aria-hidden="true">warning</span>'
                . '<div class="gk-message-content"><ul>';
            foreach ($r['notes'] as $note) $html .= '<li>' . $e($note) . '</li>';
            $html .= '</ul></div></div>';
        }

        // How the file is read.
        $html .= '<fieldset class="gk-import-options"><legend>' . $e(Lang::t('import.options')) . '</legend>'
            . '<label class="gk-import-option"><span class="gk-label-text">' . $e(Lang::t('import.delimiter')) . '</span>'
            . '<select class="gk-input" name="' . $e($this->name) . '[delimiter]">';
        foreach (self::DELIMITERS as $d => $word) {
            $html .= '<option value="' . ($d === "\t" ? 'tab' : $e($d)) . '"' . ($meta['delimiter'] === $d ? ' selected' : '') . '>'
                . $e(Lang::t('import.delim_' . $word)) . '</option>';
        }
        $html .= '</select></label>';
        $html .= '<label class="gk-import-option"><span class="gk-label-text">' . $e(Lang::t('import.encoding')) . '</span>'
            . '<select class="gk-input" name="' . $e($this->name) . '[encoding]">';
        foreach (self::ENCODINGS as $enc) {
            $html .= '<option value="' . $e($enc) . '"' . ($meta['encoding'] === $enc ? ' selected' : '') . '>'
                . $e(Lang::t('import.enc_' . strtolower(str_replace('-', '', $enc)))) . '</option>';
        }
        $html .= '</select></label>';
        $html .= '<input type="hidden" name="' . $e($this->name) . '[header]" value="0">'
            . '<label class="gk-checkbox-wrap gk-import-option"><input type="checkbox" name="' . $e($this->name) . '[header]" value="1"'
            . ($meta['header'] ? ' checked' : '') . '><span class="gk-checkbox-custom"></span>'
            . '<span class="gk-checkbox-text">' . $e(Lang::t('import.header')) . '</span></label>'
            . '</fieldset>';

        // Which field each column fills.
        $html .= '<fieldset class="gk-import-map"><legend>' . $e(Lang::t('import.map')) . '</legend>';
        foreach ($columns as $i => $column) {
            $key = $mapping[$i] ?? null;
            $sample = [];
            foreach ($rows as $cells) {
                $v = self::trim($cells[$i] ?? '');
                if ($v !== '') $sample[] = self::cut($v, 24);
                if (count($sample) === 2) break;
            }
            $sid = $base . '-sample-' . $i;
            $html .= '<div class="gk-import-col' . ($key === null ? ' gk-import-ignored' : '') . '">'
                . '<label class="gk-import-col-name" for="' . $e($base . '-map-' . $i) . '"><span class="gk-sr-only">'
                . $e(Lang::t('import.column')) . ' </span>' . $e($column) . '</label>'
                . '<select class="gk-input" id="' . $e($base . '-map-' . $i) . '" name="' . $e($this->name) . '[map][' . $i . ']" aria-describedby="' . $e($sid) . '">'
                . '<option value="">' . $e(Lang::t('import.ignore')) . '</option>';
            foreach ($this->fields as $k => $f) {
                $html .= '<option value="' . $e($k) . '"' . ($key === $k ? ' selected' : '') . '>' . $e($f['label'])
                    . ($f['required'] ? ' ' . $e(Lang::t('import.required_mark')) : '') . '</option>';
            }
            $html .= '</select><span class="gk-import-col-sample" id="' . $e($sid) . '">'
                . $e($sample === [] ? Lang::t('import.empty_column') : Lang::t('import.sample', ['values' => implode(', ', $sample)]))
                . '</span></div>';
        }
        $html .= '</fieldset>';

        // Everything that follows the mapping: js/import.js draws this part again after each change.
        $html .= '<div data-gk-import-live>';
        if ($r['missing'] !== []) {
            $names = array_map(fn (string $k): string => $this->fields[$k]['label'], $r['missing']);
            $html .= self::message('error', 'error', Lang::t('import.missing', ['fields' => implode(', ', $names)]), '', 'gk-import-missing');
        }
        $html .= $this->summaryHtml($r);
        $html .= $this->previewHtml($r, $rows);
        $html .= $this->errorsHtml($r, $rows);

        // What to do now.
        $ready = $r['missing'] === [] && $r['valid'] > 0;
        $html .= '<div class="gk-import-actions">'
            . self::submit($this->name, 'confirm', $this->confirmText($r), 'filled', 'primary', 'download_done', !$ready)
            . self::submit($this->name, 'map', Lang::t('import.recheck'), 'outlined', 'primary', 'refresh')
            . self::submit($this->name, 'cancel', Lang::t('import.cancel'), 'text', 'neutral', '', false, ' formnovalidate');
        if ($ready && $r['invalid'] > 0) $html .= '<span class="gk-import-skip">' . $e(Lang::t('import.skip_note')) . '</span>';
        $html .= '</div></div>';

        $html .= '<input type="hidden" name="' . $e($this->name) . '[cols]" value="' . $e(self::signature($columns)) . '">';
        if ($this->token !== null) {
            $html .= '<input type="hidden" name="' . $e($this->name) . '[token]" value="' . $e($this->token) . '">';
        } elseif ($this->storageDir === false && $this->source !== null) {
            $html .= '<input type="hidden" name="' . $e($this->name) . '[data]" value="' . base64_encode($this->source) . '" data-gk-import-data>'
                . '<input type="hidden" name="' . $e($this->name) . '[filename]" value="' . $e($this->filename) . '">';
        }
        return $html . '</div>';
    }

    /** @param array<string,mixed> $r */
    private function summaryHtml(array $r): string
    {
        if ($r['total'] === 0) return self::message('warning', 'info', Lang::t('import.summary_none'), 'status', 'gk-import-summary');
        if ($r['invalid'] === 0) {
            return self::message('success', 'check_circle', Lang::t('import.summary_ok', ['rows' => $this->rowsText($r['total'])]), 'status', 'gk-import-summary');
        }
        return self::message('warning', 'error', Lang::t('import.summary_errors', ['rows' => $this->rowsText($r['total']), 'bad' => self::num($r['invalid'])]), 'status', 'gk-import-summary');
    }

    /**
     * The first rows as they are in the file, each cell's message under it — a symbol and words, not
     * a colour alone — and a check column that says OK or how many errors.
     *
     * @param array<string,mixed> $r
     * @param array<int,list<string>> $rows
     */
    private function previewHtml(array $r, array $rows): string
    {
        if ($rows === []) return '';
        $e = self::escaper();
        $base = 'gk-import-' . $this->name;
        $shown = array_slice($rows, 0, $this->previewRows, true);
        $caption = count($rows) > count($shown)
            ? Lang::t('import.preview', ['n' => self::num(count($shown))]) : Lang::t('import.preview_all');
        $html = '<div class="gk-table-wrap gk-import-preview" role="region" tabindex="0" aria-labelledby="' . $e($base . '-caption') . '">'
            . '<table class="gk-table"><caption class="gk-sr-only" id="' . $e($base . '-caption') . '">' . $e($caption) . '</caption>'
            . '<thead><tr><th scope="col">' . $e(Lang::t('import.row')) . '</th><th scope="col">' . $e(Lang::t('import.status')) . '</th>';
        foreach ($r['columns'] as $i => $column) {
            $key = $r['mapping'][$i] ?? null;
            $html .= '<th scope="col"' . ($key === null ? ' class="gk-import-ignored"' : '') . '>' . $e($column)
                . '<span class="gk-import-target">' . $e($key === null ? Lang::t('import.ignored') : '→ ' . $this->fields[$key]['label']) . '</span></th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($shown as $row => $cells) {
            $errors = $r['errors'][$row] ?? [];
            $byColumn = [];
            $other = [];
            foreach ($errors as $key => $message) {
                $col = array_search($key, $r['mapping'], true);
                if (is_int($col)) $byColumn[$col] = $message;
                else $other[] = $message;
            }
            $html .= '<tr' . ($errors !== [] ? ' class="gk-import-badrow"' : '') . '><th scope="row" class="gk-import-rowno">' . $e(self::num($row)) . '</th>';
            if ($errors === []) {
                $html .= '<td class="gk-import-state"><span class="material-icons" aria-hidden="true">check_circle</span>' . $e(Lang::t('import.ok')) . '</td>';
            } else {
                $count = count($errors);
                $html .= '<td class="gk-import-state"><span class="material-icons" aria-hidden="true">error</span>'
                    . $e($count === 1 ? Lang::t('import.row_errors_one') : Lang::t('import.row_errors_n', ['n' => self::num($count)]));
                foreach ($other as $message) $html .= '<span class="gk-import-msg">' . $e($message) . '</span>';
                $html .= '</td>';
            }
            foreach ($r['columns'] as $i => $_) {
                $cls = [];
                if (($r['mapping'][$i] ?? null) === null) $cls[] = 'gk-import-ignored';
                if (isset($byColumn[$i])) $cls[] = 'gk-import-bad';
                $html .= '<td' . ($cls !== [] ? ' class="' . implode(' ', $cls) . '"' : '') . '>' . $e(self::cut($cells[$i] ?? '', 80));
                if (isset($byColumn[$i])) {
                    $html .= '<span class="gk-import-msg"><span class="material-icons" aria-hidden="true">error</span>' . $e($byColumn[$i]) . '</span>';
                }
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table></div>';
    }

    /**
     * Every row that will be left out, with its row number, field, message and the value — the rows
     * the preview does not show included (the first 100; then how many more).
     *
     * @param array<string,mixed> $r
     * @param array<int,list<string>> $rows
     */
    private function errorsHtml(array $r, array $rows): string
    {
        if ($r['errors'] === [] || $r['missing'] !== []) return '';
        $e = self::escaper();
        $base = 'gk-import-' . $this->name;
        $html = '<div class="gk-import-errors"><p class="gk-import-errors-title" id="' . $e($base . '-errors') . '">'
            . $e(Lang::t('import.errors', ['n' => self::num(count($r['errors']))])) . '</p>'
            . '<ul aria-labelledby="' . $e($base . '-errors') . '">';
        $n = 0;
        foreach ($r['errors'] as $row => $errors) {
            if (++$n > 100) break;
            foreach ($errors as $key => $message) {
                $col = array_search($key, $r['mapping'], true);
                $value = is_int($col) ? self::cut(self::trim($rows[$row][$col] ?? ''), 40) : '';
                $html .= '<li><strong>' . $e(Lang::t('import.row_n', ['row' => self::num($row)])) . '</strong> · '
                    . $e($this->fields[$key]['label']) . ': ' . $e($message)
                    . ($value !== '' ? ' <q>' . $e($value) . '</q>' : '') . '</li>';
            }
        }
        $html .= '</ul>';
        if (count($r['errors']) > 100) {
            $html .= '<p class="gk-import-more">' . $e(Lang::t('import.errors_more', ['n' => self::num(count($r['errors']) - 100)])) . '</p>';
        }
        return $html . '</div>';
    }

    /** @param array<string,mixed> $r */
    private function doneHtml(array $r): string
    {
        $text = $r['invalid'] > 0
            ? Lang::t('import.done_skipped', ['rows' => $this->rowsText($r['valid']), 'bad' => self::num($r['invalid'])])
            : Lang::t('import.done', ['rows' => $this->rowsText($r['valid'])]);
        return self::message('success', 'check_circle', $text, 'status', 'gk-import-summary')
            . '<div class="gk-import-actions">' . self::submit($this->name, 'new', Lang::t('import.new'), 'outlined', 'primary', 'upload_file') . '</div>';
    }

    /** @param array<string,mixed> $r */
    private function confirmText(array $r): string
    {
        $n = $r['valid'];
        if ($r['invalid'] === 0) return $n === 1 ? Lang::t('import.confirm_one') : Lang::t('import.confirm_n', ['n' => self::num($n)]);
        return $n === 1 ? Lang::t('import.confirm_valid_one') : Lang::t('import.confirm_valid_n', ['n' => self::num($n)]);
    }

    /** What the script needs: the fields, the limits, the words — so it checks what the server checks. */
    private function configJson(): string
    {
        $fields = [];
        foreach ($this->fields as $f) {
            $fields[] = ['key' => $f['key'], 'label' => $f['label'], 'type' => $f['type'], 'required' => $f['required'],
                         'unique' => $f['unique'], 'aliases' => $f['aliases'], 'options' => $f['options']];
        }
        $words = [];
        foreach (self::WORDS as $key) $words[$key] = Lang::t('import.' . $key);
        return (string) json_encode([
            'name' => $this->name,
            'fields' => $fields,
            'locale' => $this->localeFamily(),
            'maxRows' => $this->maxRows,
            'maxBytes' => $this->maxBytes,
            'maxColumns' => self::MAX_COLUMNS,
            'preview' => $this->previewRows,
            'delimiter' => $this->delimiter,
            'enclosure' => $this->enclosure,
            'encoding' => $this->encoding,
            'header' => $this->header,
            'decimal' => Lang::t('format.decimal'),
            'thousands' => Lang::t('format.thousands'),
            'stateless' => $this->storageDir === false,
            't' => $words,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /** The words the script writes; each is import.<key> in lang/. */
    private const WORDS = [
        'step_done_sr', 'pasted', 'options', 'delimiter', 'delim_semicolon', 'delim_comma', 'delim_tab', 'delim_pipe',
        'encoding', 'enc_utf8', 'enc_windows1252', 'enc_utf16le', 'enc_utf16be', 'header', 'map', 'column', 'column_n',
        'ignore', 'required_mark', 'sample', 'empty_column', 'missing', 'twice', 'preview', 'preview_all', 'row', 'row_n',
        'status', 'ok', 'row_errors_one', 'row_errors_n', 'ignored', 'rows_one', 'rows_n', 'summary_ok', 'summary_errors',
        'summary_none', 'errors', 'errors_more', 'confirm_one', 'confirm_n', 'confirm_valid_one', 'confirm_valid_n',
        'skip_note', 'cancel', 'size_bytes', 'size_kb', 'size_mb', 'err_size', 'err_rows', 'err_columns', 'err_binary', 'err_utf8',
        'unclosed', 'err_required', 'err_email', 'err_number', 'err_money', 'err_decimals', 'err_date', 'err_bool',
        'err_option', 'err_duplicate', 'err_read',
    ];

    // ── Small helpers ───────────────────────────────────────────────────────

    /** @return array<string,mixed>|null */
    private function parsed(): ?array
    {
        if ($this->source === null) return null;
        $key = $this->delimiter . '|' . $this->enclosure . '|' . $this->encoding . '|' . var_export($this->header, true);
        if ($this->parsedCache !== null && $this->parsedKey === $key) return $this->parsedCache;
        $names = [];
        foreach ($this->fields as $f) $names = array_merge($names, [$f['key'], $f['label']], $f['aliases']);
        $this->parsedKey = $key;
        return $this->parsedCache = self::parseCsv($this->source, [
            'delimiter' => $this->delimiter, 'enclosure' => $this->enclosure, 'encoding' => $this->encoding,
            'header' => $this->header, 'maxRows' => $this->maxRows, 'maxBytes' => $this->maxBytes, 'names' => $names,
        ]);
    }

    private function localeFamily(): string
    {
        return $this->locale ?? (strtolower(substr(Lang::locale(), 0, 2)) === 'de' ? 'de' : 'en');
    }

    private function sizeError(int $bytes): string
    {
        return Lang::t('import.err_size', ['size' => self::size($bytes), 'max' => self::size($this->maxBytes)]);
    }

    private function rowsText(int $n): string
    {
        return $n === 1 ? Lang::t('import.rows_one') : Lang::t('import.rows_n', ['n' => self::num($n)]);
    }

    /** 2,048 bytes → "2 KB", 5,242,880 → "5 MB", in the page's number shape. */
    private static function size(int $bytes): string
    {
        if ($bytes < 1024) return Lang::t('import.size_bytes', ['n' => self::num($bytes)]);
        if ($bytes < 1048576) return Lang::t('import.size_kb', ['n' => self::num((int) max(1, round($bytes / 1024)))]);
        $mb = round($bytes / 1048576, 1);
        $text = $mb == floor($mb) ? self::num((int) $mb) : str_replace('.', Lang::t('format.decimal'), sprintf('%.1F', $mb));
        return Lang::t('import.size_mb', ['n' => $text]);
    }

    private static function num(int $n): string
    {
        return number_format($n, 0, '', Lang::t('format.thousands'));
    }

    /** A value shortened for a line: at most $max characters, then an ellipsis. */
    private static function cut(string $s, int $max): string
    {
        $s = (string) preg_replace('/[ \t\n\r\x0B\f]+/', ' ', $s);
        if (preg_match_all('/./su', $s) <= $max) return $s;
        preg_match('/^.{' . ($max - 1) . '}/su', $s, $m);
        return rtrim($m[0]) . '…';
    }

    private static function message(string $tone, string $icon, string $text, string $role, string $extra = ''): string
    {
        $e = self::escaper();
        return '<div class="gk-message gk-message-' . $tone . ' gk-message-sm' . ($extra !== '' ? ' ' . $extra : '') . '"'
            . ($role !== '' ? ' role="' . $role . '"' : '') . '>'
            . '<span class="material-icons" aria-hidden="true">' . $icon . '</span>'
            . '<div class="gk-message-content">' . $e($text) . '</div></div>';
    }

    /** A submit button of the flow: the classes of Button::render(), with the step as name and value. */
    private static function submit(string $name, string $step, string $label, string $variant, string $color, string $icon, bool $disabled = false, string $extra = ''): string
    {
        $e = self::escaper();
        $iconHtml = '';
        if ($icon !== '') {
            // The font, not Icon::svg(): js/import.js draws the same buttons and has no SVG set.
            $iconHtml = '<span class="material-icons" style="font-size:18px" aria-hidden="true">' . $e($icon) . '</span>';
        }
        return '<button type="submit" class="gk-btn gk-btn-' . $variant . ' gk-btn-' . $color . '" name="' . $e($name) . '[step]" value="' . $step . '"'
            . ($disabled ? ' disabled' : '') . $extra . '>' . $iconHtml . '<span>' . $e($label) . '</span></button>';
    }

    private static function str(mixed $v): string
    {
        return is_string($v) ? $v : '';
    }

    private static function flag(mixed $value): ?bool
    {
        if (is_bool($value)) return $value;
        if ($value === 0 || $value === 1) return (bool) $value;
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['1', 'true', 'yes', 'on'], true)) return true;
            if (in_array($v, ['0', 'false', 'no', 'off', ''], true)) return false;
        }
        return null;
    }

    private static function shown(mixed $value): string
    {
        return is_scalar($value) || $value === null ? var_export($value, true) : get_debug_type($value);
    }

    private function warn(string $message): void
    {
        trigger_error("GridKit: import '{$this->name}': $message", E_USER_WARNING);
    }

    private static function escaper(): \Closure
    {
        return static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
