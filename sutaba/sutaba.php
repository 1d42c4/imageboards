<?php
declare(strict_types=1);

/*
 * Sutaba
 *
 * SQLite3-only edition for PHP 8.5.8 and newer.
 * Images and thumbnails are stored in SQLite, so no upload directories are
 * required. Edit the configuration block below before deploying.
 */

const SUTABA_MINIMUM_PHP_VERSION_ID = 80508;
const SUTABA_MINIMUM_PHP_VERSION = '8.5.8';

if (PHP_VERSION_ID < SUTABA_MINIMUM_PHP_VERSION_ID) {
    http_response_code(500);
    exit('Sutaba requires PHP ' . SUTABA_MINIMUM_PHP_VERSION . ' or newer.');
}

foreach (['sqlite3', 'gd', 'mbstring', 'fileinfo'] as $extension) {
    if (!extension_loaded($extension)) {
        http_response_code(500);
        exit(sprintf('Sutaba requires the PHP %s extension.', $extension));
    }
}

date_default_timezone_set('America/New_York');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

$databasePathFromEnvironment = getenv('SUTABA_DATABASE_PATH');

// **************************** CONFIG ****************************
$config = [
    'database_path' => is_string($databasePathFromEnvironment) && $databasePathFromEnvironment !== ''
        ? $databasePathFromEnvironment
        : __DIR__ . '/sutaba.sqlite3',
    'boards' => [
        'board1' => [
            'title' => 'スタバ チャネル / sutaba channel',
            'description' => 'intimate discussion on a broad array of topics',
            'datetime_format' => 'm/d/y H:i:s',
            'guest_name' => 'Anonymous',
            'threads_per_page' => 15,
            'post_delay_seconds' => 5,
            'session_lifetime_seconds' => 60 * 60 * 24 * 7,
            'cookie_path' => '/',
            'permissions' => 'all', // Or an array of allowed IP addresses.
            'subject_min' => 3,
            'comment_min' => 5,
            'subject_max' => 100,
            'comment_max' => 2500,
            'images' => [
                'enabled' => true,
                'required_for_thread' => true,
                'thumbnail_max_width' => 250,
                'thumbnail_max_height' => 250,
                'max_size_bytes' => 10 * 1024 * 1024,
                'max_pixels' => 40_000_000,
            ],
            'access' => [
                'admin' => [
                    'LLVegDyAFo',
                    '8pjmkGgGGE',
                ],
                'moderator' => [],
            ],
        ],
    ],
];

enum StaffRole
{
    case Guest;
    case Moderator;
    case Administrator;

    public function canModerate(): bool
    {
        return $this !== self::Guest;
    }

    public function canAdminister(): bool
    {
        return $this === self::Administrator;
    }
}

final class UserInputException extends RuntimeException
{
    public function __construct(public readonly string $errorKey)
    {
        parent::__construct($errorKey);
    }
}

final readonly class SqliteBlob
{
    public function __construct(public string $value)
    {
    }
}

final readonly class BoardConfig
{
    /**
     * @param string|list<string> $permissions
     * @param list<string> $adminTripcodes
     * @param list<string> $moderatorTripcodes
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $datetimeFormat,
        public string $guestName,
        public int $threadsPerPage,
        public int $postDelaySeconds,
        public int $sessionLifetimeSeconds,
        public string $cookiePath,
        public string|array $permissions,
        public int $subjectMin,
        public int $commentMin,
        public int $subjectMax,
        public int $commentMax,
        public bool $imagesEnabled,
        public bool $imageRequiredForThread,
        public int $thumbnailMaxWidth,
        public int $thumbnailMaxHeight,
        public int $imageMaxSizeBytes,
        public int $imageMaxPixels,
        public array $adminTripcodes,
        public array $moderatorTripcodes,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(string $id, array $data): self
    {
        $images = is_array($data['images'] ?? null) ? $data['images'] : [];
        $access = is_array($data['access'] ?? null) ? $data['access'] : [];
        $permissions = $data['permissions'] ?? 'all';

        if (!is_string($permissions) && !is_array($permissions)) {
            throw new RuntimeException(sprintf('Invalid permissions configuration for board "%s".', $id));
        }

        return new self(
            id: $id,
            title: (string) ($data['title'] ?? $id),
            description: (string) ($data['description'] ?? ''),
            datetimeFormat: (string) ($data['datetime_format'] ?? 'm/d/y H:i:s'),
            guestName: (string) ($data['guest_name'] ?? 'Anonymous'),
            threadsPerPage: max(1, (int) ($data['threads_per_page'] ?? 15)),
            postDelaySeconds: max(0, (int) ($data['post_delay_seconds'] ?? 5)),
            sessionLifetimeSeconds: max(300, (int) ($data['session_lifetime_seconds'] ?? 604_800)),
            cookiePath: (string) ($data['cookie_path'] ?? '/'),
            permissions: $permissions,
            subjectMin: max(0, (int) ($data['subject_min'] ?? 3)),
            commentMin: max(0, (int) ($data['comment_min'] ?? 5)),
            subjectMax: max(1, (int) ($data['subject_max'] ?? 100)),
            commentMax: max(1, (int) ($data['comment_max'] ?? 2500)),
            imagesEnabled: (bool) ($images['enabled'] ?? true),
            imageRequiredForThread: (bool) ($images['required_for_thread'] ?? true),
            thumbnailMaxWidth: max(1, (int) ($images['thumbnail_max_width'] ?? 250)),
            thumbnailMaxHeight: max(1, (int) ($images['thumbnail_max_height'] ?? 250)),
            imageMaxSizeBytes: max(1, (int) ($images['max_size_bytes'] ?? 10_485_760)),
            imageMaxPixels: max(1, (int) ($images['max_pixels'] ?? 40_000_000)),
            adminTripcodes: array_values(array_map('strval', (array) ($access['admin'] ?? []))),
            moderatorTripcodes: array_values(array_map('strval', (array) ($access['moderator'] ?? []))),
        );
    }
}

final class Database
{
    private const int SCHEMA_VERSION = 1;

    private const string SCHEMA = <<<'SQL'
CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    board TEXT NOT NULL,
    parent_id INTEGER,
    created_at INTEGER NOT NULL,
    ip TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT NOT NULL DEFAULT '',
    subject TEXT NOT NULL DEFAULT '',
    comment TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    pinned INTEGER NOT NULL DEFAULT 0 CHECK (pinned IN (0, 1)),
    locked INTEGER NOT NULL DEFAULT 0 CHECK (locked IN (0, 1)),
    FOREIGN KEY (parent_id) REFERENCES posts (id) ON DELETE CASCADE
);

CREATE INDEX posts_board_threads_idx
    ON posts (board, parent_id, pinned, created_at);
CREATE INDEX posts_parent_idx ON posts (parent_id, created_at);

CREATE TABLE images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL UNIQUE,
    filename TEXT NOT NULL UNIQUE,
    original_name TEXT NOT NULL,
    mime_type TEXT NOT NULL,
    size INTEGER NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    original_data BLOB NOT NULL,
    thumbnail_data BLOB NOT NULL,
    FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
);

CREATE TABLE bans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    board TEXT NOT NULL,
    post_id INTEGER,
    created_at INTEGER NOT NULL,
    ip TEXT NOT NULL,
    expires_at INTEGER NOT NULL DEFAULT 0,
    reason TEXT NOT NULL,
    UNIQUE (board, ip),
    FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE SET NULL
);

CREATE INDEX bans_board_expiry_idx ON bans (board, expires_at);

CREATE TABLE reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    board TEXT NOT NULL,
    post_id INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    ip TEXT NOT NULL,
    UNIQUE (board, post_id, ip),
    FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
);

CREATE INDEX reports_board_post_idx ON reports (board, post_id);

CREATE TABLE spam (
    board TEXT NOT NULL,
    ip TEXT NOT NULL,
    available_at INTEGER NOT NULL,
    PRIMARY KEY (board, ip)
);

CREATE INDEX spam_expiry_idx ON spam (available_at);

CREATE TABLE wordfilters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    board TEXT NOT NULL,
    word TEXT NOT NULL,
    replacement TEXT NOT NULL
);

CREATE INDEX wordfilters_board_idx ON wordfilters (board, id);
SQL;

    private SQLite3 $connection;

    public function __construct(string $path)
    {
        $this->connection = new SQLite3(
            $path,
            SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE,
        );
        $this->connection->enableExceptions(true);
        $this->connection->busyTimeout(5_000);
        $this->connection->exec('PRAGMA foreign_keys = ON');
        $this->connection->exec('PRAGMA journal_mode = WAL');
        $this->connection->exec('PRAGMA synchronous = NORMAL');
        $this->connection->exec('PRAGMA trusted_schema = OFF');
        $this->migrate();
    }

    public function close(): void
    {
        $this->connection->close();
    }

    /** @param array<string|int, mixed> $parameters */
    public function execute(string $sql, array $parameters = []): int
    {
        $statement = $this->prepare($sql, $parameters);

        try {
            $result = $statement->execute();
            if ($result instanceof SQLite3Result) {
                $result->finalize();
            }

            return $this->connection->changes();
        } finally {
            $statement->close();
        }
    }

    /**
     * @param array<string|int, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    public function all(string $sql, array $parameters = []): array
    {
        $statement = $this->prepare($sql, $parameters);

        try {
            $result = $statement->execute();
            if (!$result instanceof SQLite3Result) {
                return [];
            }

            $rows = [];
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $rows[] = $row;
            }
            $result->finalize();

            return $rows;
        } finally {
            $statement->close();
        }
    }

    /** @param array<string|int, mixed> $parameters */
    public function one(string $sql, array $parameters = []): ?array
    {
        return $this->all($sql, $parameters)[0] ?? null;
    }

    /** @param array<string|int, mixed> $parameters */
    public function scalar(string $sql, array $parameters = []): mixed
    {
        $row = $this->one($sql, $parameters);

        return $row === null ? null : array_values($row)[0];
    }

    public function lastInsertId(): int
    {
        return $this->connection->lastInsertRowID();
    }

    public function transaction(callable $callback): mixed
    {
        $this->connection->exec('BEGIN IMMEDIATE');

        try {
            $result = $callback();
            $this->connection->exec('COMMIT');

            return $result;
        } catch (Throwable $throwable) {
            $this->connection->exec('ROLLBACK');
            throw $throwable;
        }
    }

    /** @param array<string|int, mixed> $parameters */
    private function prepare(string $sql, array $parameters): SQLite3Stmt
    {
        $statement = $this->connection->prepare($sql);

        foreach ($parameters as $name => $value) {
            $placeholder = is_int($name)
                ? $name + 1
                : (str_starts_with($name, ':') ? $name : ':' . $name);

            [$boundValue, $type] = match (true) {
                $value instanceof SqliteBlob => [$value->value, SQLITE3_BLOB],
                $value === null => [null, SQLITE3_NULL],
                is_bool($value) => [$value ? 1 : 0, SQLITE3_INTEGER],
                is_int($value) => [$value, SQLITE3_INTEGER],
                is_float($value) => [$value, SQLITE3_FLOAT],
                default => [(string) $value, SQLITE3_TEXT],
            };

            $statement->bindValue($placeholder, $boundValue, $type);
        }

        return $statement;
    }

    private function migrate(): void
    {
        $version = (int) $this->connection->querySingle('PRAGMA user_version');

        if ($version > self::SCHEMA_VERSION) {
            throw new RuntimeException(sprintf(
                'This database uses schema version %d, but this Sutaba build supports version %d.',
                $version,
                self::SCHEMA_VERSION,
            ));
        }

        if ($version === 0) {
            $this->connection->exec('BEGIN IMMEDIATE');

            try {
                $this->connection->exec(self::SCHEMA);
                $this->connection->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
                $this->connection->exec('COMMIT');
            } catch (Throwable $throwable) {
                $this->connection->exec('ROLLBACK');
                throw $throwable;
            }
        }
    }
}

