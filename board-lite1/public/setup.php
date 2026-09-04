<?php

declare(strict_types=1);

use Chessboard\Http\Request;
use Chessboard\Installer;
use Chessboard\Security\Csrf;
use Chessboard\Security\Session;

function setup_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setup_headers(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header(
        "Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; " .
        "script-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'"
    );
}

try {
    $config = require dirname(__DIR__) . '/src/bootstrap.php';
    $request = Request::fromGlobals($config->basePath());
    Session::start($config, $request->isSecure());
    $csrf = new Csrf();
    $installer = new Installer($config);
    $status = $installer->status();
    $requirements = $installer->requirements();
    $errors = [];
    $username = 'admin';

    if ($request->method === 'POST') {
        $username = $request->input('username', 'admin');
        try {
            $csrf->validate($request->input('_token'));
            if ($status === Installer::STATUS_INSTALLED) {
                throw new RuntimeException('Chessboard Lite is already installed.');
            }
            if ($status === Installer::STATUS_REPAIR) {
                throw new RuntimeException(
                    'This installation needs repair. Use the command-line doctor for details.',
                );
            }

            $password = $request->input('password');
            if (!hash_equals($password, $request->input('password_confirm'))) {
                throw new RuntimeException('The two password entries do not match.');
            }

            $installer->install($username, $password);
            session_regenerate_id(true);
            Session::flash('success', 'Setup complete. Sign in with the administrator account you created.');
            header('Location: ' . $config->basePath() . '/');
            exit;
        } catch (Throwable $error) {
            $errors[] = $error->getMessage();
            $status = $installer->status();
        }
    }

    setup_headers();
} catch (Throwable $error) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Chessboard Lite setup could not start.\n\n";
    echo $error->getMessage() . "\n";
    exit;
}

$basePath = $config->basePath();
$missingRequirements = array_filter(
    $requirements,
    static fn (array $requirement): bool => !$requirement['ok'],
);
$canInstall = $status === Installer::STATUS_SETUP && $missingRequirements === [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Set up Chessboard Lite</title>
    <link rel="stylesheet" href="<?= setup_escape($basePath . '/assets/app.css') ?>">
</head>
<body>
<header class="site-header">
    <div class="shell site-header__inner">
        <span class="brand">
            <span class="brand__mark" aria-hidden="true">♞</span>
            <strong>Chessboard Lite</strong>
            <small>First-run setup</small>
        </span>
    </div>
</header>

<main class="shell page setup-page">
    <section class="auth-card setup-card">
        <div class="auth-card__mark" aria-hidden="true">♔</div>

        <?php if ($status === Installer::STATUS_INSTALLED): ?>
            <p class="eyebrow">Ready to play</p>
            <h1>Already installed</h1>
            <p>The database and administrator account are ready. Browser setup is now locked.</p>
            <div class="setup-actions">
                <a class="button" href="<?= setup_escape($basePath . '/') ?>">Open the site</a>
                <a class="button button--quiet" href="<?= setup_escape($basePath . '/mod/login') ?>">Moderator login</a>
            </div>
        <?php elseif ($status === Installer::STATUS_REPAIR): ?>
            <p class="eyebrow">Setup paused</p>
            <h1>Repair required</h1>
            <p>
                An existing database or administrator was found, but the installation is incomplete.
                Browser setup will not overwrite it.
            </p>
            <div class="setup-message setup-message--error">
                From the project directory, run <code>php bin/doctor.php</code> for details.
            </div>
        <?php else: ?>
            <p class="eyebrow">One short step</p>
            <h1>Create your administrator</h1>
            <p>
                SQLite, the application key, upload folders, and the initial
                <code>/chess/</code> board will be created automatically.
            </p>

            <?php foreach ($errors as $error): ?>
                <div class="setup-message setup-message--error" role="alert">
                    <?= setup_escape($error) ?>
                </div>
            <?php endforeach; ?>

            <ul class="setup-checks" aria-label="Server requirements">
                <?php foreach ($requirements as $requirement): ?>
                    <li class="<?= $requirement['ok'] ? 'is-ready' : 'is-missing' ?>">
                        <span aria-hidden="true"><?= $requirement['ok'] ? '✓' : '×' ?></span>
                        <span><?= setup_escape($requirement['label']) ?></span>
                        <small><?= setup_escape($requirement['detail']) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($missingRequirements !== []): ?>
                <div class="setup-message setup-message--error" role="alert">
                    Enable the missing PHP requirements, restart the server, and reload this page.
                </div>
            <?php endif; ?>

            <form class="stack-form" action="<?= setup_escape($basePath . '/setup.php') ?>" method="post">
                <input type="hidden" name="_token" value="<?= setup_escape($csrf->token()) ?>">
                <label>
                    <span>Administrator username</span>
                    <input
                        type="text"
                        name="username"
                        value="<?= setup_escape($username) ?>"
                        minlength="3"
                        maxlength="32"
                        pattern="[a-zA-Z0-9_-]{3,32}"
                        autocomplete="username"
                        required
                    >
                </label>
                <label>
                    <span>Password <small>at least 12 characters</small></span>
                    <input
                        type="password"
                        name="password"
                        minlength="12"
                        maxlength="128"
                        autocomplete="new-password"
                        required
                    >
                </label>
                <label>
                    <span>Confirm password</span>
                    <input
                        type="password"
                        name="password_confirm"
                        minlength="12"
                        maxlength="128"
                        autocomplete="new-password"
                        required
                    >
                </label>
                <button class="button" type="submit"<?= $canInstall ? '' : ' disabled' ?>>
                    Create site
                </button>
            </form>
        <?php endif; ?>
    </section>
</main>

<footer class="site-footer">
    <div class="shell site-footer__inner">
        <span>Chessboard Lite — PHP 8.5 + SQLite 3</span>
    </div>
</footer>
</body>
</html>
