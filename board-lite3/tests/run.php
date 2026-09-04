<?php

declare(strict_types=1);

use Chessboard\Application;
use Chessboard\Database;
use Chessboard\Http\HttpException;
use Chessboard\Http\Request;
use Chessboard\Installer;
use Chessboard\Repository\BoardRepository;
use Chessboard\Repository\ModerationRepository;
use Chessboard\Repository\PostRepository;
use Chessboard\Security\Csrf;
use Chessboard\Service\Markup;
use Chessboard\Service\UploadService;
if (PHP_SAPI !== 'cli') {
    echo "Run this test suite with PHP's command-line executable.\n";
    exit(1);
}

$temporaryRoot = dirname(__DIR__) . '/var/.test-' . bin2hex(random_bytes(6));
$databasePath = $temporaryRoot . '/test.sqlite';
$storagePath = $temporaryRoot . '/uploads';
$logPath = $temporaryRoot . '/app.log';

function remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $child = $path . '/' . $item;
        if (is_dir($child)) {
            remove_tree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}

if (getenv('CHESSBOARD_KEEP_TEST_FILES') !== '1') {
    register_shutdown_function(static fn () => remove_tree($temporaryRoot));
}

if (!mkdir($temporaryRoot, 0770, true) && !is_dir($temporaryRoot)) {
    throw new RuntimeException('Unable to create the test directory.');
}
putenv('CHESSBOARD_DB_PATH=' . $databasePath);
putenv('CHESSBOARD_STORAGE_PATH=' . $storagePath);
putenv('CHESSBOARD_LOG_PATH=' . $logPath);
putenv('CHESSBOARD_DEBUG=1');

$config = require dirname(__DIR__) . '/src/bootstrap.php';
$_SESSION = [];

$tests = 0;
$failures = [];

function check(bool $condition, string $label): void
{
    global $tests, $failures;
    $tests++;
    if ($condition) {
        fwrite(STDOUT, "✓ {$label}\n");

        return;
    }

    $failures[] = $label;
    fwrite(STDOUT, "✗ {$label}\n");
}