final readonly class ProcessedImage
{
    public function __construct(
        public string $filename,
        public string $originalName,
        public string $mimeType,
        public int $size,
        public int $width,
        public int $height,
        public string $originalData,
        public string $thumbnailData,
    ) {
    }
}

final class ImageProcessor
{
    /** @param array<string, mixed>|null $upload */
    public function process(?array $upload, BoardConfig $board): ?ProcessedImage
    {
        if ($upload === null || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new UserInputException('upload_failed');
        }

        $temporaryName = (string) ($upload['tmp_name'] ?? '');
        if ($temporaryName === '' || !is_file($temporaryName)) {
            throw new UserInputException('upload_failed');
        }

        $allowLocalUpload = defined('SUTABA_ALLOW_LOCAL_UPLOADS')
            && SUTABA_ALLOW_LOCAL_UPLOADS === true;
        if (!$allowLocalUpload && !is_uploaded_file($temporaryName)) {
            throw new UserInputException('upload_failed');
        }

        $originalData = file_get_contents($temporaryName);
        if (!is_string($originalData) || $originalData === '') {
            throw new UserInputException('invalid_filetype');
        }

        $size = strlen($originalData);
        if ($size > $board->imageMaxSizeBytes) {
            throw new UserInputException('file_too_large');
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($originalData);
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            default => throw new UserInputException('invalid_filetype'),
        };

        $dimensions = getimagesizefromstring($originalData);
        if ($dimensions === false) {
            throw new UserInputException('invalid_filetype');
        }

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        if ($width < 1 || $height < 1 || ($width * $height) > $board->imageMaxPixels) {
            throw new UserInputException('image_dimensions');
        }

        $source = @imagecreatefromstring($originalData);
        if (!$source instanceof GdImage) {
            throw new UserInputException('invalid_filetype');
        }

        try {
            $scale = min(
                1,
                $board->thumbnailMaxWidth / $width,
                $board->thumbnailMaxHeight / $height,
            );
            $thumbnailWidth = max(1, (int) round($width * $scale));
            $thumbnailHeight = max(1, (int) round($height * $scale));
            $thumbnail = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);

            if (!$thumbnail instanceof GdImage) {
                throw new RuntimeException('Unable to allocate a thumbnail image.');
            }

            try {
                if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                    imagealphablending($thumbnail, false);
                    imagesavealpha($thumbnail, true);
                    $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
                    imagefilledrectangle(
                        $thumbnail,
                        0,
                        0,
                        $thumbnailWidth,
                        $thumbnailHeight,
                        $transparent,
                    );
                }

                if (!imagecopyresampled(
                    $thumbnail,
                    $source,
                    0,
                    0,
                    0,
                    0,
                    $thumbnailWidth,
                    $thumbnailHeight,
                    $width,
                    $height,
                )) {
                    throw new RuntimeException('Unable to resize the uploaded image.');
                }

                ob_start();
                $encoded = match ($mimeType) {
                    'image/jpeg' => imagejpeg($thumbnail, null, 88),
                    'image/png' => imagepng($thumbnail, null, 6),
                    'image/gif' => imagegif($thumbnail),
                };
                $thumbnailData = ob_get_clean();

                if (!$encoded || !is_string($thumbnailData) || $thumbnailData === '') {
                    throw new RuntimeException('Unable to encode the thumbnail image.');
                }
            } finally {
                unset($thumbnail);
            }
        } finally {
            unset($source);
        }

        $originalName = trim((string) ($upload['name'] ?? 'image.' . $extension));
        $safeOriginalName = mb_substr(basename(str_replace('\\', '/', $originalName)), 0, 200);
        $filename = sprintf('%d-%s.%s', time(), bin2hex(random_bytes(8)), $extension);

        return new ProcessedImage(
            filename: $filename,
            originalName: $safeOriginalName !== '' ? $safeOriginalName : 'image.' . $extension,
            mimeType: $mimeType,
            size: $size,
            width: $width,
            height: $height,
            originalData: $originalData,
            thumbnailData: $thumbnailData,
        );
    }
}

final class Common
{
    /** @var array<string, string> */
    public const array ERRORS = [
        'thread_doesnt_exist' => 'The requested thread does not exist.',
        'thread_locked' => 'The requested thread is locked.',
        'wait' => 'Please wait before attempting to post again.',
        'invalid_filetype' => 'The uploaded file is not a valid JPEG, PNG, or GIF image.',
        'file_too_large' => 'The uploaded file is too large.',
        'image_dimensions' => 'The uploaded image has invalid or excessive dimensions.',
        'upload_failed' => 'The image upload did not complete successfully.',
        'board_not_found' => 'The requested board was not found or is not available to you.',
        'password_missing' => 'Enter a password to delete a post or image.',
        'invalid_password' => 'The deletion password was incorrect.',
        'thread_not_found' => 'The requested thread was not found.',
        'image_required' => 'An image is required to start a new thread.',
        'subject_length' => 'The subject is too short.',
        'comment_length' => 'The comment is too short.',
        'subject_too_long' => 'The subject is too long.',
        'comment_too_long' => 'The comment is too long.',
        'invalid_email' => 'Enter a valid email address or leave the email field empty.',
        'csrf' => 'The form expired. Refresh the page and try again.',
        'invalid_request' => 'The request was invalid.',
        'wordfilter_invalid' => 'Enter both a word and a different replacement.',
    ];

    public function role(BoardConfig $board): StaffRole
    {
        $sessionName = (string) ($_SESSION[$board->id]['name'] ?? '');
        if (!str_contains($sessionName, '#')) {
            return StaffRole::Guest;
        }

        [, $password] = explode('#', $sessionName, 2);
        if ($password === '') {
            return StaffRole::Guest;
        }

        $tripcode = $this->generateTripcode($password);
        if (in_array($tripcode, $board->adminTripcodes, true)) {
            return StaffRole::Administrator;
        }

        if (in_array($tripcode, $board->moderatorTripcodes, true)) {
            return StaffRole::Moderator;
        }

        return StaffRole::Guest;
    }

    public function hasPermission(BoardConfig $board, string $ip): bool
    {
        if ($board->permissions === 'all') {
            return true;
        }

        return is_array($board->permissions) && in_array($ip, $board->permissions, true);
    }

    public function generateTripcode(string $password): string
    {
        $encoded = mb_convert_encoding($password, 'SJIS', 'UTF-8');
        $encoded = str_replace(
            ['&', '"', "'", '<', '>'],
            ['&amp;', '&quot;', '&#39;', '&lt;', '&gt;'],
            $encoded,
        );
        $salt = substr($encoded . 'H.', 1, 2);
        $salt = preg_replace('/[^.\/0-9:;<=>?@A-Z\[\\\\\]\^_`a-z]/', '.', $salt) ?? '..';
        $salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
        $hash = crypt($encoded, $salt);

        if ($hash === '' || str_starts_with($hash, '*')) {
            return substr(hash('sha256', $encoded), -10);
        }

        return substr($hash, -10);
    }

