<?php
declare(strict_types=1);

namespace GridKit;

/**
 * FileManager — a media library and a receipt drawer: folders, thumbnails, a list, a details panel and
 * an upload of many files at once (1.125.0).
 *
 *   $fm = (new FileManager('media'))
 *       ->label('Media library')
 *       ->folders($folderRows)                         // id, name, parent
 *       ->files($fileRows, ['modified' => 'created_at'])
 *       ->path($_GET['folder'] ?? null)                // the folder on screen; the breadcrumb follows
 *       ->fromQuery()                                  // view, sort, type, search, the file whose details are open
 *       ->uploadUrl('/media/upload')
 *       ->actions(['delete' => '/media/delete', 'rename' => '/media/rename', 'move' => '/media/move'])
 *       ->accept(['image/*', 'application/pdf'])->maxSize('10M')->maxFiles(20)
 *       ->csrf($token);
 *   $fm->render();
 *
 *   // POST /media/upload — the server side, the part that matters:
 *   foreach (FileManager::receive($_FILES['files'] ?? [], $fm->rules() + ['target' => '/srv/media']) as $r) { … }
 *
 * Why it exists: every system among GridKit's users had a list of uploaded receipts or pictures of its own,
 * each with a different idea of what an upload may be. The listing is drawn from what the PAGE supplies —
 * the component never reads a directory and never deletes, renames or moves a file: it renders, and posts
 * to the page's URLs. The one thing it does with files is check an upload, in receive(), and that check
 * trusts nothing the browser said: not the name, not the type, not the size.
 *
 * Without script: links for the folders and the details, a GET form for search, type, sort and view, a
 * multipart form for the upload and a POST form with checkboxes for the bulk actions. js/file-manager.js —
 * a file of its own like js/tree.js, because the PawBot dashboard keeps its own gridkit.js — sorts and
 * filters in place, uploads with progress, opens the details beside the list and posts the actions.
 *
 * A wrong option is an E_USER_WARNING with a fallback that still renders, never an exception. A wrong value
 * in the request (a forged ?sort=) is not a mistake of the page: it falls back without a word.
 */
class FileManager
{
    /** The fields of a file row; files() maps the page's names onto them. */
    private const FILE_FIELDS = ['id', 'name', 'size', 'mime', 'modified', 'url', 'thumbUrl', 'folder', 'width', 'height'];

    /** The fields of a folder row. */
    private const FOLDER_FIELDS = ['id', 'name', 'parent', 'url', 'count'];

    private const VIEWS = ['grid', 'list'];
    private const SORTS = ['name', 'date', 'size', 'type'];
    private const TYPES = ['all', 'image', 'document', 'other'];
    private const SELECTS = ['none', 'single', 'multi'];
    private const ACTIONS = ['delete', 'rename', 'move', 'download', 'folder'];

    /** What may follow "sort=" in a link: a key and a direction. */
    private const SORT_KEYS = ['name-asc', 'name-desc', 'date-desc', 'date-asc', 'size-desc', 'size-asc', 'type-asc', 'type-desc'];

    private const DEFAULT_ACCEPT = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    private const DEFAULT_MAX_SIZE = 10485760;
    private const DEFAULT_MAX_FILES = 20;

    /**
     * The types receive() can tell apart, by what the file holds (finfo), and the names each may carry.
     * A file whose name does not end in one of its type's extensions is refused: a PNG named .jpg, or a
     * PDF named .png. The first extension is the one the stored name gets.
     */
    private const TYPES_EXT = [
        'image/jpeg'    => ['jpg', 'jpeg', 'jpe', 'jfif'],
        'image/png'     => ['png'],
        'image/gif'     => ['gif'],
        'image/webp'    => ['webp'],
        'image/avif'    => ['avif'],
        'image/bmp'     => ['bmp'],
        'image/x-ms-bmp' => ['bmp'],
        'image/tiff'    => ['tif', 'tiff'],
        'image/svg+xml' => ['svg'],
        'application/pdf' => ['pdf'],
        'text/plain'    => ['txt', 'csv', 'tsv', 'md', 'log'],
        'text/csv'      => ['csv'],
        'application/csv' => ['csv'],
        'text/rtf'      => ['rtf'],
        'application/rtf' => ['rtf'],
        'application/msword' => ['doc'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.ms-powerpoint' => ['ppt'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'application/vnd.oasis.opendocument.text' => ['odt'],
        'application/vnd.oasis.opendocument.spreadsheet' => ['ods'],
        'application/vnd.oasis.opendocument.presentation' => ['odp'],
        'application/zip' => ['zip'],
        'audio/mpeg'    => ['mp3'],
        'audio/ogg'     => ['ogg', 'oga'],
        'audio/wav'     => ['wav'],
        'audio/x-wav'   => ['wav'],
        'video/mp4'     => ['mp4', 'm4v'],
        'video/webm'    => ['webm'],
        'video/quicktime' => ['mov'],
    ];

    /** Never taken, whatever accept() says: what a browser or a server would run. */
    private const DENY_MIME = [
        'text/html', 'application/xhtml+xml', 'application/xml', 'text/xml',
        'text/x-php', 'application/x-php', 'application/x-httpd-php', 'application/x-httpd-php-source',
        'application/javascript', 'text/javascript', 'application/x-javascript', 'application/ecmascript',
        'text/x-shellscript', 'application/x-sh', 'text/x-perl', 'text/x-python', 'text/x-script.python',
        'application/x-executable', 'application/x-msdownload', 'application/x-dosexec', 'application/vnd.microsoft.portable-executable',
        'application/x-mach-binary', 'application/x-elf', 'application/x-sharedlib', 'application/java-archive', 'application/x-java-archive',
        'application/x-shockwave-flash', 'application/hta', 'application/x-msi',
    ];

    /**
     * Name parts that make a server or Windows run a file — refused anywhere in the name, so
     * "invoice.php.jpg" and "photo.phtml.png" never arrive (Apache with AddHandler runs the first).
     */
    private const EXEC_EXT = [
        'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'pht', 'phtm', 'phtml', 'phar', 'phps', 'pgif', 'inc',
        'shtml', 'shtm', 'stm', 'cgi', 'pl', 'pm', 'py', 'pyc', 'rb', 'sh', 'bash', 'zsh', 'ksh', 'csh',
        'asp', 'aspx', 'ascx', 'ashx', 'asmx', 'asa', 'cer', 'cfm', 'cfml', 'jsp', 'jspx', 'jsf', 'do', 'action',
        'exe', 'dll', 'com', 'bat', 'cmd', 'msi', 'msp', 'scr', 'pif', 'cpl', 'hta', 'vbs', 'vbe', 'wsf', 'wsh', 'ps1', 'psm1', 'jar', 'war',
        'js', 'mjs', 'cjs', 'jse', 'html', 'htm', 'xhtml', 'xht', 'svgz', 'swf', 'htaccess', 'htpasswd', 'ini', 'user', 'config',
    ];

    /** Words in a file that mean code: refused in any file (`<?php`), and in images and text the markup of a page too. */
    private const CODE_ANYWHERE = ['<?php'];
    private const CODE_IN_IMAGES = ['<?=', '<script', '<html', '<!doctype html', '<iframe', '<body', '<svg'];

    private string $id;
    /** @var list<array<string,mixed>> */
    private array $files = [];
    /** @var array<string,array<string,mixed>> */
    private array $folders = [];
    private ?string $current = null;
    /** @var list<array{name:string,url:string}>|null */
    private ?array $crumbs = null;
    private string $view = 'grid';
    private string $sortKey = 'name';
    private string $sortDir = 'asc';
    private string $type = 'all';
    private string $query = '';
    private string $select = 'multi';
    /** @var list<string> */
    private array $selected = [];
    private string $uploadUrl = '';
    /** @var array<string,string|true> */
    private array $actions = [];
    /** @var list<string> */
    private array $accept = self::DEFAULT_ACCEPT;
    private int $maxSize = self::DEFAULT_MAX_SIZE;
    private int $maxFiles = self::DEFAULT_MAX_FILES;
    private int $parallel = 3;
    private string $csrf = '';
    private string $csrfField = '_token';
    /** @var array<string,string> */
    private array $hidden = [];
    /** @var array<string,string> */
    private array $keep = [];
    private string $label = '';
    private ?string $open = null;
    private string $param = '';
    private string $anchor = '';

    public function __construct(string $id = 'files')
    {
        $this->id = trim($id) === '' ? 'files' : $id;
        if (trim($id) === '') $this->warn("the file manager needs an id — it is called 'files'.");
    }

    // ── What it shows ───────────────────────────────────────────────────────

    /**
     * The files of the listing — rows from a database, arrays or objects. $map renames the page's fields to
     * the manager's: id, name, size (bytes), mime, modified (a timestamp, a date string or a DateTime), url
     * (where the file is opened), thumbUrl (a small picture), folder (the folder id it lies in), width and
     * height (of an image, in pixels). Every field is read under its own name unless the map says otherwise.
     *
     * @param iterable<mixed> $rows
     * @param array<string,string> $map
     */
    public function files(iterable $rows, array $map = []): static
    {
        $map = $this->checkMap($map, self::FILE_FIELDS, 'files()');
        $seen = [];
        foreach ($this->files as $f) $seen[$f['id']] = true;
        foreach ($rows as $i => $row) {
            if (is_object($row)) $row = get_object_vars($row);
            if (!is_array($row)) {
                $this->warn("files(): row $i is " . get_debug_type($row) . ', not an array — it was left out.');
                continue;
            }
            $file = $this->fileRow($row, $map, "files() row $i");
            if ($file === null) continue;
            if (isset($seen[$file['id']])) {
                $this->warn("files(): the id '{$file['id']}' is there twice — the second was left out.");
                continue;
            }
            $seen[$file['id']] = true;
            $this->files[] = $file;
        }
        return $this;
    }

    /**
     * The folders: id, name, parent (the id of the folder it lies in; null or '' for the top), url (where a
     * click leads; default "?folder=<id>" on this page), count (what it holds, shown beside the name).
     *
     * @param iterable<mixed> $rows
     * @param array<string,string> $map
     */
    public function folders(iterable $rows, array $map = []): static
    {
        $map = $this->checkMap($map, self::FOLDER_FIELDS, 'folders()');
        foreach ($rows as $i => $row) {
            if (is_object($row)) $row = get_object_vars($row);
            if (!is_array($row)) {
                $this->warn("folders(): row $i is " . get_debug_type($row) . ', not an array — it was left out.');
                continue;
            }
            $get = static fn (string $f): mixed => $row[$map[$f] ?? $f] ?? null;
            $id = self::idOf($get('id'));
            $name = $get('name');
            if ($id === null) {
                $this->warn("folders(): row $i has no id — it was left out.");
                continue;
            }
            if (!is_scalar($name) || is_bool($name) || trim((string) $name) === '') {
                $this->warn("folders(): the folder '$id' has no name — it was left out.");
                continue;
            }
            if (isset($this->folders[$id])) {
                $this->warn("folders(): the id '$id' is there twice — the second was left out.");
                continue;
            }
            $url = $get('url');
            if ($url !== null && (!is_string($url) || self::unsafeUrl($url))) {
                $this->warn("folders(): the folder '$id' has a link that is not a safe URL — it links to ?folder= instead.");
                $url = null;
            }
            $count = $get('count');
            $this->folders[$id] = [
                'id'     => $id,
                'name'   => self::oneLine((string) $name),
                'parent' => self::idOf($get('parent')),
                'url'    => is_string($url) && trim($url) !== '' ? $url : null,
                'count'  => is_int($count) || (is_string($count) && ctype_digit($count)) ? (int) $count : null,
            ];
        }
        return $this;
    }

    /**
     * The folder on screen: its folders and files are listed, and the breadcrumb is its path through
     * folders() up to the top. null or '' — the top. An id folders() does not have is the top, with a warning.
     */
    public function path(string|int|null $folder): static
    {
        $id = self::idOf($folder);
        $this->current = $id;
        return $this;
    }

    /**
     * The breadcrumb, given as it is — for a page whose folders are not all in folders(): a list of
     * ['name' => 'Receipts', 'url' => '/media/12'], the top first, the current folder last (its url unused).
     *
     * @param list<array<string,mixed>> $crumbs
     */
    public function breadcrumb(array $crumbs): static
    {
        $out = [];
        foreach (array_values($crumbs) as $i => $c) {
            if (is_object($c)) $c = get_object_vars($c);
            $name = is_array($c) ? ($c['name'] ?? $c['label'] ?? null) : null;
            $url = is_array($c) ? ($c['url'] ?? $c['href'] ?? '') : '';
            if (!is_scalar($name) || is_bool($name) || trim((string) $name) === '') {
                $this->warn("breadcrumb(): crumb $i has no name — it was left out.");
                continue;
            }
            if (!is_string($url) || self::unsafeUrl($url)) {
                $this->warn("breadcrumb(): crumb $i has a link that is not a safe URL — it is shown without one.");
                $url = '';
            }
            $out[] = ['name' => self::oneLine((string) $name), 'url' => $url];
        }
        $this->crumbs = $out;
        return $this;
    }

    /** 'grid' (thumbnails, default) or 'list' (a row per file with type, size and date). */
    public function view(string $view): static
    {
        if (!in_array($view, self::VIEWS, true)) {
            $this->warn("view('$view') is not 'grid' or 'list' — it shows a grid.");
            $view = 'grid';
        }
        $this->view = $view;
        return $this;
    }

    /** The order: 'name' (default), 'date', 'size' or 'type'; 'asc' or 'desc'. Folders come first, by name. */
    public function sort(string $key, string $dir = 'asc'): static
    {
        if (!in_array($key, self::SORTS, true)) {
            $this->warn("sort('$key') is not name, date, size or type — it sorts by name.");
            $key = 'name';
        }
        $dir = strtolower($dir);
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $this->warn("sort() takes 'asc' or 'desc', not '$dir' — it is 'asc'.");
            $dir = 'asc';
        }
        $this->sortKey = $key;
        $this->sortDir = $dir;
        return $this;
    }