try {
    $installer = new Installer($config);
    check($installer->status() === Installer::STATUS_SETUP, 'a fresh copy requests browser setup');
    check($installer->missingRequirements() === [], 'browser setup requirements are available');
    $invalidSetupRejected = false;
    try {
        $installer->install('x', 'short');
    } catch (RuntimeException) {
        $invalidSetupRejected = !is_file($databasePath);
    }
    check($invalidSetupRejected, 'browser setup rejects weak credentials before writing files');
    $installation = $installer->install('webadmin', 'browser-setup-password');
    check($installation['migrations'] === ['001_initial', '002_remove_client_tracking'], 'browser setup applies all migrations');
    check(
        is_file($databasePath) && is_dir($storagePath . '/original'),
        'browser setup creates the database and storage',
    );
    check($installer->status() === Installer::STATUS_INSTALLED, 'browser setup locks after creating an administrator');

    $setupBlocked = false;
    try {
        $installer->install('attacker', 'another-browser-password');
    } catch (RuntimeException $error) {
        $setupBlocked = $error->getMessage() === 'Chessboard Lite is already installed.';
    }
    check($setupBlocked, 'browser setup cannot create another administrator after installation');

    $database = new Database($config);
    check($database->migrate() === [], 'migrations are repeatable');
    check($database->pdo()->query('PRAGMA foreign_keys')->fetchColumn() === 1, 'SQLite foreign keys are enabled');
    $postColumns = array_column($database->pdo()->query("PRAGMA table_info('posts')")->fetchAll(), 'name');
    $reportColumns = array_column($database->pdo()->query("PRAGMA table_info('reports')")->fetchAll(), 'name');
    check(
        !in_array('ip_hash', $postColumns, true) &&
        !in_array('password_hash', $postColumns, true) &&
        !in_array('reporter_ip_hash', $reportColumns, true),
        'the public schema stores no IP-derived identity or deletion password',
    );
    $legacyTables = $database->pdo()->query(
        "SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ('bans', 'rate_limits')"
    )->fetchAll(PDO::FETCH_COLUMN);
    check($legacyTables === [], 'ban and IP-backed rate-limit tables are absent');

    $boards = new BoardRepository($database);
    $posts = new PostRepository($database);
    $moderation = new ModerationRepository($database);
    $markup = new Markup($config);

    $board = $boards->find('chess');
    check($boards->find('CHESS')['id'] === $board['id'], 'boards are found case-insensitively');

    try {
        $database->immediate(static function (PDO $pdo): void {
            $query = $pdo->prepare(
                'INSERT INTO boards (slug, title, description, created_at)
                 VALUES (:slug, :title, :description, :created_at)'
            );
            $query->execute([
                'slug' => 'rollback',
                'title' => 'Rollback',
                'description' => '',
                'created_at' => time(),
            ]);
            throw new RuntimeException('intentional rollback');
        });
    } catch (RuntimeException $error) {
        check($error->getMessage() === 'intentional rollback', 'transaction errors are propagated');
    }
    check(!$boards->exists('rollback'), 'failed writes are rolled back');

    $thread = $posts->create(
        $board,
        null,
        'Sicilian study',
        'KnightFork',
        "What is the best continuation?\n[pgn]1. e4 c5 2. Nf3[/pgn]",
        null,
        [],
    );
    check($thread['post_no'] === 1 && $thread['thread_no'] === 1, 'a thread receives board-local post number 1');

    $replyBody = 'I prefer 2...d6. >>1 <script>alert(1)</script>';
    $reply = $posts->create(
        $board,
        1,
        '',
        'Anonymous',
        $replyBody,
        null,
        $markup->references($replyBody, 'chess'),
    );
    check($reply['post_no'] === 2 && $reply['thread_no'] === 1, 'a reply advances the board-local counter');

    $rendered = $markup->render($replyBody, 'chess');
    check(str_contains($rendered, 'class="post-reference"'), 'post references become links');
    check(!str_contains($rendered, '<script>') && str_contains($rendered, '&lt;script&gt;'), 'post HTML is escaped');
    check(str_contains($markup->render('[pgn]1. d4 Nf6[/pgn]', 'chess'), 'class="pgn"'), 'PGN blocks are rendered safely');
    check(
        str_contains($markup->render(">candidate move\nordinary text", 'chess'), '<span class="quote">&gt;candidate move</span>'),
        'traditional quote lines receive greentext styling',
    );

    $loadedThread = $posts->thread((int) $board['id'], 1);
    check(count($loadedThread['posts']) === 2, 'a complete thread can be loaded');
    check(
        (int) $loadedThread['posts'][0]['backlinks'][0]['post_no'] === 2,
        'quoted posts receive backlinks',
    );

    $moderator = $moderation->moderatorByUsername('webadmin');
    $moderatorId = (int) $moderator['id'];
    check(
        $moderatorId > 0 && password_verify('browser-setup-password', $moderator['password_hash']),
        'browser setup stores a securely hashed administrator password',
    );
    $firstReportId = $moderation->createReport((int) $reply['id'], 'spam');
    $secondReportId = $moderation->createReport((int) $reply['id'], 'duplicate report');
    check($firstReportId > 0 && $secondReportId > $firstReportId, 'reports are stored without reporter identity');
    check(count($moderation->openReports()) === 2, 'open reports appear in moderation');

    if (extension_loaded('gd') && extension_loaded('fileinfo')) {
        $sourcePath = $temporaryRoot . '/source.png';
        $source = imagecreatetruecolor(640, 360);
        $light = imagecolorallocate($source, 240, 217, 181);
        $dark = imagecolorallocate($source, 181, 136, 99);
        imagefill($source, 0, 0, $light);
        imagefilledrectangle($source, 0, 0, 319, 179, $dark);
        imagepng($source, $sourcePath);

        $uploads = new UploadService($config);
        $attachment = $uploads->process([
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $sourcePath,
            'name' => '../../position.png',
        ]);
        check(
            is_array($attachment) &&
            $attachment['width'] === 640 &&
            $attachment['thumb_width'] <= 320 &&
            $attachment['original_name'] === 'position.png',
            'images are decoded, renamed, re-encoded, and thumbnailed',
        );
        check(
            is_file($storagePath . '/original/' . $attachment['stored_name']) &&
            is_file($storagePath . '/thumb/' . $attachment['thumb_name']),
            'processed images are stored outside the public directory',
        );
        $uploads->remove($attachment);
        check(
            !is_file($storagePath . '/original/' . $attachment['stored_name']) &&
            !is_file($storagePath . '/thumb/' . $attachment['thumb_name']),
            'processed image files can be removed',
        );
    } else {
        fwrite(STDOUT, "– image processing skipped because GD or fileinfo is unavailable\n");
    }

    $attachment = $posts->softDelete((int) $reply['id']);
    $deleted = $posts->find((int) $board['id'], 2);
    check($attachment === null && (int) $deleted['is_deleted'] === 1 && $deleted['body'] === '', 'soft deletion removes post content');

    $csrf = new Csrf();
    $token = $csrf->token();
    $csrf->validate($token);
    $badCsrf = false;
    try {
        $csrf->validate('not-the-token');
    } catch (HttpException $error) {
        $badCsrf = $error->status === 419;
    }
    check($badCsrf, 'CSRF tokens reject invalid submissions');

    $application = new Application($config);
    $response = $application->handle(new Request(
        'GET',
        '/chess/',
        [],
        [],
        [],
        [],
    ));
    check($response->status() === 200, 'the public board route returns HTTP 200');
    check(
        str_contains($response->body(), 'Sicilian study') &&
        str_contains($response->body(), 'What is the best continuation?'),
        'the board page renders persisted content',
    );
    check(
        str_contains($response->body(), 'data-post-form-toggle') &&
        str_contains($response->body(), '>New</button>') &&
        str_contains($response->body(), 'id="new-thread-panel"') &&
        !str_contains($response->body(), 'name="password"'),
        'the board renders a collapsed New control and password-free compact form',
    );
    check(
        str_contains($response->body(), 'id="style-switcher"') &&
        str_contains($response->body(), '<option value="dark">Dark</option>'),
        'the board includes built-in style choices',
    );
    check(
        str_contains($response->body(), 'class="post post--reply') &&
        str_contains($response->body(), 'class="post-menu"'),
        'the board renders inset replies with header action menus',
    );

    $threadResponse = $application->handle(new Request(
        'GET',
        '/chess/thread/1',
        [],
        [],
        [],
        [],
    ));
    $threadBody = $threadResponse->body();
    $replyFormPosition = strpos($threadBody, '<section id="reply"');
    $originalPosition = strpos($threadBody, 'class="thread__original"');
    $repliesPosition = strpos($threadBody, 'class="thread__replies"');
    check(
        $threadResponse->status() === 200 &&
        $replyFormPosition !== false &&
        $originalPosition !== false &&
        $repliesPosition !== false &&
        $replyFormPosition < $originalPosition &&
        $originalPosition < $repliesPosition &&
        str_contains($threadBody, 'rows="3"'),
        'the reply form stays above the original post and replies',
    );

    $postResponse = $application->handle(new Request(
        'POST',
        '/chess/threads/',
        [],
        [
            '_token' => $token,
            'website' => '',
            'name' => 'HTTP Tester',
            'subject' => 'Endgame study',
            'body' => 'White to move and draw.',
        ],
        [],
        [],
    ));
    check($postResponse->status() === 303, 'a valid HTTP form submission redirects after posting');
    check(
        $posts->find((int) $board['id'], 3)['subject'] === 'Endgame study',
        'the routed form submission is persisted in SQLite',
    );
} catch (Throwable $error) {
    $failures[] = 'uncaught ' . $error::class . ': ' . $error->getMessage();
    fwrite(STDOUT, "✗ " . end($failures) . "\n");
    fwrite(STDOUT, $error->getTraceAsString() . "\n");
}

fwrite(STDOUT, "\n{$tests} checks, " . count($failures) . " failure(s).\n");
if ($failures !== []) {
    exit(1);
}