    public function generatePassword(int $length = 20): string
    {
        $alphabet = '23456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ!@$*-_+=';
        $password = '';
        $lastIndex = strlen($alphabet) - 1;

        for ($index = 0; $index < $length; $index++) {
            $password .= $alphabet[random_int(0, $lastIndex)];
        }

        return $password;
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public function verifyCsrf(mixed $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public function formatSize(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $size;
        $unit = 0;

        while ($value >= 1024 && $unit < array_key_last($units)) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $unit === 0 ? 0 : 1) . ' ' . $units[$unit];
    }

    public function relativeTime(int $timestamp): string
    {
        if ($timestamp === 0) {
            return 'never';
        }

        $difference = $timestamp - time();
        if ($difference <= 0) {
            return 'expired';
        }

        $units = [
            31_536_000 => 'year',
            2_592_000 => 'month',
            604_800 => 'week',
            86_400 => 'day',
            3_600 => 'hour',
            60 => 'minute',
            1 => 'second',
        ];

        foreach ($units as $seconds => $label) {
            if ($difference >= $seconds) {
                $amount = (int) floor($difference / $seconds);

                return sprintf('in %d %s%s', $amount, $label, $amount === 1 ? '' : 's');
            }
        }

        return 'soon';
    }
}

final class SutabaRepository
{
    private const string POST_COLUMNS = <<<'SQL'
p.id,
p.board,
p.parent_id,
p.created_at,
p.ip,
p.name,
p.email,
p.subject,
p.comment,
p.pinned,
p.locked,
i.id AS image_id,
i.filename,
i.original_name,
i.mime_type,
i.size AS image_size,
i.width AS image_width,
i.height AS image_height,
EXISTS (
    SELECT 1
    FROM bans AS post_ban
    WHERE post_ban.board = p.board AND post_ban.post_id = p.id
) AS banned_for_post
SQL;

    public function __construct(
        private readonly Database $database,
        private readonly BoardConfig $board,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function getThreads(int $page): array
    {
        $offset = max(0, $page - 1) * $this->board->threadsPerPage;

        return $this->database->all(
            'SELECT ' . self::POST_COLUMNS . ',
                (SELECT COUNT(*) FROM posts AS reply WHERE reply.parent_id = p.id) AS reply_count,
                COALESCE(
                    (SELECT MAX(reply.created_at) FROM posts AS reply WHERE reply.parent_id = p.id),
                    p.created_at
                ) AS bumped_at
             FROM posts AS p
             LEFT JOIN images AS i ON i.post_id = p.id
             WHERE p.board = :board AND p.parent_id IS NULL
             ORDER BY p.pinned DESC, bumped_at DESC, p.created_at DESC, p.id DESC
             LIMIT :limit OFFSET :offset',
            [
                'board' => $this->board->id,
                'limit' => $this->board->threadsPerPage,
                'offset' => $offset,
            ],
        );
    }

    public function getThreadCount(): int
    {
        return (int) $this->database->scalar(
            'SELECT COUNT(*) FROM posts WHERE board = :board AND parent_id IS NULL',
            ['board' => $this->board->id],
        );
    }

    /** @return array<string, mixed>|null */
    public function getThread(int $id): ?array
    {
        $thread = $this->database->one(
            'SELECT ' . self::POST_COLUMNS . '
             FROM posts AS p
             LEFT JOIN images AS i ON i.post_id = p.id
             WHERE p.board = :board AND p.id = :id AND p.parent_id IS NULL',
            ['board' => $this->board->id, 'id' => $id],
        );

        if ($thread === null) {
            return null;
        }

        $thread['replies'] = $this->database->all(
            'SELECT ' . self::POST_COLUMNS . ',
                (SELECT COUNT(*) FROM reports AS report
                 WHERE report.board = p.board AND report.post_id = p.id) AS report_count
             FROM posts AS p
             LEFT JOIN images AS i ON i.post_id = p.id
             WHERE p.board = :board AND p.parent_id = :parent_id
             ORDER BY p.created_at ASC, p.id ASC',
            ['board' => $this->board->id, 'parent_id' => $id],
        );

        return $thread;
    }

    /** @return array<string, mixed>|null */
    public function getPost(int $id): ?array
    {
        return $this->database->one(
            'SELECT ' . self::POST_COLUMNS . '
             FROM posts AS p
             LEFT JOIN images AS i ON i.post_id = p.id
             WHERE p.board = :board AND p.id = :id',
            ['board' => $this->board->id, 'id' => $id],
        );
    }

    public function isThread(int $id): bool
    {
        return (bool) $this->database->scalar(
            'SELECT EXISTS(
                SELECT 1 FROM posts
                WHERE board = :board AND id = :id AND parent_id IS NULL
             )',
            ['board' => $this->board->id, 'id' => $id],
        );
    }

    public function isLocked(int $id): bool
    {
        return (bool) $this->database->scalar(
            'SELECT locked FROM posts
             WHERE board = :board AND id = :id AND parent_id IS NULL',
            ['board' => $this->board->id, 'id' => $id],
        );
    }

