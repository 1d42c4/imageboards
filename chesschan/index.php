<?php
require_once 'db.php';

$db = get_db();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * THREADS_PER_PAGE;

// Get threads (OPs only)
$stmt = $db->prepare("
    SELECT * FROM posts 
    WHERE parent = 0 
    ORDER BY sticky DESC, created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([THREADS_PER_PAGE, $offset]);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total threads for pagination
$total = $db->query("SELECT COUNT(*) FROM posts WHERE parent = 0")->fetchColumn();
$total_pages = max(1, ceil($total / THREADS_PER_PAGE));

function get_replies($db, $thread_id, $limit = REPLIES_SHOWN) {
    $stmt = $db->prepare("
        SELECT * FROM posts 
        WHERE parent = ? 
        ORDER BY id ASC
    ");
    $stmt->execute([$thread_id]);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = count($all);
    if ($count <= $limit) return [$all, 0];
    return [array_slice($all, -$limit), $count - $limit];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>/<?= BOARD_NAME ?>/ - <?= BOARD_TITLE ?></title>
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
        <a href="#postform">[Post]</a>
        <a href="index.php">[Catalog]</a>
    </nav>

    <header class="board-header">
        <h1>/<?= BOARD_NAME ?>/ - <?= BOARD_TITLE ?></h1>
        <div class="subtitle"><?= BOARD_DESC ?></div>
    </header>

    <div class="post-form-container" id="postform">
        <?php if (isset($_GET['error'])): ?>
            <div class="error"><?= clean($_GET['error']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="success">Post submitted successfully.</div>
        <?php endif; ?>

        <form class="post-form" action="post.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="parent" value="0">
            <table>
                <tr>
                    <td class="label">Name</td>
                    <td><input type="text" name="name" maxlength="<?= MAX_NAME ?>" placeholder="Anonymous"></td>
                </tr>
                <tr>
                    <td class="label">Subject</td>
                    <td><input type="text" name="subject" maxlength="<?= MAX_SUBJECT ?>"></td>
                </tr>
                <tr>
                    <td class="label">Comment</td>
                    <td><textarea name="comment" required maxlength="<?= MAX_COMMENT ?>" placeholder="Say something about chess..."></textarea></td>
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
                        <button type="submit" class="btn btn-primary">Create Thread</button>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <div class="threads">
        <?php if (empty($threads)): ?>
            <p style="text-align:center;color:var(--text-muted);padding:40px;">No threads yet. Be the first to post.</p>
        <?php endif; ?>

        <?php foreach ($threads as $op): ?>
            <?php
            list($replies, $omitted) = get_replies($db, $op['id']);
            ?>
            <div class="thread" id="t<?= $op['id'] ?>">
                <!-- OP -->
                <div class="post op" id="p<?= $op['id'] ?>">
                    <?php if ($op['filename']): ?>
                        <div class="file">
                            <a href="uploads/<?= clean($op['filename']) ?>" target="_blank">
                                <img src="uploads/<?= clean($op['filename']) ?>" alt="image" loading="lazy">
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
                            <span class="date"><?= date('Y-m-d H:i:s', $op['created_at']) ?> (<?= time_ago($op['created_at']) ?>)</span>
                            <span class="postnum">
                                <a href="thread.php?id=<?= $op['id'] ?>#p<?= $op['id'] ?>">No.<?= $op['id'] ?></a>
                                <a href="thread.php?id=<?= $op['id'] ?>">[Reply]</a>
                            </span>
                        </div>
                        <div class="comment"><?= format_comment($op['comment']) ?></div>
                    </div>
                </div>

                <?php if ($omitted > 0): ?>
                    <div class="omitted"><?= $omitted ?> post<?= $omitted > 1 ? 's' : '' ?> omitted. Click <a href="thread.php?id=<?= $op['id'] ?>">Reply</a> to view.</div>
                <?php endif; ?>

                <div class="replies">
                    <?php foreach ($replies as $r): ?>
                        <div class="post" id="p<?= $r['id'] ?>">
                            <?php if ($r['filename']): ?>
                                <div class="file">
                                    <a href="uploads/<?= clean($r['filename']) ?>" target="_blank">
                                        <img src="uploads/<?= clean($r['filename']) ?>" alt="image" loading="lazy">
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
                                        <a href="thread.php?id=<?= $op['id'] ?>#p<?= $r['id'] ?>">No.<?= $r['id'] ?></a>
                                    </span>
                                </div>
                                <div class="comment"><?= format_comment($r['comment']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
        <div style="text-align:center;padding:20px;color:var(--text-muted);">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <strong>[<?= $i ?>]</strong>
                <?php else: ?>
                    <a href="?page=<?= $i ?>">[<?= $i ?>]</a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

    <footer class="footer">
        Chesschan · Lightweight PHP imageboard inspired by jschan<br>
        No accounts · No tracking · Just chess discussion
    </footer>
</body>
</html>