    /** Only one kind: 'all' (default), 'image', 'document' or 'other'. */
    public function filter(string $type): static
    {
        if (!in_array($type, self::TYPES, true)) {
            $this->warn("filter('$type') is not all, image, document or other — every file is shown.");
            $type = 'all';
        }
        $this->type = $type;
        return $this;
    }

    /** Only files and folders whose name holds this text (case and accents ignored). */
    public function search(string $text): static
    {
        $this->query = self::oneLine($text);
        return $this;
    }

    /**
     * Takes view, sort, type, q, folder and file from the request (default $_GET) — what the toolbar, the
     * folder links and the name links write without the script. A value that is not one it knows is ignored
     * without a word: the request is the visitor's, not the page's.
     *
     * @param array<string,mixed>|null $query
     */
    public function fromQuery(?array $query = null): static
    {
        $q = $query ?? $_GET;
        if ($this->param !== '') $q = is_array($q[$this->param] ?? null) ? $q[$this->param] : [];
        $str = static fn (string $k): ?string => isset($q[$k]) && is_string($q[$k]) ? $q[$k] : null;
        if (($v = $str('view')) !== null && in_array($v, self::VIEWS, true)) $this->view = $v;
        if (($v = $str('sort')) !== null && in_array($v, self::SORT_KEYS, true)) {
            [$this->sortKey, $this->sortDir] = explode('-', $v);
        }
        if (($v = $str('type')) !== null && in_array($v, self::TYPES, true)) $this->type = $v;
        if (($v = $str('q')) !== null) $this->query = self::oneLine(self::cut($v, 400));
        if (($v = $str('folder')) !== null) $this->current = self::idOf($v);
        if (($v = $str('file')) !== null && $v !== '') $this->open = $v;
        return $this;
    }