    /**
     * @param array{
     *   parent_id: ?int,
     *   created_at: int,
     *   ip: string,
     *   name: string,
     *   email: string,
     *   subject: string,
     *   comment: string,
     *   password_hash: string,
     *   pinned: int,
     *   locked: int
     * } $post
     */
    public function createPost(array $post, ?ProcessedImage $image): int
    {
        return $this->database->transaction(function () use ($post, $image): int {
            $this->database->execute(
                'INSERT INTO posts (
                    board, parent_id, created_at, ip, name, email, subject,
                    comment, password_hash, pinned, locked
                 ) VALUES (
                    :board, :parent_id, :created_at, :ip, :name, :email, :subject,
                    :comment, :password_hash, :pinned, :locked
                 )',
                [
                    'board' => $this->board->id,
                    ...$post,
                ],
            );
            $postId = $this->database->lastInsertId();

            if ($image !== null) {
                $this->database->execute(
                    'INSERT INTO images (
                        post_id, filename, original_name, mime_type, size, width,
                        height, original_data, thumbnail_data
                     ) VALUES (
                        :post_id, :filename, :original_name, :mime_type, :size,
                        :width, :height, :original_data, :thumbnail_data
                     )',
                    [
                        'post_id' => $postId,
                        'filename' => $image->filename,
                        'original_name' => $image->originalName,
                        'mime_type' => $image->mimeType,
                        'size' => $image->size,
                        'width' => $image->width,
                        'height' => $image->height,
                        'original_data' => new SqliteBlob($image->originalData),
                        'thumbnail_data' => new SqliteBlob($image->thumbnailData),
                    ],
                );
            }

            return $postId;
        });
    }

    public function consumePostSlot(string $ip): bool
    {
        $now = time();

        return $this->database->transaction(function () use ($ip, $now): bool {
            $availableAt = $this->database->scalar(
                'SELECT available_at FROM spam WHERE board = :board AND ip = :ip',
                ['board' => $this->board->id, 'ip' => $ip],
            );

            if ($availableAt !== null && (int) $availableAt > $now) {
                return false;
            }

            $this->database->execute(
                'INSERT INTO spam (board, ip, available_at)
                 VALUES (:board, :ip, :available_at)
                 ON CONFLICT (board, ip)
                 DO UPDATE SET available_at = excluded.available_at',
                [
                    'board' => $this->board->id,
                    'ip' => $ip,
                    'available_at' => $now + $this->board->postDelaySeconds,
                ],
            );

            return true;
        });
    }

    public function deletePost(int $id, string $password, bool $staff): bool
    {
        $post = $this->database->one(
            'SELECT password_hash FROM posts WHERE board = :board AND id = :id',
            ['board' => $this->board->id, 'id' => $id],
        );

        if ($post === null || (!$staff && !$this->passwordMatches((string) $post['password_hash'], $password))) {
            return false;
        }

        return $this->database->execute(
            'DELETE FROM posts WHERE board = :board AND id = :id',
            ['board' => $this->board->id, 'id' => $id],
        ) > 0;
    }

    public function deleteImage(int $postId, string $password, bool $staff): bool
    {
        $post = $this->database->one(
            'SELECT password_hash FROM posts WHERE board = :board AND id = :id',
            ['board' => $this->board->id, 'id' => $postId],
        );

        if ($post === null || (!$staff && !$this->passwordMatches((string) $post['password_hash'], $password))) {
            return false;
        }

        return $this->database->execute(
            'DELETE FROM images
             WHERE post_id = :post_id
               AND EXISTS (
                   SELECT 1 FROM posts
                   WHERE posts.id = images.post_id AND posts.board = :board
               )',
            ['post_id' => $postId, 'board' => $this->board->id],
        ) > 0;
    }

    public function toggleThread(int $id, bool $locked, bool $pinned): bool
    {
        $assignments = [];
        $parameters = ['board' => $this->board->id, 'id' => $id];

        if ($locked) {
            $assignments[] = 'locked = CASE locked WHEN 1 THEN 0 ELSE 1 END';
        }

        if ($pinned) {
            $assignments[] = 'pinned = CASE pinned WHEN 1 THEN 0 ELSE 1 END';
        }

        if ($assignments === []) {
            return false;
        }

        return $this->database->execute(
            'UPDATE posts SET ' . implode(', ', $assignments) . '
             WHERE board = :board AND id = :id AND parent_id IS NULL',
            $parameters,
        ) > 0;
    }

    public function setThreadAttributes(int $id, ?bool $locked, ?bool $pinned): bool
    {
        $assignments = [];
        $parameters = ['board' => $this->board->id, 'id' => $id];

        if ($locked !== null) {
            $assignments[] = 'locked = :locked';
            $parameters['locked'] = $locked;
        }

        if ($pinned !== null) {
            $assignments[] = 'pinned = :pinned';
            $parameters['pinned'] = $pinned;
        }

        if ($assignments === []) {
            return false;
        }

        return $this->database->execute(
            'UPDATE posts SET ' . implode(', ', $assignments) . '
             WHERE board = :board AND id = :id AND parent_id IS NULL',
            $parameters,
        ) > 0;
    }

    /** @return array<string, mixed>|null */
    public function getImage(int $id): ?array
    {
        return $this->database->one(
            'SELECT i.filename, i.original_name, i.mime_type, i.original_data, i.thumbnail_data
             FROM images AS i
             INNER JOIN posts AS p ON p.id = i.post_id
             WHERE p.board = :board AND i.id = :id',
            ['board' => $this->board->id, 'id' => $id],
        );
    }

    /** @return array<string, mixed>|null */
    public function getBanForIp(string $ip): ?array
    {
        return $this->database->one(
            'SELECT id, board, post_id, created_at, ip, expires_at, reason
             FROM bans
             WHERE board = :board AND ip = :ip
               AND (expires_at = 0 OR expires_at > :now)',
            ['board' => $this->board->id, 'ip' => $ip, 'now' => time()],
        );
    }

    /** @return list<array<string, mixed>> */
    public function getBans(string $sort, string $direction): array
    {
        $column = match ($sort) {
            'ip' => 'ip',
            'expires' => 'expires_at',
            default => 'created_at',
        };
        $order = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        return $this->database->all(
            sprintf(
                'SELECT id, board, post_id, created_at, ip, expires_at, reason
                 FROM bans WHERE board = :board ORDER BY %s %s, id DESC',
                $column,
                $order,
            ),
            ['board' => $this->board->id],
        );
    }

    /** @param list<int> $postIds */
    public function banPosts(array $postIds, int $duration, string $reason): int
    {
        $created = 0;
        $now = time();
        $expiresAt = $duration === 0 ? 0 : $now + $duration;

        foreach ($postIds as $postId) {
            $post = $this->database->one(
                'SELECT id, ip FROM posts WHERE board = :board AND id = :id',
                ['board' => $this->board->id, 'id' => $postId],
            );

            if ($post === null) {
                continue;
            }

            $this->database->execute(
                'INSERT INTO bans (board, post_id, created_at, ip, expires_at, reason)
                 VALUES (:board, :post_id, :created_at, :ip, :expires_at, :reason)
                 ON CONFLICT (board, ip) DO UPDATE SET
                    post_id = excluded.post_id,
                    created_at = excluded.created_at,
                    expires_at = excluded.expires_at,
                    reason = excluded.reason',
                [
                    'board' => $this->board->id,
                    'post_id' => $postId,
                    'created_at' => $now,
                    'ip' => (string) $post['ip'],
                    'expires_at' => $expiresAt,
                    'reason' => $reason,
                ],
            );
            $created++;
        }

        return $created;
    }

    /** @param list<string> $ips */
    public function unban(array $ips): int
    {
        $removed = 0;
        foreach ($ips as $ip) {
            $removed += $this->database->execute(
                'DELETE FROM bans WHERE board = :board AND ip = :ip',
                ['board' => $this->board->id, 'ip' => $ip],
            );
        }

        return $removed;
    }

    public function reportPost(int $postId, string $ip): bool
    {
        return $this->database->execute(
            'INSERT OR IGNORE INTO reports (board, post_id, created_at, ip)
             SELECT :board, :post_id, :created_at, :ip
             WHERE EXISTS (
                 SELECT 1 FROM posts WHERE board = :board AND id = :post_id
             )',
            [
                'board' => $this->board->id,
                'post_id' => $postId,
                'created_at' => time(),
                'ip' => $ip,
            ],
        ) > 0;
    }

    /** @return list<array<string, mixed>> */
    public function getReports(string $sort, string $direction): array
    {
        $column = match ($sort) {
            'post_id' => 'r.post_id',
            'ip' => 'r.ip',
            default => 'r.created_at',
        };
        $order = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $rows = $this->database->all(
            sprintf(
                'SELECT r.post_id, r.created_at, r.ip, p.parent_id
                 FROM reports AS r
                 INNER JOIN posts AS p ON p.id = r.post_id
                 WHERE r.board = :board
                 ORDER BY %s %s, r.id DESC',
                $column,
                $order,
            ),
            ['board' => $this->board->id],
        );

        $reports = [];
        foreach ($rows as $row) {
            $postId = (int) $row['post_id'];
            if (!isset($reports[$postId])) {
                $reports[$postId] = [
                    'post_id' => $postId,
                    'created_at' => (int) $row['created_at'],
                    'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'],
                    'ips' => [],
                ];
            }
            $reports[$postId]['ips'][] = (string) $row['ip'];
        }

        return array_values($reports);
    }

    /** @param list<int> $postIds */
    public function clearReports(array $postIds): int
    {
        $removed = 0;
        foreach ($postIds as $postId) {
            $removed += $this->database->execute(
                'DELETE FROM reports WHERE board = :board AND post_id = :post_id',
                ['board' => $this->board->id, 'post_id' => $postId],
            );
        }

        return $removed;
    }

    /** @return list<array<string, mixed>> */
    public function getWordfilters(string $sort = 'id', string $direction = 'ASC'): array
    {
        $column = match ($sort) {
            'word' => 'word',
            'replacement' => 'replacement',
            default => 'id',
        };
        $order = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        return $this->database->all(
            sprintf(
                'SELECT id, board, word, replacement
                 FROM wordfilters WHERE board = :board ORDER BY %s %s',
                $column,
                $order,
            ),
            ['board' => $this->board->id],
        );
    }

    public function addWordfilter(string $word, string $replacement): bool
    {
        if ($word === '' || $replacement === '' || $word === $replacement) {
            return false;
        }

        return $this->database->execute(
            'INSERT INTO wordfilters (board, word, replacement)
             VALUES (:board, :word, :replacement)',
            [
                'board' => $this->board->id,
                'word' => $word,
                'replacement' => $replacement,
            ],
        ) > 0;
    }

    /** @param list<int> $ids */
    public function deleteWordfilters(array $ids): int
    {
        $removed = 0;
        foreach ($ids as $id) {
            $removed += $this->database->execute(
                'DELETE FROM wordfilters WHERE board = :board AND id = :id',
                ['board' => $this->board->id, 'id' => $id],
            );
        }

        return $removed;
    }

    public function cleanup(): void
    {
        $now = time();
        $this->database->execute(
            'DELETE FROM bans WHERE board = :board AND expires_at > 0 AND expires_at <= :now',
            ['board' => $this->board->id, 'now' => $now],
        );
        $this->database->execute(
            'DELETE FROM spam WHERE available_at <= :now',
            ['now' => $now],
        );
        $this->database->execute(
            'DELETE FROM reports
             WHERE board = :board
               AND NOT EXISTS (SELECT 1 FROM posts WHERE posts.id = reports.post_id)',
            ['board' => $this->board->id],
        );
    }

    private function passwordMatches(string $storedHash, string $password): bool
    {
        return $password !== '' && password_verify($password, $storedHash);
    }
}

final class Renderer
{
    /** @var list<array<string, mixed>>|null */
    private ?array $wordfilters = null;

    /**
     * @param array<string, BoardConfig> $boards
     */
    public function __construct(
        private readonly BoardConfig $board,
        private readonly array $boards,
        private readonly SutabaRepository $repository,
        private readonly Common $common,
        private readonly StaffRole $role,
        private readonly string $csrfToken,
    ) {
    }

