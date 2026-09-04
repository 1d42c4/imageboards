<?php
require_once 'db.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

if ($id < 1) {
    header('Location: index.php');
    exit;
}

// Get OP
$stmt = $db->prepare("SELECT * FROM posts WHERE id = ? AND parent = 0");
$stmt->execute([$id]);
$op = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$op) {
    header('Location: index.php');
    exit;
}

// Get all replies
$stmt = $db->prepare("SELECT * FROM posts WHERE parent = ? ORDER BY id ASC");
$stmt->execute([$id]);
$replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $op['subject'] ? clean($op['subject']) . ' - ' : '' ?>/<?= BOARD_NAME ?>/ - <?= BOARD_TITLE ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="topnav">
        <strong style="color:var(--accent)"><?= SITE_NAME ?></strong>
        <span class="sep">|</span>
        <div class="board-list">
            <a href="index.php">/<?= BOARD_NAME ?>/</a>
        </div>
        <span class="sep">|</span>
        <a href="index.php">[Return]</a>
        <a href="#postform">[Reply]</a>
    </nav>

    <header class="board-header">
        <h1>/<?= BOARD_NAME ?>/ - <?= BOARD_TITLE ?></h1>
        <div class="subtitle"><?= BOARD_DESC ?></div>
    </header>

    <div class="threads thread-view">
        <div class="backlink">
            <a href="index.php">← Return to board</a>
        </div>

        <!-- OP -->
        <div class="post op" id="p<?= $op['id'] ?>">
            <?php if ($op['filename']): ?>
                <div class="file">
                    <a href="uploads/<?= clean($op['filename']) ?>" target="_blank">
                        <img src="uploads/<?= clean($op['filename']) ?>" alt="image">
                    </a>
                    <div class="fileinfo">
                        <?= clean($op['original_name']) ?> 
                        (<?= round($op['filesize']/1024) ?> KB, <?= $op['width'] ?>x<?= $op['height'] ?>)
                    </div>
                </div>
            <?php endif; ?>
            <div class="content">
                <div class="meta">
                    <?php if ($op['subject']): ?>
                        <span class="subject"><?= clean($op['subject']) ?></span>
                    <?php endif; ?>
                    <span class="name"><?= clean($op['name'] ?: 'Anonymous') ?></span>
                    <span class="date"><?= date('Y-m-d H:i:s', $op['created_at']) ?></span>
                    <span class="postnum">
                        <a href="#p<?= $op['id'] ?>">No.<?= $op['id'] ?></a>
                    </span>
                </div>
                <div class="comment"><?= format_comment($op['comment']) ?></div>
            </div>
        </div>

        <!-- Replies -->
        <?php foreach ($replies as $r): ?>
            <div class="post" id="p<?= $r['id'] ?>">
                <?php if ($r['filename']): ?>
                    <div class="file">
                        <a href="uploads/<?= clean($r['filename']) ?>" target="_blank">
                            <img src="uploads/<?= clean($r['filename']) ?>" alt="image">
                        </a>
                        <div class="fileinfo">
                            <?= clean($r['original_name']) ?> 
                            (<?= round($r['filesize']/1024) ?> KB)
                        </div>
                    </div>
                <?php endif; ?>
                <div class="content">
                    <div class="meta">
                        <?php if ($r['subject']): ?>
                            <span class="subject"><?= clean($r['subject']) ?></span>
                        <?php endif; ?>
                        <span class="name"><?= clean($r['name'] ?: 'Anonymous') ?></span>
                        <span class="date"><?= date('Y-m-d H:i:s', $r['created_at']) ?></span>
                        <span class="postnum">
                            <a href="#p<?= $r['id'] ?>">No.<?= $r['id'] ?></a>
                        </span>
                    </div>
                    <div class="comment"><?= format_comment($r['comment']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Reply form -->
    <div class="post-form-container" id="postform">
        <?php if (isset($_GET['error'])): ?>
            <div class="error"><?= clean($_GET['error']) ?></div>
        <?php endif; ?>

        <form class="post-form" action="post.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="parent" value="<?= $op['id'] ?>">
            <table>
                <tr>
                    <td class="label">Name</td>
                    <td><input type="text" name="name" maxlength="<?= MAX_NAME ?>" placeholder="Anonymous"></td>
                </tr>
                <tr>
                    <td class="label">Comment</td>
                    <td><textarea name="comment" required maxlength="<?= MAX_COMMENT ?>" placeholder="Reply..."></textarea></td>
                </tr>
                <tr>
                    <td class="label">File</td>
                    <td><input type="file" name="file" accept="image/*"></td>
                </tr>
                <tr>
                    <td class="label">Password</td>
                    <td><input type="password" name="password" maxlength="32" placeholder="For deletion"></td>
                </tr>
                <tr>
                    <td></td>
                    <td class="submit-row">
                        <button type="submit" class="btn btn-primary">Post Reply</button>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <footer class="footer">
        Chesschan · Lightweight PHP imageboard inspired by jschan
    </footer>
</body>
</html>