    /**
     * The request parameters of this manager under one name — ?media[folder]=2&media[view]=list — for a page
     * with two managers, or one whose own parameters are called sort or q. Default: none (?folder=2&view=list).
     */
    public function param(string $name): static
    {
        if ($name !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,31}$/', $name) !== 1) {
            $this->warn("param('$name') is not a parameter name (letters, digits, _) — the parameters stay as they are.");
            return $this;
        }
        $this->param = $name;
        return $this;
    }

    /** A fragment every link and the toolbar end with (#files), for a page that shows the manager in a tab or a section. */
    public function anchor(string $id): static
    {
        $id = ltrim($id, '#');
        if ($id !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_:.-]{0,63}$/', $id) !== 1) {
            $this->warn("anchor('$id') is not an id — the links have no fragment.");
            return $this;
        }
        $this->anchor = $id;
        return $this;
    }

    /** Request parameters every link and the toolbar keep, for a page that is itself reached by one (?page=media). */
    public function keep(array $params): static
    {
        foreach ($params as $k => $v) {
            $k = (string) $k;
            if ($k === $this->param || ($this->param === '' && in_array($k, ['view', 'sort', 'type', 'q', 'folder', 'file'], true))) {
                $this->warn("keep(): '$k' is the file manager's own parameter — it was ignored.");
                continue;
            }
            if (!is_scalar($v) || is_bool($v)) {
                $this->warn("keep(): '$k' is not text — it was ignored.");
                continue;
            }
            $this->keep[$k] = (string) $v;
        }
        return $this;
    }

    /** The accessible name of the manager; default the catalogue's "Files". The top of the breadcrumb says it too. */
    public function label(string $text): static
    {
        $this->label = self::oneLine($text);
        return $this;
    }

    // ── Selection and actions ───────────────────────────────────────────────

    /** 'none' (no checkboxes), 'single' (one file — a radio button) or 'multi' (default — checkboxes, select all). */
    public function select(string $mode): static
    {
        if (!in_array($mode, self::SELECTS, true)) {
            $this->warn("select('$mode') is not none, single or multi — it is multi.");
            $mode = 'multi';
        }
        $this->select = $mode;
        return $this;
    }

    /** Files ticked when the page opens. @param list<string|int> $ids */
    public function selected(array $ids): static
    {
        foreach ($ids as $id) {
            $id = self::idOf($id);
            if ($id !== null && !in_array($id, $this->selected, true)) $this->selected[] = $id;
        }
        return $this;
    }

    /** The details of this file are open when the page opens (fromQuery() reads ?file= as well). */
    public function open(string|int $id): static
    {
        $this->open = (string) $id;
        return $this;
    }

    /**
     * What the person can do, each a URL the manager POSTs to, or true — then only the event gk:filesaction
     * fires, and the page does the rest. delete, move, download (the ticked files), rename (in the details),
     * folder (a new folder in the current one). See GRIDKIT_SKILL.md for what each one posts and answers.
     *
     * @param array<string,string|bool> $actions
     */
    public function actions(array $actions): static
    {
        foreach ($actions as $name => $target) {
            $name = (string) $name;
            if (!in_array($name, self::ACTIONS, true)) {
                $this->warn("actions(): '$name' is not delete, rename, move, download or folder — it was ignored.");
                continue;
            }
            if ($target === false || $target === null) {
                unset($this->actions[$name]);
                continue;
            }
            if ($target === true) {
                $this->actions[$name] = true;
                continue;
            }
            if (!is_string($target) || trim($target) === '' || self::unsafeUrl($target)) {
                $this->warn("actions(): '$name' needs a URL or true — " . (is_string($target) && self::unsafeUrl($target) ? 'a ' . self::scheme($target) . ': URL' : 'this') . ' was ignored.');
                continue;
            }
            $this->actions[$name] = $target;
        }
        return $this;
    }

    // ── Upload ──────────────────────────────────────────────────────────────

    /** Where uploads are posted (multipart, the field files[]). Without it there is no upload. */
    public function uploadUrl(string $url): static
    {
        if (trim($url) === '' || self::unsafeUrl($url)) {
            $this->warn('uploadUrl() needs a URL' . (self::unsafeUrl($url) ? ', not a ' . self::scheme($url) . ': one' : '') . ' — there is no upload.');
            $this->uploadUrl = '';
            return $this;
        }
        $this->uploadUrl = $url;
        return $this;
    }

    /**
     * What may be uploaded: MIME types and families ('image/*', 'application/pdf') or extensions ('.csv').
     * Default: JPEG, PNG, GIF, WebP and PDF. 'image/*' does not take SVG — name 'image/svg+xml' for that.
     *
     * @param list<string> $types
     */
    public function accept(array $types): static
    {
        $out = [];
        foreach ($types as $t) {
            $t = is_string($t) ? strtolower(trim($t)) : '';
            if (preg_match('~^(\.[a-z0-9]{1,10}|[a-z]+/(\*|[a-z0-9][a-z0-9.+-]*))$~', $t) !== 1) {
                $this->warn('accept(): ' . var_export($t, true) . " is neither a MIME type like 'image/png' nor an extension like '.pdf' — it was ignored.");
                continue;
            }
            if (in_array($t, self::DENY_MIME, true)) {
                $this->warn("accept(): '$t' is a type a browser or a server runs — it is never taken.");
                continue;
            }
            if ($t[0] === '.' && in_array(substr($t, 1), self::EXEC_EXT, true)) {
                $this->warn("accept(): '$t' names a file a server runs — it is never taken.");
                continue;
            }
            if (!in_array($t, $out, true)) $out[] = $t;
        }
        if ($out === []) {
            $this->warn('accept() was left with nothing — images and PDF are taken.');
            $out = self::DEFAULT_ACCEPT;
        }
        $this->accept = $out;
        return $this;
    }

    /** The most one file may hold: bytes, or '10M', '512K', '2 MB'. 1 KB to 2 GB; default 10 MB. */
    public function maxSize(int|string $size): static
    {
        $bytes = self::bytes($size);
        if ($bytes === null || $bytes < 1024 || $bytes > 2147483648) {
            $this->warn('maxSize(' . var_export($size, true) . ') is not a size from 1 KB to 2 GB — it is 10 MB.');
            $bytes = self::DEFAULT_MAX_SIZE;
        }
        $this->maxSize = $bytes;
        return $this;
    }

    /** The most files one upload takes; 1 to 1000, default 20. */
    public function maxFiles(int $count): static
    {
        $this->maxFiles = $this->intIn($count, 1, 1000, self::DEFAULT_MAX_FILES, 'maxFiles()');
        return $this;
    }

    /** How many files the script sends at the same time; 1 to 6, default 3. */
    public function parallel(int $count): static
    {
        $this->parallel = $this->intIn($count, 1, 6, 3, 'parallel()');
        return $this;
    }

    /** The page's CSRF token: a hidden field ($field, default _token) in every form and the X-CSRF-Token header of every request the script sends. */
    public function csrf(string $token, string $field = '_token'): static
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,63}$/', $field) !== 1) {
            $this->warn("csrf(): '$field' is not a field name — the token is posted as _token.");
            $field = '_token';
        }
        $this->csrf = $token;
        $this->csrfField = $field;
        return $this;
    }

    /** Hidden fields posted with every upload and action. @param array<string,mixed> $fields */
    public function hidden(array $fields): static
    {
        foreach ($fields as $k => $v) {
            $k = (string) $k;
            if (in_array($k, ['action', 'ids', 'target', 'name', 'parent', 'folder', 'files'], true)) {
                $this->warn("hidden(): '$k' is a field the manager posts itself — it was ignored.");
                continue;
            }
            if (!is_scalar($v) || is_bool($v)) {
                $this->warn("hidden(): '$k' is not text — it was ignored.");
                continue;
            }
            $this->hidden[$k] = (string) $v;
        }
        return $this;
    }

    /**
     * What receive() must check so the server takes what the browser was told: ['accept' => …,
     * 'maxSize' => …, 'maxFiles' => …]. Add 'target' and the rest: FileManager::receive($_FILES['files'], $fm->rules() + [...]).
     *
     * @return array{accept:list<string>,maxSize:int,maxFiles:int}
     */
    public function rules(): array
    {
        return ['accept' => $this->accept, 'maxSize' => $this->maxSize, 'maxFiles' => $this->maxFiles];
    }

    // ── Rendering ───────────────────────────────────────────────────────────

    /** Prints the manager. */
    public function render(): void
    {
        echo $this->html();
    }

    /** The string twin of render(). */
    public function html(): string
    {
        $e = self::escaper();
        $key = self::key($this->id);
        $base = 'gk-files-' . $key;
        $label = $this->label !== '' ? $this->label : Lang::t('files.label');
        if ($this->current !== null && $this->folders !== [] && !isset($this->folders[$this->current])) {
            $this->warn("path(): the folder '{$this->current}' is not in folders() — the top is shown.");
            $this->current = null;
        }
        [$folders, $files] = $this->listing();
        $openFile = null;
        if ($this->open !== null) {
            foreach ($this->files as $f) {
                if ($f['id'] === $this->open) $openFile = $f;
            }
        }

        $html = '<section class="gk-files gk-files-view-' . $e($this->view) . '" id="' . $e($base) . '" data-gk-files="' . $e($this->id) . '"'
            . ' aria-label="' . $e($label) . '">';
        $html .= '<script type="application/json" data-gk-files-config>' . $this->configJson($key) . '</script>';
        $html .= '<div class="gk-files-top">' . $this->crumbsHtml($label) . $this->newFolderHtml($base) . '</div>';
        $html .= $this->toolbarHtml($base);
        if ($this->uploadUrl !== '') $html .= $this->uploadHtml($base);
        $html .= '<div class="gk-files-main">';
        $html .= $this->bodyHtml($base, $folders, $files);
        $html .= $this->detailsHtml($base, $openFile);
        $html .= '</div>';
        $html .= '<p class="gk-sr-only" aria-live="polite" data-gk-files-live></p>';
        return $html . '</section>';
    }

    /** The folders and files of the current folder, filtered and sorted. @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>} */
    private function listing(): array
    {
        $needle = self::fold($this->query);
        $folders = [];
        foreach ($this->folders as $f) {
            if ($f['parent'] !== $this->current) continue;
            if ($needle !== '' && !str_contains(self::fold($f['name']), $needle)) continue;
            $folders[] = $f;
        }
        usort($folders, static fn (array $a, array $b): int => strnatcmp(self::fold($a['name']), self::fold($b['name'])));

        $useFolders = $this->folders !== [] || $this->current !== null;
        $files = [];
        foreach ($this->files as $f) {
            if ($useFolders && $f['folder'] !== $this->current) continue;
            if ($this->type !== 'all' && $f['kind'] !== $this->type) continue;
            if ($needle !== '' && !str_contains(self::fold($f['name']), $needle)) continue;
            $files[] = $f;
        }
        $key = $this->sortKey;
        $sign = $this->sortDir === 'desc' ? -1 : 1;
        usort($files, static function (array $a, array $b) use ($key, $sign): int {
            $c = match ($key) {
                'date' => ($a['modified'] ?? 0) <=> ($b['modified'] ?? 0),
                'size' => ($a['size'] ?? 0) <=> ($b['size'] ?? 0),
                'type' => strcmp($a['ext'], $b['ext']),
                default => 0,
            };
            if ($c === 0) $c = strnatcmp(self::fold($a['name']), self::fold($b['name']));
            if ($c === 0) $c = strcmp($a['id'], $b['id']);
            return $c * $sign;
        });
        return [$folders, $files];
    }

    private function crumbsHtml(string $label): string
    {
        $e = self::escaper();
        $crumbs = $this->crumbs;
        if ($crumbs === null) {
            if ($this->folders === [] && $this->current === null) return '';
            $crumbs = [];
            $seen = [];
            for ($id = $this->current; $id !== null && isset($this->folders[$id]) && !isset($seen[$id]); $id = $this->folders[$id]['parent']) {
                $seen[$id] = true;
                array_unshift($crumbs, ['name' => $this->folders[$id]['name'], 'url' => $this->folderLink($id)]);
            }
            array_unshift($crumbs, ['name' => $label, 'url' => $this->link(['folder' => null])]);
        }
        if ($crumbs === []) return '';
        $html = '<nav class="gk-files-crumbs" aria-label="' . $e(Lang::t('files.path')) . '"><ol>';
        $last = count($crumbs) - 1;
        foreach ($crumbs as $i => $c) {
            if ($i === $last) {
                $html .= '<li><span aria-current="page">' . $e($c['name']) . '</span></li>';
            } elseif ($c['url'] !== '') {
                $html .= '<li><a href="' . $e($c['url']) . '">' . $e($c['name']) . '</a></li>';
            } else {
                $html .= '<li><span>' . $e($c['name']) . '</span></li>';
            }
        }
        return $html . '</ol></nav>';
    }

    private function newFolderHtml(string $base): string
    {
        if (!isset($this->actions['folder'])) return '';
        $e = self::escaper();
        $target = $this->actions['folder'];
        $html = '<details class="gk-files-newfolder"' . ($target === true ? ' hidden data-gk-files-needs-js' : '') . '>'
            . '<summary class="gk-btn gk-btn-outlined gk-btn-primary gk-btn-sm"><span class="material-icons" aria-hidden="true">create_new_folder</span>'
            . $e(Lang::t('files.new_folder')) . '</summary>'
            . '<form method="post" action="' . $e($target === true ? '' : $target) . '" data-gk-files-form="folder">'
            . $this->hiddenFields() . '<input type="hidden" name="action" value="folder">'
            . '<input type="hidden" name="parent" value="' . $e($this->current ?? '') . '">'
            . '<label class="gk-files-field"><span>' . $e(Lang::t('files.folder_name')) . '</span>'
            . '<input type="text" name="name" id="' . $e($base . '-newfolder') . '" required maxlength="120" autocomplete="off"></label>'
            . '<button type="submit" class="gk-btn gk-btn-filled gk-btn-primary gk-btn-sm">' . $e(Lang::t('files.create')) . '</button>'
            . '</form></details>';
        return $html;
    }

    private function toolbarHtml(string $base): string
    {
        $e = self::escaper();
        $n = fn (string $k): string => $e($this->param !== '' ? $this->param . '[' . $k . ']' : $k);
        $html = '<form class="gk-files-toolbar" method="get" action="' . $e($this->anchor !== '' ? '#' . $this->anchor : '') . '" role="search" aria-label="' . $e(Lang::t('files.find')) . '">';
        foreach ($this->keep as $k => $v) {
            $html .= '<input type="hidden" name="' . $e($k) . '" value="' . $e($v) . '">';
        }
        if ($this->current !== null) $html .= '<input type="hidden" name="' . $n('folder') . '" value="' . $e($this->current) . '">';
        $html .= '<label class="gk-files-search"><span class="material-icons" aria-hidden="true">search</span>'
            . '<span class="gk-sr-only">' . $e(Lang::t('files.search')) . '</span>'
            . '<input type="search" name="' . $n('q') . '" value="' . $e($this->query) . '" placeholder="' . $e(Lang::t('files.search')) . '"'
            . ' autocomplete="off" data-gk-files-q></label>';
        $html .= '<label class="gk-files-field"><span>' . $e(Lang::t('files.type')) . '</span><select name="' . $n('type') . '" data-gk-files-type>';
        foreach (self::TYPES as $t) {
            $html .= '<option value="' . $t . '"' . ($t === $this->type ? ' selected' : '') . '>' . $e(Lang::t('files.type_' . $t)) . '</option>';
        }
        $html .= '</select></label>';
        $html .= '<label class="gk-files-field"><span>' . $e(Lang::t('files.sort')) . '</span><select name="' . $n('sort') . '" data-gk-files-sort>';
        $cur = $this->sortKey . '-' . $this->sortDir;
        foreach (self::SORT_KEYS as $s) {
            $html .= '<option value="' . $s . '"' . ($s === $cur ? ' selected' : '') . '>' . $e(Lang::t('files.sort_' . str_replace('-', '_', $s))) . '</option>';
        }
        $html .= '</select></label>';
        $html .= '<fieldset class="gk-files-views"><legend class="gk-sr-only">' . $e(Lang::t('files.view')) . '</legend>';
        foreach (self::VIEWS as $v) {
            $html .= '<label class="gk-files-viewbtn"><input type="radio" name="' . $n('view') . '" value="' . $v . '"' . ($v === $this->view ? ' checked' : '')
                . ' data-gk-files-view><span class="material-icons" aria-hidden="true">' . ($v === 'grid' ? 'grid_view' : 'view_list') . '</span>'
                . '<span class="gk-files-viewword">' . $e(Lang::t('files.view_' . $v)) . '</span></label>';
        }
        $html .= '</fieldset>';
        $html .= '<button type="submit" class="gk-btn gk-btn-tonal gk-btn-primary gk-btn-sm gk-files-apply">' . $e(Lang::t('files.apply')) . '</button>';
        return $html . '</form>';
    }

    private function uploadHtml(string $base): string
    {
        $e = self::escaper();
        $accept = implode(',', $this->accept);
        $html = '<form class="gk-files-upload" method="post" enctype="multipart/form-data" action="' . $e($this->uploadUrl) . '"'
            . ' aria-label="' . $e(Lang::t('files.upload')) . '">';
        $html .= $this->hiddenFields();
        $html .= '<input type="hidden" name="folder" value="' . $e($this->current ?? '') . '">';
        $html .= '<div class="gk-files-drop">'
            . '<span class="material-icons gk-files-drop-icon" aria-hidden="true">cloud_upload</span>'
            . '<span class="gk-files-drop-text">' . $e(Lang::t('files.drop')) . '</span>'
            . '<label class="gk-files-pick"><span class="material-icons" aria-hidden="true">attach_file</span>'
            . '<span>' . $e(Lang::t('files.choose')) . '</span>'
            . '<input type="file" name="files[]" id="' . $e($base . '-input') . '" multiple accept="' . $e($accept) . '"'
            . ' aria-describedby="' . $e($base . '-hint') . '"></label>'
            . '<span class="gk-files-hint" id="' . $e($base . '-hint') . '">' . $e($this->hintText()) . '</span>'
            . '<button type="submit" class="gk-btn gk-btn-filled gk-btn-primary gk-btn-sm gk-files-send">' . $e(Lang::t('files.upload')) . '</button>'
            . '</div>';
        $html .= '<ul class="gk-files-queue" aria-label="' . $e(Lang::t('files.queue')) . '" hidden></ul>';
        return $html . '</form>';
    }

    /** "JPEG, PNG, GIF, WebP and PDF, up to 10 MB each, 20 files at once." */
    private function hintText(): string
    {
        $names = [];
        foreach ($this->accept as $t) {
            $names[] = match (true) {
                $t === 'image/*' => Lang::t('files.kind_images'),
                $t === 'application/pdf' => 'PDF',
                $t === 'image/svg+xml' => 'SVG',
                $t === 'image/jpeg' => 'JPEG',
                $t === 'image/webp' => 'WebP',
                $t[0] === '.' => strtoupper(substr($t, 1)),
                str_ends_with($t, '/*') => ucfirst(substr($t, 0, -2)),
                default => strtoupper((string) preg_replace('/^.*[\/.+-]/', '', $t)),
            };
        }
        $names = array_values(array_unique($names));
        $last = array_pop($names);
        $types = $names === [] ? (string) $last : implode(', ', $names) . ' ' . Lang::t('files.and') . ' ' . $last;
        return Lang::t('files.hint', ['types' => $types, 'size' => self::size($this->maxSize), 'count' => $this->maxFiles]);
    }

    /**
     * @param list<array<string,mixed>> $folders
     * @param list<array<string,mixed>> $files
     */
    private function bodyHtml(string $base, array $folders, array $files): string
    {
        $e = self::escaper();
        $urlActions = array_filter($this->actions, static fn ($t): bool => is_string($t));
        unset($urlActions['rename'], $urlActions['folder']);
        $first = $urlActions === [] ? '' : (string) reset($urlActions);
        $html = '<form class="gk-files-body" method="post" action="' . $e($first) . '" id="' . $e($base . '-form') . '">';
        $html .= $this->hiddenFields() . '<input type="hidden" name="folder" value="' . $e($this->current ?? '') . '">';
        $html .= $this->bulkHtml($base);
        $count = count($files);
        $summary = Lang::t($count === 1 ? 'files.count_one' : 'files.count_n', ['n' => $count]);
        if ($folders !== []) $summary .= ' · ' . Lang::t(count($folders) === 1 ? 'files.folders_one' : 'files.folders_n', ['n' => count($folders)]);
        $html .= '<p class="gk-files-summary" data-gk-files-summary>' . $e($summary) . '</p>';
        $html .= '<div class="gk-files-head" aria-hidden="true">'
            . '<span></span><span>' . $e(Lang::t('files.col_name')) . '</span><span>' . $e(Lang::t('files.col_type')) . '</span>'
            . '<span>' . $e(Lang::t('files.col_size')) . '</span><span>' . $e(Lang::t('files.col_date')) . '</span></div>';
        $html .= '<ul class="gk-files-items" aria-label="' . $e(Lang::t('files.items')) . '">';
        foreach ($folders as $f) $html .= $this->folderHtml($f);
        foreach ($files as $f) $html .= $this->itemHtml($base, $f);
        $html .= '</ul>';
        $empty = $folders === [] && $files === [];
        $emptyText = ($this->query !== '' || $this->type !== 'all') ? Lang::t('files.no_match') : Lang::t('files.empty');
        $html .= '<p class="gk-files-empty"' . ($empty ? '' : ' hidden') . '>' . $e($emptyText) . '</p>';
        return $html . '</form>';
    }

    private function bulkHtml(string $base): string
    {
        if ($this->select === 'none') return '';
        $e = self::escaper();
        $html = '<div class="gk-files-bulk" role="group" aria-label="' . $e(Lang::t('files.bulk')) . '">';
        if ($this->select === 'multi') {
            $html .= '<label class="gk-files-all" hidden><input type="checkbox" data-gk-files-all><span>' . $e(Lang::t('files.select_all')) . '</span></label>';
        }
        $ticked = count(array_filter($this->files, fn (array $f): bool => in_array($f['id'], $this->selected, true)));
        $html .= '<span class="gk-files-count" data-gk-files-count>' . $e(Lang::t('files.selected_n', ['n' => $ticked])) . '</span>';
        $icons = ['download' => 'download', 'move' => 'drive_file_move', 'delete' => 'delete'];
        foreach (['download', 'move', 'delete'] as $name) {
            if (!isset($this->actions[$name])) continue;
            $target = $this->actions[$name];
            $needsJs = $target === true ? ' hidden data-gk-files-needs-js' : '';
            if ($name === 'move') {
                $html .= '<span class="gk-files-move"' . $needsJs . '><label class="gk-files-field"><span>' . $e(Lang::t('files.move_to')) . '</span>'
                    . '<select name="target" data-gk-files-target>' . $this->folderOptions() . '</select></label>';
            }
            $color = $name === 'delete' ? ' gk-btn-danger' : ' gk-btn-primary';
            $html .= '<button type="submit" class="gk-btn gk-btn-outlined' . $color . ' gk-btn-sm gk-files-act" name="action" value="' . $name . '"'
                . ($target === true ? '' : ' formaction="' . $e($target) . '"') . ($name === 'move' ? '' : $needsJs)
                . ' data-gk-files-action="' . $name . '"><span class="material-icons" aria-hidden="true">' . $icons[$name] . '</span>'
                . '<span>' . $e(Lang::t('files.act_' . $name)) . '</span></button>';
            if ($name === 'move') $html .= '</span>';
        }
        return $html . '</div>';
    }

    /** Every folder as an option of the "Move to" select, indented by its depth; the top first. */
    private function folderOptions(): string
    {
        $e = self::escaper();
        $html = '<option value="">' . $e($this->label !== '' ? $this->label : Lang::t('files.label')) . '</option>';
        $children = [];
        foreach ($this->folders as $f) {
            $p = $f['parent'] !== null && isset($this->folders[$f['parent']]) ? $f['parent'] : '';
            $children[$p][] = $f;
        }
        $walk = function (string $parent, int $depth, array $seen) use (&$walk, $children, $e): string {
            $out = '';
            $list = $children[$parent] ?? [];
            usort($list, static fn (array $a, array $b): int => strnatcmp(self::fold($a['name']), self::fold($b['name'])));
            foreach ($list as $f) {
                if (isset($seen[$f['id']])) continue;
                $out .= '<option value="' . $e($f['id']) . '">' . str_repeat("\u{00A0}\u{00A0}\u{00A0}", $depth + 1) . $e($f['name']) . '</option>';
                $out .= $walk($f['id'], $depth + 1, $seen + [$f['id'] => true]);
            }
            return $out;
        };
        return $html . $walk('', 0, ['' => true]);
    }

    /** @param array<string,mixed> $f */
    private function folderHtml(array $f): string
    {
        $e = self::escaper();
        $count = $f['count'] !== null ? '<span class="gk-files-meta">' . $e(Lang::t($f['count'] === 1 ? 'files.items_one' : 'files.items_n', ['n' => $f['count']])) . '</span>' : '';
        return '<li class="gk-files-item gk-files-folder" data-gk-folder="' . $e($f['id']) . '">'
            . '<span class="gk-files-thumb" aria-hidden="true"><span class="material-icons">folder</span></span>'
            . '<a class="gk-files-name" href="' . $e($this->folderLink($f['id'])) . '">'
            . '<span class="gk-sr-only">' . $e(Lang::t('files.folder')) . ' </span>' . $e($f['name']) . '</a>'
            . $count . '</li>';
    }

    /** @param array<string,mixed> $f */
    private function itemHtml(string $base, array $f): string
    {
        $e = self::escaper();
        $cid = $base . '-c-' . self::key($f['id']);
        $checked = in_array($f['id'], $this->selected, true) ? ' checked' : '';
        $html = '<li class="gk-files-item' . ($checked !== '' ? ' gk-files-selected' : '') . '" data-gk-file="' . $e($f['id']) . '" data-gk-kind="' . $f['kind'] . '">';
        if ($this->select !== 'none') {
            $html .= '<input type="' . ($this->select === 'single' ? 'radio' : 'checkbox') . '" class="gk-files-check" id="' . $e($cid) . '"'
                . ' name="ids[]" value="' . $e($f['id']) . '"' . $checked
                . ' aria-label="' . $e(Lang::t('files.select_one', ['name' => $f['name']])) . '">';
            $html .= '<label class="gk-files-thumb" for="' . $e($cid) . '" aria-hidden="true">' . $this->thumbInner($f) . '</label>';
        } else {
            $html .= '<span class="gk-files-thumb" aria-hidden="true">' . $this->thumbInner($f) . '</span>';
        }
        $html .= '<a class="gk-files-name" href="' . $e($this->link(['file' => $f['id']])) . '" title="' . $e($f['name']) . '">' . $e($f['name']) . '</a>';
        $html .= '<span class="gk-files-type">' . $e(self::typeWord($f)) . '</span>'
            . '<span class="gk-files-size">' . $e($f['size'] !== null ? self::size($f['size']) : '–') . '</span>'
            . '<span class="gk-files-date">' . $e($f['modified'] !== null ? self::date($f['modified']) : '–') . '</span>';
        return $html . '</li>';
    }

    /** @param array<string,mixed> $f */
    private function thumbInner(array $f): string
    {
        $e = self::escaper();
        $src = $f['thumbUrl'] ?? ($f['kind'] === 'image' ? $f['url'] : null);
        if ($src !== null) {
            return '<img src="' . $e($src) . '" alt="" loading="lazy" decoding="async">';
        }
        return '<span class="material-icons">' . self::kindIcon($f) . '</span>'
            . '<span class="gk-files-ext">' . $e(strtoupper($f['ext'])) . '</span>';
    }

    /** @param array<string,mixed>|null $f */
    private function detailsHtml(string $base, ?array $f): string
    {
        $e = self::escaper();
        $title = $base . '-dtitle';
        $html = '<aside class="gk-files-details" id="' . $e($base . '-details') . '" aria-labelledby="' . $e($title) . '"'
            . ' tabindex="-1"' . ($f === null ? ' hidden' : '') . '>';
        $html .= '<div class="gk-files-details-head"><h3 class="gk-files-details-title" id="' . $e($title) . '">' . $e($f['name'] ?? '') . '</h3>'
            . '<a class="gk-files-details-close" href="' . $e($this->link(['file' => null])) . '" role="button" aria-label="' . $e(Lang::t('files.close_details')) . '">'
            . '<span class="material-icons" aria-hidden="true">close</span></a></div>';
        if ($f === null) {
            return $html . '<div class="gk-files-details-body" data-gk-files-details></div></aside>';
        }
        $html .= '<div class="gk-files-details-body" data-gk-files-details>';
        $preview = $f['kind'] === 'image' && $f['url'] !== null
            ? '<img src="' . $e($f['url']) . '" alt="' . $e(Lang::t('files.preview_of', ['name' => $f['name']])) . '">'
            : '<span class="material-icons" aria-hidden="true">' . self::kindIcon($f) . '</span>';
        $html .= '<div class="gk-files-preview">' . $preview . '</div>';
        $html .= '<dl class="gk-files-props">';
        $html .= '<dt>' . $e(Lang::t('files.col_type')) . '</dt><dd>' . $e(self::typeWord($f) . ($f['mime'] !== '' ? ' (' . $f['mime'] . ')' : '')) . '</dd>';
        $html .= '<dt>' . $e(Lang::t('files.col_size')) . '</dt><dd>' . $e($f['size'] !== null ? self::size($f['size']) : '–') . '</dd>';
        if ($f['width'] !== null && $f['height'] !== null) {
            $html .= '<dt>' . $e(Lang::t('files.dimensions')) . '</dt><dd>' . $e(Lang::t('files.pixels', ['w' => self::num($f['width']), 'h' => self::num($f['height'])])) . '</dd>';
        }
        $html .= '<dt>' . $e(Lang::t('files.col_date')) . '</dt><dd>' . $e($f['modified'] !== null ? self::date($f['modified']) : '–') . '</dd>';
        $html .= '</dl>';
        if ($f['url'] !== null) {
            $html .= '<div class="gk-files-url"><label class="gk-files-field"><span>' . $e(Lang::t('files.url')) . '</span>'
                . '<input type="text" readonly value="' . $e($f['url']) . '"></label>'
                . '<div class="gk-files-details-actions">'
                . '<button type="button" class="gk-btn gk-btn-outlined gk-btn-primary gk-btn-sm gk-files-copy" hidden data-gk-files-copy>'
                . '<span class="material-icons" aria-hidden="true">content_copy</span><span>' . $e(Lang::t('files.copy')) . '</span></button>'
                . '<a class="gk-btn gk-btn-text gk-btn-primary gk-btn-sm" href="' . $e($f['url']) . '" target="_blank" rel="noopener">'
                . '<span class="material-icons" aria-hidden="true">open_in_new</span><span>' . $e(Lang::t('files.open')) . '</span></a></div></div>';
        }
        if (isset($this->actions['rename']) && is_string($this->actions['rename'])) {
            $html .= '<form class="gk-files-rename" method="post" action="' . $e($this->actions['rename']) . '" data-gk-files-form="rename">'
                . $this->hiddenFields() . '<input type="hidden" name="action" value="rename">'
                . '<input type="hidden" name="ids[]" value="' . $e($f['id']) . '">'
                . '<label class="gk-files-field"><span>' . $e(Lang::t('files.new_name')) . '</span>'
                . '<input type="text" name="name" value="' . $e($f['name']) . '" required maxlength="255" autocomplete="off"></label>'
                . '<button type="submit" class="gk-btn gk-btn-tonal gk-btn-primary gk-btn-sm">' . $e(Lang::t('files.act_rename')) . '</button></form>';
        }
        return $html . '</div></aside>';
    }

    private function hiddenFields(): string
    {
        $e = self::escaper();
        $html = '';
        if ($this->csrf !== '') $html .= '<input type="hidden" name="' . $e($this->csrfField) . '" value="' . $e($this->csrf) . '">';
        foreach ($this->hidden as $k => $v) {
            $html .= '<input type="hidden" name="' . $e($k) . '" value="' . $e($v) . '">';
        }
        return $html;
    }

    /** What js/file-manager.js reads. Every <, >, & and quote is escaped: no name can end the script element. */
    private function configJson(string $key): string
    {
        $files = [];
        foreach ($this->files as $f) {
            $files[] = [
                'id' => $f['id'], 'name' => $f['name'], 'size' => $f['size'], 'mime' => $f['mime'], 'ext' => $f['ext'],
                'kind' => $f['kind'], 'modified' => $f['modified'], 'url' => $f['url'], 'thumbUrl' => $f['thumbUrl'],
                'folder' => $f['folder'], 'width' => $f['width'], 'height' => $f['height'],
            ];
        }
        $folders = [];
        foreach ($this->folders as $f) {
            $folders[] = ['id' => $f['id'], 'name' => $f['name'], 'parent' => $f['parent'], 'url' => $this->folderLink($f['id'])];
        }
        $words = [];
        foreach (self::JS_WORDS as $w) $words[$w] = Lang::t('files.' . $w);
        $cfg = [
            'id'       => $this->id,
            'key'      => $key,
            'select'   => $this->select,
            'view'     => $this->view,
            'sort'     => $this->sortKey . '-' . $this->sortDir,
            'type'     => $this->type,
            'q'        => $this->query,
            'folder'   => $this->current,
            'useFolders' => $this->folders !== [] || $this->current !== null,
            'upload'   => $this->uploadUrl,
            'accept'   => $this->accept,
            'maxSize'  => $this->maxSize,
            'maxFiles' => $this->maxFiles,
            'parallel' => $this->parallel,
            'csrf'     => $this->csrf !== '' ? ['field' => $this->csrfField, 'token' => $this->csrf] : null,
            'hidden'   => (object) $this->hidden,
            'actions'  => (object) $this->actions,
            'fileLink' => $this->link(['file' => '__ID__']),
            'param'    => $this->param,
            'open'     => $this->open,
            'files'    => $files,
            'folders'  => $folders,
            'locale'   => ['decimal' => Lang::t('format.decimal'), 'thousands' => Lang::t('format.thousands'), 'date' => Lang::t('files.date_format')],
            't'        => $words,
        ];
        $json = json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return $json === false ? '{}' : $json;
    }

    /** The words the script needs, from files.* in lang/. */
    private const JS_WORDS = [
        'count_one', 'count_n', 'folders_one', 'folders_n', 'selected_n', 'select_one', 'empty', 'no_match',
        'size_bytes', 'size_kb', 'size_mb', 'size_gb', 'pixels', 'col_type', 'col_size', 'col_date', 'dimensions', 'url', 'copy', 'copied',
        'copy_failed', 'open', 'new_name', 'act_rename', 'preview_of', 'type_image', 'type_document', 'type_other', 'folder', 'items_one', 'items_n',
        'state_waiting', 'state_uploading', 'state_done', 'state_error', 'state_cancelled', 'cancel', 'retry', 'dismiss',
        'err_type', 'err_size', 'err_empty', 'err_count', 'err_http', 'err_network', 'err_answer', 'uploaded', 'failed', 'batch_done',
        'confirm_delete', 'deleted', 'moved', 'renamed', 'folder_created', 'action_failed', 'pasted', 'kind_pdf', 'kind_word', 'kind_sheet',
        'kind_slides', 'kind_text', 'kind_archive', 'kind_audio', 'kind_video', 'kind_image', 'kind_file', 'none_selected', 'drop_here',
        'shown_n', 'details_of',
    ];

    // ── Taking rows in ──────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $row
     * @param array<string,string> $map
     * @return array<string,mixed>|null
     */
    private function fileRow(array $row, array $map, string $where): ?array
    {
        $get = static fn (string $f): mixed => $row[$map[$f] ?? $f] ?? null;
        $id = self::idOf($get('id'));
        if ($id === null) {
            $this->warn("$where has no id — it was left out.");
            return null;
        }
        $name = $get('name');
        if (!is_scalar($name) || is_bool($name) || trim((string) $name) === '') {
            $this->warn("$where ('$id') has no name — it was left out.");
            return null;
        }
        $name = self::oneLine((string) $name);
        $size = $get('size');
        if ($size !== null && !(is_int($size) || (is_string($size) && ctype_digit($size)) || (is_float($size) && $size >= 0))) {
            $this->warn("$where ('$id') has a size that is not a number of bytes — it is not shown.");
            $size = null;
        }
        $mime = $get('mime');
        $mime = is_string($mime) && preg_match('~^[a-z0-9.+-]+/[a-z0-9.+-]+$~i', trim($mime)) ? strtolower(trim($mime)) : '';
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($mime === '' && $ext !== '') {
            foreach (self::TYPES_EXT as $m => $exts) {
                if (in_array($ext, $exts, true)) { $mime = $m; break; }
            }
        }
        $urls = [];
        foreach (['url', 'thumbUrl'] as $u) {
            $v = $get($u);
            if ($v !== null && $v !== '' && (!is_string($v) || (self::unsafeUrl($v) && !self::pictureData($v, $u === 'thumbUrl')))) {
                $this->warn("$where ('$id') has a $u that is not a safe URL — it was left out.");
                $v = null;
            }
            $urls[$u] = is_string($v) && trim($v) !== '' ? $v : null;
        }
        $dim = [];
        foreach (['width', 'height'] as $d) {
            $v = $get($d);
            $dim[$d] = is_int($v) && $v > 0 ? $v : (is_string($v) && ctype_digit($v) && (int) $v > 0 ? (int) $v : null);
        }
        return [
            'id'       => $id,
            'name'     => $name,
            'size'     => $size === null ? null : (int) $size,
            'mime'     => $mime,
            'ext'      => $ext,
            'kind'     => self::kindOf($mime),
            'modified' => self::timestamp($get('modified')),
            'url'      => $urls['url'],
            'thumbUrl' => $urls['thumbUrl'],
            'folder'   => self::idOf($get('folder')),
            'width'    => $dim['width'],
            'height'   => $dim['height'],
        ];
    }

    /**
     * @param array<string,mixed> $map
     * @param list<string> $fields
     * @return array<string,string>
     */
    private function checkMap(array $map, array $fields, string $where): array
    {
        $out = [];
        foreach ($map as $k => $v) {
            $k = (string) $k;
            if (!in_array($k, $fields, true)) {
                $this->warn("$where: the map names '$k', which is not one of " . implode(', ', $fields) . ' — it was ignored.');
                continue;
            }
            if (!is_string($v) && !is_int($v)) {
                $this->warn("$where: the map gives '$k' a column that is not a name — it was ignored.");
                continue;
            }
            $out[$k] = (string) $v;
        }
        return $out;
    }

    private function intIn(int $value, int $min, int $max, int $default, string $what): int
    {
        if ($value >= $min && $value <= $max) return $value;
        $this->warn("$what takes a whole number from $min to $max, not $value — it is $default.");
        return $default;
    }

    private function warn(string $message): void
    {
        trigger_error("GridKit: file manager '{$this->id}': $message", E_USER_WARNING);
    }

    /** A link to this page with the manager's state; null drops a parameter. @param array<string,?string> $params */
    private function link(array $params): string
    {
        $q = [];
        if ($this->current !== null) $q['folder'] = $this->current;
        if ($this->view !== 'grid') $q['view'] = $this->view;
        if ($this->sortKey . '-' . $this->sortDir !== 'name-asc') $q['sort'] = $this->sortKey . '-' . $this->sortDir;
        if ($this->type !== 'all') $q['type'] = $this->type;
        if ($this->query !== '') $q['q'] = $this->query;
        foreach ($params as $k => $v) {
            if ($v === null) unset($q[$k]);
            else $q[$k] = $v;
        }
        $all = $this->keep;
        if ($this->param !== '') {
            if ($q !== []) $all[$this->param] = $q;
        } else {
            $all += $q;
        }
        $s = http_build_query($all, '', '&', PHP_QUERY_RFC3986);
        return '?' . $s . ($this->anchor !== '' ? '#' . $this->anchor : '');
    }

    private function folderLink(string $id): string
    {
        $url = $this->folders[$id]['url'] ?? null;
        if ($url !== null) return $url;
        return $this->link(['folder' => $id, 'q' => null, 'file' => null]);
    }

    // ── The server side of an upload ────────────────────────────────────────

    /**
     * Checks what arrived in $_FILES['files'] (any shape: one file, files[], nested) and returns one result
     * per file — nothing is trusted that the browser said:
     *
     *   - only a file PHP received in this request (is_uploaded_file), with its error code in words;
     *   - the size of the file on disk, not the one claimed; 0 bytes is refused;
     *   - the type by what the file holds (finfo), never by the name or Content-Type; it must be accepted,
     *     its name must end in an extension of that type, and a type a browser or server runs is never taken;
     *   - a name with a NUL byte, a leading dot, or a part that runs (.php, .phtml, .phar, .htaccess …)
     *     anywhere — "invoice.php.jpg" — is refused;
     *   - an image must be readable as the type it claims, within maxWidth × maxHeight and maxPixels
     *     (a 50,000 × 50,000 PNG of 100 bytes is a decompression bomb);
     *   - PHP code in any file, and markup of a page in an image or text, is refused (a polyglot);
     *   - an SVG is taken only when accept() names image/svg+xml, and only without script, event handlers,
     *     foreign objects, entities or links to javascript: or data:;
     *   - the stored name is random (32 hex digits) plus the extension, checked against the type — the client's name never
     *     reaches the disk.
     *
     * Options: accept, maxSize, maxFiles (use $fm->rules()), maxWidth, maxHeight (default 20000), maxPixels
     * (default 50 million), target (a directory: the file is moved there; without it nothing is moved and
     * the result carries 'tmp'), public (true: target may lie inside the web root), webRoot (default
     * $_SERVER['DOCUMENT_ROOT']).
     *
     * A result: ok, index, name (the client's, cleaned, for display), size, mime, ext, kind, width, height,
     * stored, path (when moved), tmp, sha256, error (a code, null when ok), message (in words).
     *
     * @param array<string,mixed> $files
     * @param array<string,mixed> $opts
     * @return list<array<string,mixed>>
     */
    public static function receive(array $files, array $opts = []): array
    {
        return self::intake(
            $files,
            $opts,
            static fn (string $path): bool => is_uploaded_file($path),
            static fn (string $from, string $to): bool => move_uploaded_file($from, $to)
        );
    }

    /**
     * The checks of receive() for a file that is already on the server and was not uploaded in this request:
     * a file from an import directory, a mail attachment, a test. With 'target' it is COPIED there (the
     * original stays). Same options, same result.
     *
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    public static function check(string $path, string $name = '', array $opts = []): array
    {
        $entry = ['name' => $name !== '' ? $name : basename($path), 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => (int) @filesize($path)];
        $out = self::intake(
            $entry,
            ['maxFiles' => 1] + $opts,
            static fn (string $p): bool => is_file($p),
            static fn (string $from, string $to): bool => copy($from, $to)
        );
        return $out[0] ?? self::refused(0, $entry['name'], 'no_file', Lang::t('files.err_no_file'));
    }

    /**
     * The one place a file is let in. $isUploaded and $move are is_uploaded_file and move_uploaded_file in
     * receive() — the trusted path; check() passes is_file and copy. Private: no caller outside this class
     * can declare a file of its choosing "uploaded".
     *
     * @param array<string,mixed> $files
     * @param array<string,mixed> $opts
     * @return list<array<string,mixed>>
     */
    private static function intake(array $files, array $opts, callable $isUploaded, callable $move): array
    {
        $o = self::receiveOptions($opts);
        $entries = self::entries($files);
        $results = [];
        $count = 0;
        $targetOk = null;
        foreach ($entries as $i => $f) {
            $err = $f['error'];
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            $display = self::displayName($f['name']);
            $count++;
            if ($count > $o['maxFiles']) {
                $results[] = self::refused($i, $display, 'count', Lang::t('files.err_count', ['max' => $o['maxFiles']]));
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                [$code, $msg] = match ($err) {
                    UPLOAD_ERR_INI_SIZE  => ['ini_size', Lang::t('files.err_ini_size', ['name' => $display, 'max' => self::size(self::bytes((string) ini_get('upload_max_filesize')) ?? 0)])],
                    UPLOAD_ERR_FORM_SIZE => ['size', Lang::t('files.err_size', ['name' => $display, 'size' => '?', 'max' => self::size($o['maxSize'])])],
                    UPLOAD_ERR_PARTIAL   => ['partial', Lang::t('files.err_partial', ['name' => $display])],
                    default              => ['server', Lang::t('files.err_server', ['name' => $display, 'code' => $err])],
                };
                $results[] = self::refused($i, $display, $code, $msg);
                continue;
            }
            $tmp = $f['tmp_name'];
            if ($tmp === '' || str_contains($tmp, "\0") || !$isUploaded($tmp)) {
                $results[] = self::refused($i, $display, 'not_uploaded', Lang::t('files.err_not_uploaded', ['name' => $display]));
                continue;
            }
            $r = self::inspect($tmp, $f['name'], $display, $o);
            $r['index'] = $i;
            if ($r['ok'] && $o['target'] !== null) {
                $targetOk ??= self::targetDir($o);
                if ($targetOk === false) {
                    $r = ['tmp' => $tmp] + self::refused($i, $display, 'store', Lang::t('files.err_store', ['name' => $display]));
                } else {
                    $dest = $targetOk . DIRECTORY_SEPARATOR . $r['stored'];
                    if (!@$move($tmp, $dest)) {
                        $r = ['tmp' => $tmp] + self::refused($i, $display, 'store', Lang::t('files.err_store', ['name' => $display]));
                    } else {
                        @chmod($dest, $o['public'] ? 0644 : 0640);
                        $r['path'] = $dest;
                        $r['tmp'] = null;
                    }
                }
            }
            $results[] = $r;
        }
        return $results;
    }

    /** @param array<string,mixed> $opts @return array<string,mixed> */
    private static function receiveOptions(array $opts): array
    {
        $known = ['accept', 'maxSize', 'maxFiles', 'maxWidth', 'maxHeight', 'maxPixels', 'target', 'public', 'webRoot'];
        foreach (array_keys($opts) as $k) {
            if (!in_array((string) $k, $known, true)) {
                trigger_error("GridKit: FileManager::receive() does not know the option '$k' — it was ignored.", E_USER_WARNING);
            }
        }
        $int = static function (string $k, int $min, int $max, int $default) use ($opts): int {
            if (!array_key_exists($k, $opts)) return $default;
            $v = $k === 'maxSize' && is_string($opts[$k]) ? self::bytes($opts[$k]) : $opts[$k];
            if (is_int($v) && $v >= $min && $v <= $max) return $v;
            trigger_error("GridKit: FileManager::receive() takes '$k' from $min to $max — it is $default.", E_USER_WARNING);
            return $default;
        };
        $accept = self::DEFAULT_ACCEPT;
        if (array_key_exists('accept', $opts)) {
            $list = [];
            foreach ((array) $opts['accept'] as $t) {
                if (is_string($t) && trim($t) !== '') $list[] = strtolower(trim($t));
            }
            if ($list === []) {
                trigger_error("GridKit: FileManager::receive() was given no type to accept — images and PDF are taken.", E_USER_WARNING);
            } else {
                $accept = $list;
            }
        }
        $target = $opts['target'] ?? null;
        if ($target !== null && (!is_string($target) || trim($target) === '')) {
            trigger_error("GridKit: FileManager::receive() takes 'target' as a directory — nothing is stored.", E_USER_WARNING);
            $target = '';
        }
        return [
            'accept'    => $accept,
            'maxSize'   => $int('maxSize', 1, PHP_INT_MAX, self::DEFAULT_MAX_SIZE),
            'maxFiles'  => $int('maxFiles', 1, 1000, self::DEFAULT_MAX_FILES),
            'maxWidth'  => $int('maxWidth', 1, 100000, 20000),
            'maxHeight' => $int('maxHeight', 1, 100000, 20000),
            'maxPixels' => $int('maxPixels', 1, 1000000000, 50000000),
            'target'    => $target,
            'public'    => ($opts['public'] ?? false) === true,
            'webRoot'   => is_string($opts['webRoot'] ?? null) ? $opts['webRoot'] : (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''),
        ];
    }

    /**
     * The directory a file may be moved to, or false: it must exist and be writable, and it may lie inside
     * the web root only when 'public' => true says so — an upload folder the web server serves is where a
     * mistake elsewhere becomes code that runs.
     *
     * @param array<string,mixed> $o
     */
    private static function targetDir(array $o): string|false
    {
        $dir = $o['target'] === '' ? false : realpath($o['target']);
        if ($dir === false || !is_dir($dir) || !is_writable($dir)) {
            trigger_error('GridKit: FileManager::receive(): the target ' . var_export($o['target'], true) . ' is not a writable directory — nothing was stored.', E_USER_WARNING);
            return false;
        }
        $root = $o['webRoot'] !== '' ? realpath($o['webRoot']) : false;
        if ($root !== false && !$o['public'] && ($dir === $root || str_starts_with($dir . DIRECTORY_SEPARATOR, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
            trigger_error("GridKit: FileManager::receive(): the target '$dir' lies inside the web root — nothing was stored. Store uploads outside it, or pass 'public' => true.", E_USER_WARNING);
            return false;
        }
        return $dir;
    }

    /**
     * $_FILES in any of its shapes as a flat list of entries.
     *
     * @param array<string,mixed> $files
     * @return list<array{name:string,tmp_name:string,error:int,size:int}>
     */
    private static function entries(array $files): array
    {
        if ($files === []) return [];
        if (array_is_list($files)) {
            $out = [];
            foreach ($files as $f) {
                if (is_array($f)) array_push($out, ...self::entries($f));
            }
            return $out;
        }
        if (!array_key_exists('tmp_name', $files) && !array_key_exists('error', $files)) return [];
        $flat = static function (mixed $v) use (&$flat): array {
            if (!is_array($v)) return [$v];
            $out = [];
            foreach ($v as $x) array_push($out, ...$flat($x));
            return $out;
        };
        $names = $flat($files['name'] ?? '');
        $tmps = $flat($files['tmp_name'] ?? '');
        $errs = $flat($files['error'] ?? UPLOAD_ERR_NO_FILE);
        $sizes = $flat($files['size'] ?? 0);
        $out = [];
        foreach ($errs as $i => $err) {
            $out[] = [
                'name'     => is_string($names[$i] ?? null) ? $names[$i] : '',
                'tmp_name' => is_string($tmps[$i] ?? null) ? $tmps[$i] : '',
                'error'    => is_int($err) || (is_string($err) && ctype_digit($err)) ? (int) $err : UPLOAD_ERR_NO_FILE,
                'size'     => is_int($sizes[$i] ?? null) ? $sizes[$i] : 0,
            ];
        }
        return $out;
    }

    /**
     * Everything about one file on disk. $raw is the name the client sent; $display the cleaned one.
     *
     * @param array<string,mixed> $o
     * @return array<string,mixed>
     */
    private static function inspect(string $path, string $raw, string $display, array $o): array
    {
        if (str_contains($raw, "\0")) {
            return self::refused(0, $display, 'name', Lang::t('files.err_name', ['name' => $display]));
        }
        $base = self::baseName($raw);
        if ($base === '' || $base[0] === '.') {
            return self::refused(0, $display, 'name', Lang::t('files.err_name', ['name' => $display]));
        }
        $parts = explode('.', strtolower($base));
        array_shift($parts);
        foreach ($parts as $p) {
            if (in_array(trim($p), self::EXEC_EXT, true)) {
                return self::refused(0, $display, 'name', Lang::t('files.err_exec', ['name' => $display]));
            }
        }
        $ext = $parts === [] ? '' : (string) end($parts);

        clearstatcache(true, $path);
        $size = @filesize($path);
        if ($size === false || !is_readable($path)) {
            return self::refused(0, $display, 'unreadable', Lang::t('files.err_unreadable', ['name' => $display]));
        }
        if ($size === 0) {
            return self::refused(0, $display, 'empty', Lang::t('files.err_empty', ['name' => $display]));
        }
        if ($size > $o['maxSize']) {
            return self::refused(0, $display, 'size', Lang::t('files.err_size', ['name' => $display, 'size' => self::size($size), 'max' => self::size($o['maxSize'])]), $size);
        }
        if (!class_exists(\finfo::class)) {
            trigger_error('GridKit: FileManager::receive() needs the fileinfo extension to tell a file by its content — nothing is taken without it.', E_USER_WARNING);
            return self::refused(0, $display, 'type', Lang::t('files.err_type', ['name' => $display]), $size);
        }
        $mime = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($path));
        if (in_array($mime, self::DENY_MIME, true) || !isset(self::TYPES_EXT[$mime])) {
            return self::refused(0, $display, 'type', Lang::t('files.err_type', ['name' => $display]), $size, $mime);
        }
        if (!self::accepted($mime, $ext, $o['accept'])) {
            return self::refused(0, $display, 'type', Lang::t('files.err_type', ['name' => $display]), $size, $mime);
        }
        if (!in_array($ext, self::TYPES_EXT[$mime], true)) {
            return self::refused(0, $display, 'mismatch', Lang::t('files.err_mismatch', ['name' => $display, 'type' => self::mimeWord($mime)]), $size, $mime);
        }

        // Code where none belongs: PHP in any file; the markup of a page in an image or in text.
        $kind = self::kindOf($mime);
        $words = self::CODE_ANYWHERE;
        if ($kind === 'image' || str_starts_with($mime, 'text/')) $words = array_merge($words, self::CODE_IN_IMAGES);
        if ($mime === 'image/svg+xml') $words = self::CODE_ANYWHERE;
        if (self::holds($path, $words)) {
            return self::refused(0, $display, 'script', Lang::t('files.err_script', ['name' => $display]), $size, $mime);
        }

        $width = $height = null;
        if ($mime === 'image/svg+xml') {
            $bad = self::svgProblem((string) file_get_contents($path));
            if ($bad !== null) {
                return self::refused(0, $display, 'script', Lang::t('files.err_svg', ['name' => $display]), $size, $mime);
            }
        } elseif ($kind === 'image') {
            $info = @getimagesize($path);
            if (!is_array($info) || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
                return self::refused(0, $display, 'unreadable', Lang::t('files.err_image', ['name' => $display]), $size, $mime);
            }
            [$width, $height] = [(int) $info[0], (int) $info[1]];
            if ($width > $o['maxWidth'] || $height > $o['maxHeight'] || $width * $height > $o['maxPixels']) {
                return self::refused(0, $display, 'dimensions', Lang::t('files.err_dimensions', [
                    'name' => $display, 'w' => self::num($width), 'h' => self::num($height),
                    'max' => self::num((int) round($o['maxPixels'] / 1000000)),
                ]), $size, $mime);
            }
        }

        // The type's own extension: the one the name has (it is one of the type's), a JPEG always .jpg.
        $canon = $mime === 'image/jpeg' ? 'jpg' : $ext;
        return [
            'ok'      => true,
            'index'   => 0,
            'name'    => $display,
            'size'    => $size,
            'mime'    => $mime,
            'ext'     => $canon,
            'kind'    => $kind,
            'width'   => $width,
            'height'  => $height,
            'stored'  => bin2hex(random_bytes(16)) . '.' . $canon,
            'path'    => null,
            'tmp'     => $path,
            'sha256'  => (string) hash_file('sha256', $path),
            'error'   => null,
            'message' => Lang::t('files.accepted', ['name' => $display]),
        ];
    }

    /** @return array<string,mixed> */
    private static function refused(int $index, string $name, string $code, string $message, ?int $size = null, ?string $mime = null): array
    {
        return [
            'ok' => false, 'index' => $index, 'name' => $name, 'size' => $size, 'mime' => $mime, 'ext' => null, 'kind' => null,
            'width' => null, 'height' => null, 'stored' => null, 'path' => null, 'tmp' => null, 'sha256' => null,
            'error' => $code, 'message' => $message,
        ];
    }

    /** Is this type (by content) and extension among what the page accepts? @param list<string> $accept */
    private static function accepted(string $mime, string $ext, array $accept): bool
    {
        foreach ($accept as $a) {
            if ($a === $mime) return true;
            // image/* takes the raster images, never SVG: an SVG is a document that can hold script.
            if (str_ends_with($a, '/*') && str_starts_with($mime, substr($a, 0, -1)) && $mime !== 'image/svg+xml') return true;
            if ($a[0] === '.' && substr($a, 1) === $ext && $mime !== 'image/svg+xml') return true;
            if ($a === '.svg' && $mime === 'image/svg+xml' && $ext === 'svg') return true;
        }
        return false;
    }

    /** Does the file hold one of these words (case ignored)? Read in pieces, so a large file is no burden. @param list<string> $words */
    private static function holds(string $path, array $words): bool
    {
        $h = @fopen($path, 'rb');
        if ($h === false) return true;
        $tail = '';
        $keep = 0;
        foreach ($words as $w) $keep = max($keep, strlen($w));
        try {
            while (!feof($h)) {
                $chunk = fread($h, 1048576);
                if ($chunk === false) return true;
                $hay = strtolower($tail . $chunk);
                foreach ($words as $w) {
                    if (str_contains($hay, $w)) return true;
                }
                $tail = substr($hay, -$keep);
            }
        } finally {
            fclose($h);
        }
        return false;
    }

    /**
     * Why an SVG is not safe to show, or null. Parsed without the network and without entities; anything
     * that runs or loads is a reason: a DOCTYPE or entity, a processing instruction other than the XML
     * declaration, script, foreignObject, iframe, embed, object, an on… attribute, a link to javascript:,
     * vbscript: or data: other than a raster picture, a style that imports or runs.
     */
    private static function svgProblem(string $svg): ?string
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $svg)) return 'doctype';
        if (preg_match('/<\?(?!xml\s)/i', $svg)) return 'processing instruction';
        if (!class_exists(\DOMDocument::class)) return 'no dom';
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok || $doc->documentElement === null || strtolower($doc->documentElement->localName) !== 'svg') return 'not svg';
        $bad = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'handler', 'listener', 'audio', 'video', 'animate', 'set', 'animatetransform', 'animatemotion'];
        $xp = new \DOMXPath($doc);
        foreach ($xp->query('//*') ?: [] as $el) {
            if (!$el instanceof \DOMElement) continue;
            if (in_array(strtolower($el->localName), $bad, true)) return $el->localName;
            if (strtolower($el->localName) === 'style' && preg_match('/@import|javascript:|expression\s*\(|url\s*\(\s*["\']?\s*(?!#)/i', (string) $el->textContent)) return 'style';
            foreach ($el->attributes ?? [] as $attr) {
                $n = strtolower($attr->localName);
                $v = strtolower((string) preg_replace('/[\x00-\x20\x7f]+/', '', $attr->value));
                if (str_starts_with($n, 'on')) return 'handler';
                if (in_array($n, ['href', 'src', 'action', 'formaction', 'from', 'to', 'values'], true)
                    && preg_match('/^(javascript|vbscript):|^data:(?!image\/(png|jpe?g|gif|webp);)/', $v)) return 'link';
                if ($n === 'style' && preg_match('/javascript:|expression\(|@import/', $v)) return 'style';
            }
        }
        return null;
    }

    // ── Small helpers ───────────────────────────────────────────────────────

    /** The client's name, as it can be shown: no path, no control characters, at most 255 bytes, never empty. */
    private static function displayName(string $raw): string
    {
        $name = self::baseName($raw);
        if (preg_match('//u', $name) !== 1) {
            // Not UTF-8: Windows-1252 from an old browser, or bytes. Each byte above ASCII becomes a "?".
            $name = (string) preg_replace('/[\x80-\xff]/', '?', $name);
        }
        $name = (string) preg_replace('/[\x00-\x1f\x7f]/u', '', $name);
        $name = trim(self::cut($name, 255));
        return $name === '' ? Lang::t('files.unnamed') : $name;
    }

    /** The last part of a path, with either slash; trailing dots and spaces dropped (Windows drops them too: "x.php." is x.php). */
    private static function baseName(string $raw): string
    {
        $raw = str_replace('\\', '/', $raw);
        $pos = strrpos($raw, '/');
        $base = $pos === false ? $raw : substr($raw, $pos + 1);
        return rtrim($base, ". \t\n\r\0\x0B");
    }

    private static function kindOf(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) return 'image';
        if ($mime === 'application/pdf' || str_starts_with($mime, 'text/') || str_contains($mime, 'officedocument')
            || str_contains($mime, 'opendocument') || in_array($mime, ['application/msword', 'application/vnd.ms-excel',
                'application/vnd.ms-powerpoint', 'application/rtf', 'application/csv'], true)) return 'document';
        return 'other';
    }

    /** @param array<string,mixed> $f */
    private static function kindIcon(array $f): string
    {
        $m = (string) $f['mime'];
        return match (true) {
            $f['kind'] === 'image' => 'image',
            $m === 'application/pdf' => 'picture_as_pdf',
            str_contains($m, 'sheet') || str_contains($m, 'excel') || in_array($f['ext'], ['csv', 'tsv'], true) => 'table_chart',
            str_contains($m, 'presentation') || str_contains($m, 'powerpoint') => 'slideshow',
            str_starts_with($m, 'audio/') => 'audio_file',
            str_starts_with($m, 'video/') => 'video_file',
            $m === 'application/zip' => 'folder_zip',
            $f['kind'] === 'document' => 'description',
            default => 'insert_drive_file',
        };
    }

    /** "PDF document", "PNG image", "Spreadsheet" — the type in words. @param array<string,mixed> $f */
    private static function typeWord(array $f): string
    {
        $m = (string) $f['mime'];
        $ext = strtoupper((string) $f['ext']);
        return match (true) {
            $f['kind'] === 'image' => Lang::t('files.kind_image', ['ext' => $ext !== '' ? $ext : strtoupper(substr($m, 6))]),
            $m === 'application/pdf' => Lang::t('files.kind_pdf'),
            str_contains($m, 'sheet') || str_contains($m, 'excel') || $ext === 'CSV' => Lang::t('files.kind_sheet'),
            str_contains($m, 'presentation') || str_contains($m, 'powerpoint') => Lang::t('files.kind_slides'),
            str_contains($m, 'word') || str_contains($m, 'opendocument.text') || $m === 'application/rtf' => Lang::t('files.kind_word'),
            str_starts_with($m, 'text/') => Lang::t('files.kind_text'),
            str_starts_with($m, 'audio/') => Lang::t('files.kind_audio'),
            str_starts_with($m, 'video/') => Lang::t('files.kind_video'),
            $m === 'application/zip' => Lang::t('files.kind_archive'),
            default => Lang::t('files.kind_file', ['ext' => $ext !== '' ? $ext : '?']),
        };
    }

    private static function mimeWord(string $mime): string
    {
        return self::typeWord(['mime' => $mime, 'ext' => self::TYPES_EXT[$mime][0] ?? '', 'kind' => self::kindOf($mime)]);
    }

    /** An id as text, or null for none (null, '', false, an array). */
    private static function idOf(mixed $v): ?string
    {
        if (is_int($v) || (is_string($v) && trim($v) !== '')) return (string) $v;
        if (is_float($v)) return (string) $v;
        return null;
    }

    private static function timestamp(mixed $v): ?int
    {
        if ($v instanceof \DateTimeInterface) return $v->getTimestamp();
        if (is_int($v)) return $v;
        if (is_string($v) && trim($v) !== '') {
            if (ctype_digit($v)) return (int) $v;
            $t = strtotime($v);
            return $t === false ? null : $t;
        }
        return null;
    }

    private static function date(int $ts): string
    {
        return date(Lang::t('files.date_format'), $ts);
    }

    /** '10M', '512K', '2 MB', 1048576 → bytes; null when it is not a size. */
    private static function bytes(int|string $size): ?int
    {
        if (is_int($size)) return $size;
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*([kmg]?)b?\s*$/i', $size, $m) !== 1) return null;
        $n = (float) $m[1];
        $n *= match (strtolower($m[2])) { 'k' => 1024, 'm' => 1048576, 'g' => 1073741824, default => 1 };
        return (int) round($n);
    }

    /** 2,048 bytes → "2 KB", 5,242,880 → "5 MB", in the page's number shape. */
    private static function size(int $bytes): string
    {
        if ($bytes < 1024) return Lang::t('files.size_bytes', ['n' => self::num($bytes)]);
        if ($bytes < 1048576) return Lang::t('files.size_kb', ['n' => self::num((int) max(1, round($bytes / 1024)))]);
        if ($bytes < 1073741824) {
            $mb = round($bytes / 1048576, 1);
            $text = $mb == floor($mb) ? self::num((int) $mb) : str_replace('.', Lang::t('format.decimal'), sprintf('%.1F', $mb));
            return Lang::t('files.size_mb', ['n' => $text]);
        }
        $gb = round($bytes / 1073741824, 1);
        $text = $gb == floor($gb) ? self::num((int) $gb) : str_replace('.', Lang::t('format.decimal'), sprintf('%.1F', $gb));
        return Lang::t('files.size_gb', ['n' => $text]);
    }

    private static function num(int $n): string
    {
        return number_format($n, 0, '', Lang::t('format.thousands'));
    }

    private static function oneLine(string $s): string
    {
        return trim((string) (preg_replace('/[\x00-\x1f\x7f\s]+/u', ' ', $s) ?? $s));
    }

    /** At most $bytes bytes of UTF-8, never half a character. */
    private static function cut(string $s, int $bytes): string
    {
        if (strlen($s) <= $bytes) return $s;
        if (function_exists('mb_strcut')) return mb_strcut($s, 0, $bytes, 'UTF-8');
        $s = substr($s, 0, $bytes);
        return (string) preg_replace('/[\xC0-\xF7][\x80-\xBF]*$/', '', $s);
    }

    /** Lower case, accents folded, umlauts both ways reduced to the vowel: how a search meets a name. */
    private static function fold(string $s): string
    {
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
        $s = strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a',
            'â' => 'a', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c', 'ñ' => 'n']);
        return $s;
    }

    /**
     * A picture as a data: URL — the one data: a listing may show: a raster picture (PNG, JPEG, GIF, WebP)
     * anywhere, an SVG only as a thumbnail, which is only ever an <img> (no script runs in one).
     */
    private static function pictureData(string $url, bool $svg): bool
    {
        return preg_match('~^data:image/(png|jpeg|gif|webp' . ($svg ? '|svg\+xml' : '') . ')[;,]~i', self::bare($url)) === 1;
    }

    private static function unsafeUrl(string $url): bool
    {
        return preg_match('/^(javascript|vbscript|data):/i', self::bare($url)) === 1;
    }

    private static function scheme(string $url): string
    {
        return strtolower((string) strstr(self::bare($url), ':', true));
    }

    private static function bare(string $url): string
    {
        return (string) preg_replace('/[\x00-\x20\x7f]+/', '', $url);
    }

    /** An id as a piece of an HTML id: as it is when it is safe, else a short hash of it. */
    private static function key(string $id): string
    {
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) ? $id : 'h' . substr(md5($id), 0, 12);
    }

    private static function escaper(): \Closure
    {
        return static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
