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
use Chessboard\Service\RateLimiter;
use Chessboard\Service\UploadService;
if (PHP_SAPI !== 'cli') {
    echo "Run this test suite with PHP's command-line executable.\n";
    exit(1);
}

$temporaryRoot = dirname(__DIR__) . '/var/.test-' . bin2hex(random_bytes(6));
$databasePath = $temporaryRoot . '/test.sqlite';
$storagePath = $temporaryRoot . '/uploads';
$keyPath = $temporaryRoot . '/app.key';
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
putenv('CHESSBOARD_KEY_PATH=' . $keyPath);
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
    check($installation['migrations'] === ['001_initial'], 'browser setup applies the initial migration');
    check(
        is_file($databasePath) && is_file($keyPath) && is_dir($storagePath . '/original'),
        'browser setup creates the database, application key, and storage',
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
        password_hash('thread-delete-password', PASSWORD_DEFAULT),
        'author-hash',
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
        'reply-hash',
        null,
        $markup->references($replyBody, 'chess'),
    );
    check($reply['post_no'] === 2 && $reply['thread_no'] === 1, 'a reply advances the board-local counter');

    $rendered = $markup->render($replyBody, 'chess');
    check(str_contains($rendered, 'class="post-reference"'), 'post references become links');
    check(!str_contains($rendered, '<script>') && str_contains($rendered, '&lt;script&gt;'), 'post HTML is escaped');
    check(str_contains($markup->render('[pgn]1. d4 Nf6[/pgn]', 'chess'), 'class="pgn"'), 'PGN blocks are rendered safely');

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
    check($moderation->createReport((int) $reply['id'], 'reporter-hash', 'spam'), 'a report can be opened');
    check(
        !$moderation->createReport((int) $reply['id'], 'reporter-hash', 'duplicate'),
        'duplicate open reports are suppressed',
    );
    check(count($moderation->openReports()) === 1, 'open reports appear in moderation');

    $banId = $moderation->createBan(
        'reply-hash',
        'Automated test',
        time() + 3600,
        $moderatorId,
    );
    check($banId > 0 && $moderation->isBanned('reply-hash') !== null, 'active bans are enforced');
    $moderation->deleteBan($banId);
    check($moderation->isBanned('reply-hash') === null, 'bans can be removed');

    $limiter = new RateLimiter($database);
    $limiter->assertAllowed('test-client', 'post', 60, 2);
    $limiter->assertAllowed('test-client', 'post', 60, 2);
    $limited = false;
    try {
        $limiter->assertAllowed('test-client', 'post', 60, 2);
    } catch (HttpException $error) {
        $limited = $error->status === 429;
    }
    check($limited, 'SQLite-backed rate limiting blocks excess writes');

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
        ['REMOTE_ADDR' => '127.0.0.1'],
    ));
    check($response->status() === 200, 'the public board route returns HTTP 200');
    check(
        str_contains($response->body(), 'Sicilian study') &&
        str_contains($response->body(), 'What is the best continuation?'),
        'the board page renders persisted content',
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
            'password' => 'test-deletion-password',
        ],
        [],
        ['REMOTE_ADDR' => '192.0.2.25'],
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