    public function page(string $content): string
    {
        $title = escape($this->board->title);
        $description = $this->board->description === ''
            ? ''
            : ' — ' . escape($this->board->description);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}{$description}</title>
<style>
:root{color-scheme:light;--page:#b2b292;--panel:#ffffcc;--panel-alt:#d3d3ab;--ink:#111;--accent:#cc1105;--quote:#789922;--name:#117743}
*{box-sizing:border-box}
body{margin:12px;background:var(--page);color:var(--ink);font:13px/1.35 Arial,sans-serif}
a{color:#000;text-decoration:none}a:hover{color:#c00}
button,input,select,textarea{font:inherit}textarea{max-width:100%}
table{border-collapse:collapse}.layout td{padding:3px 5px}.board-table{width:100%;border:1px solid #000}.board-table th,.board-table td{border:1px solid #000;padding:6px}
.panel{background:var(--panel)}.panel-alt{background:var(--panel-alt)}.title{font-size:1.1rem}.menu{font-weight:normal}.error{padding:8px;background:#fee;border:1px solid #c00;color:#900}
.post{width:100%;margin:6px 0;border:1px solid #000;background:var(--panel)}.post header{padding:5px;background:var(--panel-alt)}.post-body{min-height:44px;padding:8px;overflow:auto}
.post-image{float:left;max-width:250px;max-height:250px;margin:0 8px 6px 0}.subject{color:var(--accent);font-weight:bold}.name{color:var(--name);font-weight:bold}.trip{color:var(--name)}
.quote-text{color:var(--quote)}.quote-button{border:0;background:none;padding:0;color:#000;cursor:pointer}.quote-button:hover{color:#c00}
.staff-admin{color:#800080;font-weight:bold}.staff-mod{color:#c00;font-weight:bold}.banned{color:#c00;font-weight:bold}
.controls{display:flex;justify-content:flex-end;gap:5px;flex-wrap:wrap;margin:6px 0}.pagination{font-size:16px;margin-top:8px}
.report-0{background:#ffffcc}.report-1{background:#ffcccc}.report-2{background:#ffb2b2}.report-3{background:#ff9999}.report-4{background:#ff7f7f}.report-5{background:#ff6666}.report-6{background:#ff4c4c}.report-7{background:#ff3232}.report-8{background:#ff1919}.report-9{background:#ff0000}
hr{border:0;border-top:1px solid #000}.clearfix::after{content:"";display:block;clear:both}
@media(max-width:680px){body{margin:6px}.layout,.layout tbody,.layout tr,.layout td{display:block}.layout td:first-child{font-weight:bold}.post-image{max-width:45vw;height:auto}}
</style>
<script src="main.js" defer></script>
</head>
<body>
{$content}
</body>
</html>
HTML;
    }

    public function home(array $threads, int $page, int $pageCount, ?string $error): string
    {
        $rows = '';
        foreach ($threads as $index => $thread) {
            $id = (int) $thread['id'];
            $subject = escape((string) $thread['subject']);
            $replyCount = (int) $thread['reply_count'];
            $icons = '';
            if ($thread['image_id'] !== null) {
                $icons .= ' <img src="image.gif" title="Image" width="10" height="10" alt="I">';
            }
            if ((int) $thread['locked'] === 1) {
                $icons .= ' <img src="lock.gif" title="Locked" width="10" height="10" alt="L">';
            }
            if ((int) $thread['pinned'] === 1) {
                $icons .= ' <img src="pin.gif" title="Pinned" width="10" height="10" alt="P">';
            }
            $class = $index % 2 === 0 ? 'panel' : 'panel-alt';
            $rows .= <<<HTML
<tr>
<td class="{$class}">
<span style="float:right">{$replyCount}</span>
<input type="checkbox" name="posts[]" value="{$id}">
<a href="?action=view_thread&amp;thread_id={$id}">{$subject}</a>{$icons}
</td>
</tr>
HTML;
        }

        if ($rows === '') {
            $rows = '<tr><td class="panel">No posts found on this board.</td></tr>';
        }

        $pagination = $this->pagination($page, $pageCount);
        $errorHtml = $this->error($error);
        $form = $this->postForm();
        $moderation = $this->moderationControls();

        return $this->header()
            . $errorHtml
            . $form
            . '<hr>'
            . '<form method="post">'
            . $this->csrfField()
            . '<table class="board-table"><thead><tr><th class="panel-alt"><span style="float:right">Replies</span>Title</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . $moderation
            . '</form>'
            . $pagination;
    }

    public function thread(array $thread, ?string $error): string
    {
        $locked = (int) $thread['locked'] === 1;
        $content = $this->header() . $this->error($error);

        if (!$locked || $this->role->canModerate()) {
            $content .= $this->postForm($thread) . '<hr>';
        }

        $content .= '<form method="post">' . $this->csrfField();
        $content .= '<div id="messages">' . $this->post($thread);
        foreach ((array) $thread['replies'] as $reply) {
            $content .= $this->post($reply);
        }
        $content .= '</div>' . $this->moderationControls(true) . '</form>';
        $content .= '<p><a href="?">Return to the board index</a></p>';

        return $content;
    }

    public function missingThread(?string $error): string
    {
        return $this->header()
            . $this->error($error ?? 'thread_not_found')
            . '<p><a href="?">Return to the board index</a></p>';
    }

    public function banned(array $ban): string
    {
        $ip = escape((string) $ban['ip']);
        $reason = escape((string) $ban['reason']);
        $created = date('F j, Y \a\t h:i:s A', (int) $ban['created_at']);
        $expires = (int) $ban['expires_at'] === 0
            ? 'never expire'
            : 'expire ' . $this->common->relativeTime((int) $ban['expires_at']);

        return <<<HTML
<h1 class="banned">BANNED</h1>
<p>Your IP ({$ip}) was banned on {$created} for:</p>
<p><strong>{$reason}</strong></p>
<p>This ban will {$expires}.</p>
HTML;
    }

    /** @param list<array<string, mixed>> $posts */
    public function banConfirmation(array $posts): string
    {
        $fields = '';
        $labels = [];
        foreach ($posts as $post) {
            $id = (int) $post['id'];
            $fields .= '<input type="hidden" name="post_ids[]" value="' . $id . '">';
            $labels[] = '#' . $id;
        }
        $postList = escape(implode(', ', $labels));

        return $this->header()
            . '<h3>Ban the creators of ' . $postList . '?</h3>'
            . '<form method="post">'
            . $this->csrfField()
            . $fields
            . '<input type="hidden" name="action" value="ban_confirm">'
            . '<table class="layout">'
            . '<tr><td><label for="reason">Reason</label></td><td><input id="reason" name="reason" size="50" maxlength="500" required></td></tr>'
            . '<tr><td><label for="expires">Expires</label></td><td><select id="expires" name="expires">'
            . '<option value="3600">One hour</option>'
            . '<option value="21600">Six hours</option>'
            . '<option value="86400">One day</option>'
            . '<option value="172800">Two days</option>'
            . '<option value="604800">One week</option>'
            . '<option value="2419200">One month</option>'
            . '<option value="31536000">One year</option>'
            . '<option value="0">Never</option>'
            . '</select></td></tr>'
            . '<tr><td colspan="2"><button type="submit">Confirm ban</button></td></tr>'
            . '</table></form>';
    }

    public function manage(string $subaction, string $sort, string $direction): string
    {
        $navigation = '<p>'
            . '[ <a href="?action=manage&amp;subaction=bans">Bans</a> ] '
            . '[ <a href="?action=manage&amp;subaction=reports">Reports</a> ] '
            . '[ <a href="?action=manage&amp;subaction=wordfilters">Word filters</a> ]'
            . '</p>';

        $body = match ($subaction) {
            'bans' => $this->manageBans($sort, $direction),
            'reports' => $this->manageReports($sort, $direction),
            'wordfilters' => $this->manageWordfilters($sort, $direction),
            default => '<p>Choose a moderation section.</p>',
        };

        return $this->header() . $navigation . $body;
    }

    private function header(): string
    {
        $title = escape($this->board->title);
        $description = $this->board->description === ''
            ? ''
            : ' — <small>' . escape($this->board->description) . '</small>';

        return '<div class="title"><a href="?"><strong>' . $title . '</strong></a>' . $description . '</div>'
            . '<hr><div class="menu">' . $this->menu() . '</div><hr>';
    }

    private function menu(): string
    {
        $items = [];
        foreach ($this->boards as $board) {
            $items[] = '[ <a href="?board=' . rawurlencode($board->id) . '">' . escape($board->title) . '</a> ]';
        }
        if ($this->role->canModerate()) {
            $items[] = '[ <a href="?action=manage">Manage</a> ]';
        }

        return implode(' ', $items);
    }

    private function postForm(?array $thread = null): string
    {
        $name = escape((string) ($_SESSION[$this->board->id]['name'] ?? $this->board->guestName));
        $email = escape((string) ($_SESSION[$this->board->id]['email'] ?? ''));
        $password = escape((string) ($_SESSION[$this->board->id]['password'] ?? ''));
        $parentField = '';
        if ($thread !== null) {
            $parentField = '<input type="hidden" name="parent_id" value="' . (int) $thread['id'] . '">';
        }
        $fileRow = $this->board->imagesEnabled
            ? '<tr><td><label for="file">File</label></td><td><input id="file" type="file" name="file" accept="image/jpeg,image/png,image/gif"></td></tr>'
            : '';
        $moderationRows = '';

        if ($thread !== null && $this->role->canModerate()) {
            $locked = (int) $thread['locked'] === 1 ? ' checked' : '';
            $pinned = (int) $thread['pinned'] === 1 ? ' checked' : '';
            $moderationRows = '<tr><td>Moderation</td><td>'
                . '<label><input type="checkbox" name="locked" value="1"' . $locked . '> Lock thread</label>';
            if ($this->role->canAdminister()) {
                $moderationRows .= '<br><label><input type="checkbox" name="pinned" value="1"' . $pinned . '> Pin thread</label>';
            }
            $moderationRows .= '</td></tr>';
        } elseif ($thread === null && $this->role->canModerate()) {
            $moderationRows = '<tr><td>Moderation</td><td>'
                . '<label><input type="checkbox" name="locked" value="1"> Lock thread</label>';
            if ($this->role->canAdminister()) {
                $moderationRows .= '<br><label><input type="checkbox" name="pinned" value="1"> Pin thread</label>';
            }
            $moderationRows .= '</td></tr>';
        }
        $csrfField = $this->csrfField();
        $subjectMax = $this->board->subjectMax;
        $commentMax = $this->board->commentMax;

        return <<<HTML
<form method="post" name="post-form" enctype="multipart/form-data">
{$csrfField}
<input type="hidden" name="action" value="post">
{$parentField}
<table class="layout">
<tr><td><label for="name">Name</label></td><td><input id="name" name="name" size="32" maxlength="80" value="{$name}"></td></tr>
<tr><td><label for="email">Email</label></td><td><input id="email" type="email" name="email" size="32" maxlength="254" value="{$email}"></td></tr>
<tr><td><label for="subject">Subject</label></td><td><input id="subject" name="subject" size="32" maxlength="{$subjectMax}"> <button type="submit">Submit</button></td></tr>
<tr><td><label for="comment">Message</label></td><td><textarea id="comment" name="comment" cols="58" rows="5" maxlength="{$commentMax}"></textarea></td></tr>
{$fileRow}
<tr><td><label for="password">Password</label></td><td><input id="password" type="password" name="password" value="{$password}"> <small>(for post and image deletion)</small></td></tr>
{$moderationRows}
</table>
</form>
HTML;
    }

    private function post(array $post): string
    {
        $id = (int) $post['id'];
        $threadId = $post['parent_id'] === null ? $id : (int) $post['parent_id'];
        $subject = escape((string) $post['subject']);
        $name = (string) $post['name'];
        $tripcode = null;
        if (str_contains($name, '#')) {
            [$name, $tripcode] = explode('#', $name, 2);
        }
        $safeName = escape($name === '' ? $this->board->guestName : $name);
        $safeEmail = escape((string) $post['email']);
        $nameMarkup = $safeEmail === ''
            ? $safeName
            : '<a href="mailto:' . $safeEmail . '">' . $safeName . '</a>';
        $tripcodeMarkup = $tripcode === null ? '' : ' <span class="trip">!' . escape($tripcode) . '</span>';
        $staffMarkup = '';
        if ($tripcode !== null && in_array($tripcode, $this->board->adminTripcodes, true)) {
            $staffMarkup = ' <span class="staff-admin">## Admin ##</span>';
        } elseif ($tripcode !== null && in_array($tripcode, $this->board->moderatorTripcodes, true)) {
            $staffMarkup = ' <span class="staff-mod">## Mod ##</span>';
        }
        $date = date($this->board->datetimeFormat, (int) $post['created_at']);
        $icons = '';
        if ((int) $post['locked'] === 1 && $post['parent_id'] === null) {
            $icons .= ' <img src="lock.gif" title="Locked" width="10" height="10" alt="L">';
        }
        if ((int) $post['pinned'] === 1 && $post['parent_id'] === null) {
            $icons .= ' <img src="pin.gif" title="Pinned" width="10" height="10" alt="P">';
        }

        $fileMarkup = '';
        $imageMarkup = '';
        if ($post['image_id'] !== null) {
            $imageId = (int) $post['image_id'];
            $originalName = escape((string) $post['original_name']);
            $size = $this->common->formatSize((int) $post['image_size']);
            $width = (int) $post['image_width'];
            $height = (int) $post['image_height'];
            $fileMarkup = '<div>File: <a href="?action=image&amp;id=' . $imageId . '">'
                . $originalName . '</a> — ' . $size . ', ' . $width . '×' . $height . '</div>';
            $imageMarkup = '<a href="?action=image&amp;id=' . $imageId . '">'
                . '<img class="post-image" src="?action=image&amp;id=' . $imageId . '&amp;thumbnail=1" alt="">'
                . '</a>';
        }

        $reportCount = min(9, max(0, (int) ($post['report_count'] ?? 0)));
        $reportClass = 'report-' . $reportCount;
        $comment = $this->formatComment((string) $post['comment']);
        $banned = (int) $post['banned_for_post'] === 1
            ? '<p class="banned">(USER WAS BANNED FOR THIS POST.)</p>'
            : '';
        $isoTime = $this->isoTime((int) $post['created_at']);

        return <<<HTML
<article class="post" id="post-{$id}">
<header>
<input type="checkbox" name="posts[]" value="{$id}">
<a href="?action=view_thread&amp;thread_id={$threadId}#post-{$id}">No.</a>
<button class="quote-button" type="button" data-quote="{$id}">{$id}</button>
 <span class="subject">{$subject}</span>
 <span class="name">{$nameMarkup}</span>{$tripcodeMarkup}{$staffMarkup}
 <time datetime="{$isoTime}">{$date}</time>{$icons}
{$fileMarkup}
</header>
<div class="post-body clearfix {$reportClass}">
{$imageMarkup}{$comment}{$banned}
</div>
</article>
HTML;
    }

    private function moderationControls(bool $includeReport = false): string
    {
        $password = escape((string) ($_SESSION[$this->board->id]['password'] ?? ''));
        $buttons = '<button name="action" value="delete_post" type="submit">Delete Post</button>'
            . '<button name="action" value="delete_image" type="submit">Delete Image</button>';

        if ($this->role->canAdminister()) {
            $buttons .= '<button name="action" value="toggle_pin" type="submit">Toggle Pin</button>';
        }
        if ($this->role->canModerate()) {
            $buttons .= '<button name="action" value="toggle_lock" type="submit">Toggle Lock</button>'
                . '<button name="action" value="ban" type="submit">Ban</button>';
        }
        if ($includeReport) {
            $buttons .= '<button name="action" value="report" type="submit">Report</button>';
        }

        return '<div class="controls">'
            . '<label>Deletion password <input type="password" name="password" value="' . $password . '"></label>'
            . $buttons
            . '</div>';
    }

    private function pagination(int $currentPage, int $pageCount): string
    {
        if ($pageCount <= 1) {
            return '';
        }

        $links = [];
        for ($page = 1; $page <= $pageCount; $page++) {
            $links[] = $page === $currentPage
                ? '[ <strong>' . $page . '</strong> ]'
                : '[ <a href="?page=' . $page . '">' . $page . '</a> ]';
        }

        return '<nav class="pagination" aria-label="Pages">' . implode(' ', $links) . '</nav>';
    }

    private function formatComment(string $comment): string
    {
        $this->wordfilters ??= $this->repository->getWordfilters();
        foreach ($this->wordfilters as $filter) {
            $comment = str_replace(
                (string) $filter['word'],
                (string) $filter['replacement'],
                $comment,
            );
        }

        $html = nl2br(escape($comment), false);
        $html = preg_replace_callback(
            '/&gt;&gt;(\d+)/',
            function (array $match): string {
                $postId = (int) $match[1];
                $post = $this->repository->getPost($postId);
                if ($post === null) {
                    return '<span class="quote-text">&gt;&gt;' . $postId . '</span>';
                }
                $threadId = $post['parent_id'] === null ? $postId : (int) $post['parent_id'];

                return '<a class="quote-text" href="?action=view_thread&amp;thread_id='
                    . $threadId . '#post-' . $postId . '">&gt;&gt;' . $postId . '</a>';
            },
            $html,
        ) ?? $html;

        return preg_replace(
            '/(^|<br>)(\s*&gt;(?!&gt;).*?)(?=<br>|$)/',
            '$1<span class="quote-text">$2</span>',
            $html,
        ) ?? $html;
    }

    private function manageBans(string $sort, string $direction): string
    {
        $bans = $this->repository->getBans($sort, $direction);
        $rows = '';
        foreach ($bans as $index => $ban) {
            $class = $index % 2 === 0 ? 'panel' : 'panel-alt';
            $ip = escape((string) $ban['ip']);
            $reason = escape((string) $ban['reason']);
            $created = date('F j, Y h:i:s A', (int) $ban['created_at']);
            $expires = $this->common->relativeTime((int) $ban['expires_at']);
            $rows .= '<tr class="' . $class . '">'
                . '<td><input type="checkbox" name="ips[]" value="' . $ip . '"></td>'
                . '<td>' . escape((string) $ban['board']) . '</td>'
                . '<td>' . $ip . '</td><td>' . $created . '</td><td>' . $reason . '</td><td>' . $expires . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr class="panel"><td colspan="6">No banned users found.</td></tr>';
        }

        return '<form method="post">' . $this->csrfField()
            . '<table class="board-table"><thead><tr class="panel-alt"><th></th><th>Board</th><th>IP</th><th>Banned on</th><th>Reason</th><th>Expires</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<p><button name="action" value="unban" type="submit">Unban selected</button></p></form>';
    }

    private function manageReports(string $sort, string $direction): string
    {
        $reports = $this->repository->getReports($sort, $direction);
        $rows = '';
        foreach ($reports as $index => $report) {
            $class = $index % 2 === 0 ? 'panel' : 'panel-alt';
            $postId = (int) $report['post_id'];
            $threadId = $report['parent_id'] === null ? $postId : (int) $report['parent_id'];
            $time = date('F j, Y h:i:s A', (int) $report['created_at']);
            $ips = escape(implode(', ', (array) $report['ips']));
            $rows .= '<tr class="' . $class . '">'
                . '<td><input type="checkbox" name="post_ids[]" value="' . $postId . '"></td>'
                . '<td>' . escape($this->board->id) . '</td>'
                . '<td>' . $postId . ' [<a href="?action=view_thread&amp;thread_id=' . $threadId . '#post-' . $postId . '">Link</a>]</td>'
                . '<td>' . $time . '</td><td>' . $ips . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr class="panel"><td colspan="5">No reports found.</td></tr>';
        }

        return '<form method="post">' . $this->csrfField()
            . '<table class="board-table"><thead><tr class="panel-alt"><th></th><th>Board</th><th>Post</th><th>Time</th><th>Reporter IPs</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<p><button name="action" value="clear_reports" type="submit">Clear selected reports</button></p></form>';
    }

    private function manageWordfilters(string $sort, string $direction): string
    {
        $filters = $this->repository->getWordfilters($sort, $direction);
        $rows = '';
        foreach ($filters as $index => $filter) {
            $class = $index % 2 === 0 ? 'panel' : 'panel-alt';
            $id = (int) $filter['id'];
            $rows .= '<tr class="' . $class . '">'
                . '<td><input type="checkbox" name="wordfilter_ids[]" value="' . $id . '"></td>'
                . '<td>' . escape((string) $filter['board']) . '</td>'
                . '<td>' . escape((string) $filter['word']) . '</td>'
                . '<td>' . escape((string) $filter['replacement']) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr class="panel"><td colspan="4">No word filters found.</td></tr>';
        }

        return '<form method="post">' . $this->csrfField()
            . '<table class="layout"><tr><td><label for="word">Word</label></td><td><input id="word" name="word" required></td></tr>'
            . '<tr><td><label for="replacement">Replacement</label></td><td><input id="replacement" name="replacement" required></td></tr>'
            . '<tr><td colspan="2"><button name="action" value="add_wordfilter" type="submit">Add word filter</button></td></tr></table>'
            . '<table class="board-table"><thead><tr class="panel-alt"><th></th><th>Board</th><th>Word</th><th>Replacement</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<p><button name="action" value="delete_wordfilters" type="submit">Delete selected filters</button></p></form>';
    }

    private function error(?string $key): string
    {
        if ($key === null || !isset(Common::ERRORS[$key])) {
            return '';
        }

        return '<p class="error" role="alert">' . escape(Common::ERRORS[$key]) . '</p><hr>';
    }

    private function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . escape($this->csrfToken) . '">';
    }

    private function isoTime(int $timestamp): string
    {
        return escape(date(DATE_ATOM, $timestamp));
    }
}

final class Application
{
    /** @var array<string, BoardConfig> */
    private array $boards = [];
    private BoardConfig $board;
    private Database $database;
    private SutabaRepository $repository;
    private Common $common;
    private StaffRole $role;
    private Renderer $renderer;
    private string $ip;
    private ?string $selectionError = null;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
        $rawBoards = $config['boards'] ?? null;
        if (!is_array($rawBoards) || $rawBoards === []) {
            throw new RuntimeException('Configure at least one board.');
        }

        foreach ($rawBoards as $id => $rawBoard) {
            if (is_string($id) && is_array($rawBoard)) {
                $this->boards[$id] = BoardConfig::fromArray($id, $rawBoard);
            }
        }
        if ($this->boards === []) {
            throw new RuntimeException('Configure at least one valid board.');
        }

        $this->ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $this->common = new Common();
        $this->startSession(array_values($this->boards)[0]);
        $this->board = $this->selectBoard();
        $this->database = new Database((string) ($config['database_path'] ?? __DIR__ . '/sutaba.sqlite3'));
        $this->repository = new SutabaRepository($this->database, $this->board);
        $this->role = $this->common->role($this->board);
        $this->renderer = new Renderer(
            board: $this->board,
            boards: $this->boards,
            repository: $this->repository,
            common: $this->common,
            role: $this->role,
            csrfToken: $this->common->csrfToken(),
        );
    }

    public function run(): void
    {
        $this->sendSecurityHeaders();
        $getAction = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';

        if ($getAction === 'image') {
            $this->serveImage();

            return;
        }

        $this->repository->cleanup();
        $ban = $this->repository->getBanForIp($this->ip);
        if ($ban !== null) {
            echo $this->renderer->page($this->renderer->banned($ban));

            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                $content = $this->handlePost();
                if ($content !== null) {
                    echo $this->renderer->page($content);

                    return;
                }
            } catch (UserInputException $exception) {
                $this->redirectWithError($exception->errorKey);
            }
        }

        $error = $this->selectionError;
        if (is_string($_GET['error'] ?? null) && isset(Common::ERRORS[$_GET['error']])) {
            $error = $_GET['error'];
        }

        $content = match ($getAction) {
            'view_thread' => $this->threadPage($error),
            'manage' => $this->managePage(),
            default => $this->homePage($error),
        };

        echo $this->renderer->page($content);
    }

    private function startSession(BoardConfig $defaultBoard): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('sutaba_session');
        ini_set('session.gc_maxlifetime', (string) $defaultBoard->sessionLifetimeSeconds);
        session_set_cookie_params([
            'lifetime' => $defaultBoard->sessionLifetimeSeconds,
            'path' => $defaultBoard->cookiePath,
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    private function selectBoard(): BoardConfig
    {
        $firstPermitted = null;
        foreach ($this->boards as $candidate) {
            if ($this->common->hasPermission($candidate, $this->ip)) {
                $firstPermitted = $candidate;
                break;
            }
        }

        if (!$firstPermitted instanceof BoardConfig) {
            http_response_code(403);
            exit('You do not have permission to view any boards.');
        }

        $requestedId = is_string($_GET['board'] ?? null) ? $_GET['board'] : null;
        if ($requestedId !== null) {
            $requestedBoard = $this->boards[$requestedId] ?? null;
            if ($requestedBoard instanceof BoardConfig
                && $this->common->hasPermission($requestedBoard, $this->ip)) {
                $_SESSION['board'] = $requestedId;
            } else {
                $this->selectionError = 'board_not_found';
            }
        }

        $sessionId = is_string($_SESSION['board'] ?? null) ? $_SESSION['board'] : '';
        $sessionBoard = $this->boards[$sessionId] ?? null;
        if (!$sessionBoard instanceof BoardConfig
            || !$this->common->hasPermission($sessionBoard, $this->ip)) {
            $sessionBoard = $firstPermitted;
            $_SESSION['board'] = $sessionBoard->id;
        }

        if (!isset($_SESSION[$sessionBoard->id]['password'])) {
            $_SESSION[$sessionBoard->id]['password'] = $this->common->generatePassword();
        }

        return $sessionBoard;
    }

    private function handlePost(): ?string
    {
        if (!$this->common->verifyCsrf($_POST['csrf_token'] ?? null)) {
            throw new UserInputException('csrf');
        }

        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

        return match ($action) {
            'post' => $this->createPost(),
            'delete_post', 'Delete Post' => $this->deleteSelectedPosts(),
            'delete_image', 'Delete Image' => $this->deleteSelectedImages(),
            'report', 'Report' => $this->reportSelectedPosts(),
            'toggle_pin', 'Toggle Pin' => $this->toggleSelectedPosts(pinned: true),
            'toggle_lock', 'Toggle Lock' => $this->toggleSelectedPosts(pinned: false),
            'ban', 'Ban' => $this->prepareBan(),
            'ban_confirm' => $this->confirmBan(),
            'unban' => $this->unban(),
            'clear_reports' => $this->clearReports(),
            'add_wordfilter' => $this->addWordfilter(),
            'delete_wordfilters' => $this->deleteWordfilters(),
            default => throw new UserInputException('invalid_request'),
        };
    }

    private function createPost(): never
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $password = (string) ($_POST['password'] ?? $_SESSION[$this->board->id]['password']);

        $_SESSION[$this->board->id]['name'] = $name;
        $_SESSION[$this->board->id]['email'] = $email;
        if ($password !== '') {
            $_SESSION[$this->board->id]['password'] = $password;
        }

        $this->role = $this->common->role($this->board);
        $parentId = $this->optionalPositiveInt($_POST['parent_id'] ?? null);

        if ($parentId !== null && !$this->repository->isThread($parentId)) {
            throw new UserInputException('thread_doesnt_exist');
        }
        if ($parentId !== null && $this->repository->isLocked($parentId) && !$this->role->canModerate()) {
            throw new UserInputException('thread_locked');
        }

        if ($parentId === null && mb_strlen($subject) < $this->board->subjectMin) {
            throw new UserInputException('subject_length');
        }
        if (mb_strlen($subject) > $this->board->subjectMax) {
            throw new UserInputException('subject_too_long');
        }
        if (mb_strlen($comment) < $this->board->commentMin) {
            if ($comment === '' && $parentId !== null && $this->role->canModerate()) {
                $this->repository->setThreadAttributes(
                    $parentId,
                    isset($_POST['locked']),
                    $this->role->canAdminister() ? isset($_POST['pinned']) : null,
                );
                $this->redirect(['action' => 'view_thread', 'thread_id' => $parentId]);
            }
            throw new UserInputException('comment_length');
        }
        if (mb_strlen($comment) > $this->board->commentMax && !$this->role->canModerate()) {
            throw new UserInputException('comment_too_long');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new UserInputException('invalid_email');
        }
        if ($password === '') {
            throw new UserInputException('password_missing');
        }

        $image = null;
        if ($this->board->imagesEnabled) {
            $upload = isset($_FILES['file']) && is_array($_FILES['file']) ? $_FILES['file'] : null;
            $image = (new ImageProcessor())->process($upload, $this->board);
        }
        if ($parentId === null
            && $this->board->imagesEnabled
            && $this->board->imageRequiredForThread
            && $image === null
            && !$this->role->canModerate()) {
            throw new UserInputException('image_required');
        }

        if (!$this->role->canModerate() && !$this->repository->consumePostSlot($this->ip)) {
            throw new UserInputException('wait');
        }

        $postName = $name === '' ? $this->board->guestName : $name;
        if (str_contains($postName, '#')) {
            [$plainName, $tripcodePassword] = explode('#', $postName, 2);
            $postName = $plainName;
            if ($tripcodePassword !== '') {
                $postName .= '#' . $this->common->generateTripcode($tripcodePassword);
            }
        }
        $postName = mb_substr($postName, 0, 120);

        $pinned = $parentId === null
            && $this->role->canAdminister()
            && isset($_POST['pinned'])
            ? 1
            : 0;
        $locked = $parentId === null
            && $this->role->canModerate()
            && isset($_POST['locked'])
            ? 1
            : 0;

        if ($parentId !== null && $this->role->canModerate()) {
            $this->repository->setThreadAttributes(
                $parentId,
                isset($_POST['locked']),
                $this->role->canAdminister() ? isset($_POST['pinned']) : null,
            );
        }

        $postId = $this->repository->createPost(
            [
                'parent_id' => $parentId,
                'created_at' => time(),
                'ip' => $this->ip,
                'name' => $postName,
                'email' => $email,
                'subject' => $subject,
                'comment' => $comment,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'pinned' => $pinned,
                'locked' => $locked,
            ],
            $image,
        );
        $threadId = $parentId ?? $postId;

        $this->redirect(
            ['action' => 'view_thread', 'thread_id' => $threadId],
            'post-' . $postId,
        );
    }

    private function deleteSelectedPosts(): never
    {
        $postIds = $this->selectedIds($_POST['posts'] ?? null);
        $password = (string) ($_POST['password'] ?? '');
        if ($postIds === []) {
            throw new UserInputException('invalid_request');
        }
        if ($password === '' && !$this->role->canModerate()) {
            throw new UserInputException('password_missing');
        }

        $deleted = false;
        foreach ($postIds as $postId) {
            $deleted = $this->repository->deletePost(
                $postId,
                $password,
                $this->role->canModerate(),
            ) || $deleted;
        }
        if (!$deleted) {
            throw new UserInputException('invalid_password');
        }

        $this->redirectCurrent();
    }

    private function deleteSelectedImages(): never
    {
        $postIds = $this->selectedIds($_POST['posts'] ?? null);
        $password = (string) ($_POST['password'] ?? '');
        if ($postIds === []) {
            throw new UserInputException('invalid_request');
        }
        if ($password === '' && !$this->role->canModerate()) {
            throw new UserInputException('password_missing');
        }

        $deleted = false;
        foreach ($postIds as $postId) {
            $deleted = $this->repository->deleteImage(
                $postId,
                $password,
                $this->role->canModerate(),
            ) || $deleted;
        }
        if (!$deleted) {
            throw new UserInputException('invalid_password');
        }

        $this->redirectCurrent();
    }

    private function reportSelectedPosts(): never
    {
        foreach ($this->selectedIds($_POST['posts'] ?? null) as $postId) {
            $this->repository->reportPost($postId, $this->ip);
        }

        $this->redirectCurrent();
    }

    private function toggleSelectedPosts(bool $pinned): never
    {
        if (($pinned && !$this->role->canAdminister())
            || (!$pinned && !$this->role->canModerate())) {
            throw new UserInputException('invalid_request');
        }

        foreach ($this->selectedIds($_POST['posts'] ?? null) as $postId) {
            $this->repository->toggleThread(
                $postId,
                !$pinned,
                $pinned,
            );
        }

        $this->redirectCurrent();
    }

    private function prepareBan(): string
    {
        if (!$this->role->canModerate()) {
            throw new UserInputException('invalid_request');
        }

        $posts = [];
        foreach ($this->selectedIds($_POST['posts'] ?? null) as $postId) {
            $post = $this->repository->getPost($postId);
            if ($post !== null) {
                $posts[] = $post;
            }
        }
        if ($posts === []) {
            throw new UserInputException('invalid_request');
        }

        return $this->renderer->banConfirmation($posts);
    }

    private function confirmBan(): never
    {
        if (!$this->role->canModerate()) {
            throw new UserInputException('invalid_request');
        }

        $postIds = $this->selectedIds($_POST['post_ids'] ?? null);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $duration = $this->nonNegativeInt($_POST['expires'] ?? null);
        $allowedDurations = [0, 3_600, 21_600, 86_400, 172_800, 604_800, 2_419_200, 31_536_000];

        if ($postIds === [] || $reason === '' || mb_strlen($reason) > 500
            || $duration === null || !in_array($duration, $allowedDurations, true)) {
            throw new UserInputException('invalid_request');
        }

        $this->repository->banPosts($postIds, $duration, $reason);
        $this->redirect(['action' => 'manage', 'subaction' => 'bans']);
    }

    private function unban(): never
    {
        if (!$this->role->canModerate()) {
            throw new UserInputException('invalid_request');
        }

        $ips = [];
        foreach ((array) ($_POST['ips'] ?? []) as $ip) {
            if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                $ips[] = $ip;
            }
        }
        $this->repository->unban($ips);
        $this->redirect(['action' => 'manage', 'subaction' => 'bans']);
    }

    private function clearReports(): never
    {
        if (!$this->role->canModerate()) {
            throw new UserInputException('invalid_request');
        }

        $this->repository->clearReports($this->selectedIds($_POST['post_ids'] ?? null));
        $this->redirect(['action' => 'manage', 'subaction' => 'reports']);
    }

    private function addWordfilter(): never
    {
        if (!$this->role->canModerate()) {
            throw new UserInputException('invalid_request');
        }

        $word = trim((string) ($_POST['word'] ?? ''));
        $replacement = trim((string) ($_POST['replacement'] ?? ''));
        if (mb_strlen($word) > 200 || mb_strlen($replacement) > 200
            || !$this->repository->addWordfilter($word, $replacement)) {
            throw new UserInputException('wordfilter_invalid');
        }

        $this->redirect(['action' => 'manage', 'subaction' => 'wordfilters']);
    }

    private function deleteWordfilters(): never
    {
        if (!$this->role->canModerate()) {
            throw new UserInputException('invalid_request');
        }

        $this->repository->deleteWordfilters(
            $this->selectedIds($_POST['wordfilter_ids'] ?? null),
        );
        $this->redirect(['action' => 'manage', 'subaction' => 'wordfilters']);
    }

    private function homePage(?string $error): string
    {
        $page = max(1, $this->optionalPositiveInt($_GET['page'] ?? null) ?? 1);
        $threadCount = $this->repository->getThreadCount();
        $pageCount = max(1, (int) ceil($threadCount / $this->board->threadsPerPage));
        $page = min($page, $pageCount);

        return $this->renderer->home(
            $this->repository->getThreads($page),
            $page,
            $pageCount,
            $error,
        );
    }

    private function threadPage(?string $error): string
    {
        $threadId = $this->optionalPositiveInt($_GET['thread_id'] ?? null);
        if ($threadId === null) {
            return $this->renderer->missingThread($error);
        }

        $thread = $this->repository->getThread($threadId);

        return $thread === null
            ? $this->renderer->missingThread($error)
            : $this->renderer->thread($thread, $error);
    }

    private function managePage(): string
    {
        if (!$this->role->canModerate()) {
            $this->redirect();
        }

        $subaction = is_string($_GET['subaction'] ?? null) ? $_GET['subaction'] : '';
        $sort = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : '';
        $direction = is_string($_GET['direction'] ?? null) ? $_GET['direction'] : 'DESC';

        return $this->renderer->manage($subaction, $sort, $direction);
    }

    private function serveImage(): void
    {
        $imageId = $this->optionalPositiveInt($_GET['id'] ?? null);
        $image = $imageId === null ? null : $this->repository->getImage($imageId);
        if ($image === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Image not found.';

            return;
        }

        $thumbnail = isset($_GET['thumbnail']) && $_GET['thumbnail'] === '1';
        $data = (string) $image[$thumbnail ? 'thumbnail_data' : 'original_data'];
        $filename = str_replace(["\r", "\n", '"'], '', (string) $image['original_name']);
        header('Content-Type: ' . (string) $image['mime_type']);
        header('Content-Length: ' . strlen($data));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=86400');
        echo $data;
    }

    /** @return list<int> */
    private function selectedIds(mixed $values): array
    {
        $ids = [];
        foreach ((array) $values as $value) {
            $id = $this->optionalPositiveInt($value);
            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($integer) ? $integer : null;
    }

    private function nonNegativeInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return is_int($integer) ? $integer : null;
    }

    /** @param array<string, int|string> $parameters */
    private function redirect(array $parameters = [], string $fragment = ''): never
    {
        $location = $parameters === [] ? '?' : '?' . http_build_query($parameters);
        if ($fragment !== '') {
            $location .= '#' . rawurlencode($fragment);
        }
        header('Location: ' . $location, true, 303);
        exit;
    }

    private function redirectCurrent(): never
    {
        $parameters = [];
        $action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
        if (in_array($action, ['view_thread', 'manage'], true)) {
            $parameters['action'] = $action;
        }

        $threadId = $this->optionalPositiveInt($_GET['thread_id'] ?? null);
        if ($action === 'view_thread' && $threadId !== null) {
            $parameters['thread_id'] = $threadId;
        }

        $subaction = is_string($_GET['subaction'] ?? null) ? $_GET['subaction'] : '';
        if ($action === 'manage' && in_array($subaction, ['bans', 'reports', 'wordfilters'], true)) {
            $parameters['subaction'] = $subaction;
        }

        $this->redirect($parameters);
    }

    private function redirectWithError(string $error): never
    {
        $parameters = ['error' => $error];
        $action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
        if ($action === 'view_thread') {
            $threadId = $this->optionalPositiveInt($_GET['thread_id'] ?? null);
            if ($threadId !== null) {
                $parameters = [
                    'action' => 'view_thread',
                    'thread_id' => $threadId,
                    'error' => $error,
                ];
            }
        } elseif ($action === 'manage') {
            $parameters['action'] = 'manage';
            $subaction = is_string($_GET['subaction'] ?? null) ? $_GET['subaction'] : '';
            if (in_array($subaction, ['bans', 'reports', 'wordfilters'], true)) {
                $parameters['subaction'] = $subaction;
            }
        }

        $this->redirect($parameters);
    }

    private function sendSecurityHeaders(): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "img-src 'self' data:; script-src 'self'; style-src 'unsafe-inline'; "
            . "base-uri 'self'; form-action 'self'; frame-ancestors 'self'",
        );
    }

    private function isHttps(): bool
    {
        return isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== ''
            && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }
}

function escape(string|int|float|null $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
        'UTF-8',
    );
}

if (!defined('SUTABA_SKIP_RUN')) {
    try {
        (new Application($config))->run();
    } catch (Throwable $throwable) {
        error_log((string) $throwable);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
        }
        echo 'Sutaba could not start. Check the PHP error log for details.';
    }
}
