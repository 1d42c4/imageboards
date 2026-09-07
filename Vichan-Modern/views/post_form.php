<?php declare(strict_types=1); $dynamic = $dynamic ?? false; ?>
<?php if ((bool) $board['locked'] || ($thread !== null && (bool) $thread['locked'])): ?><p class="center-text">This <?= $thread === null ? 'board' : 'thread' ?> is read-only.</p><?php else: ?>
<form name="post" class="post-form" enctype="multipart/form-data" action="<?= $v->url('post.php') ?>" method="post" data-ajax="post">
<input type="hidden" name="board" value="<?= $v::e($board['slug']) ?>"><input type="hidden" name="thread" value="<?= $v::e($thread['id'] ?? 0) ?>">
<?php if ($dynamic): ?><?= $v->csrf() ?><?php else: ?><input type="hidden" name="csrf" value=""><?php endif ?>
<div class="honeypot" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
<table><tbody>
<tr><th><label for="post-name">Name</label></th><td><input id="post-name" name="name" type="text" size="25" maxlength="50" placeholder="Anonymous" autocomplete="off"></td></tr>
<tr><th><label for="post-subject">Subject</label></th><td><input id="post-subject" name="subject" type="text" size="25" maxlength="120"> <button type="submit"><?= $thread === null ? 'New topic' : 'Reply' ?></button></td></tr>
<tr><th><label for="body">Comment</label></th><td><div class="format-toolbar"><button type="button" data-wrap="**" title="Bold"><strong>B</strong></button><button type="button" data-wrap="__" title="Italic"><em>I</em></button><button type="button" data-wrap="`" title="Code">Code</button><button type="button" data-wrap="[spoiler]" title="Spoiler">Spoiler</button></div><textarea name="body" id="body" rows="7" cols="48" maxlength="30000"></textarea><div class="unimportant">Use &gt;&gt;123 to quote a post. Links become clickable. HTML is displayed as text.</div></td></tr>
<tr><th><label for="upload-file">Files</label></th><td><input id="upload-file" type="file" name="files[]" multiple accept="image/jpeg,image/png,image/gif,image/webp,video/mp4"><div class="unimportant">Up to <?= $v::e($v->app->config->int('max_files')) ?> files, <?= $v::e((int) ($v->app->config->int('max_upload_bytes') / 1048576)) ?> MB each. JPG, PNG, GIF, WebP, MP4.</div><div id="upload-preview"></div></td></tr>
<?php if ($v->app->config->bool('captcha')): ?><tr class="captcha-row"><th><label for="captcha-code">Verification</label></th><td><img class="captcha-image" width="200" height="64" alt="Verification code" <?= $dynamic ? 'src="' . $v->url('captcha.php') . '"' : 'data-captcha="1"' ?>><button type="button" class="refresh-captcha" aria-label="Get a new verification code">↻</button><br><input id="captcha-code" name="captcha" type="text" size="12" maxlength="6" autocomplete="off" required></td></tr><?php endif ?>
<tr><th><label for="post-password">Password</label></th><td><input id="post-password" name="password" type="password" size="20" maxlength="128" autocomplete="new-password"><span class="unimportant"> Optional; at least 8 characters, for deleting your post later.</span></td></tr>
<?php if ($thread !== null): ?><tr><th>Options</th><td><label><input type="checkbox" name="sage" value="1"> Do not bump this thread</label></td></tr><?php endif ?>
<tr><td></td><td><button type="submit"><?= $thread === null ? 'New topic' : 'Reply' ?></button> <span class="form-status" role="status"></span></td></tr>
</tbody></table>
<?php if (!$dynamic): ?><noscript><p class="center-text">To post with JavaScript disabled, <a href="<?= $v->url('compose.php?board=' . rawurlencode($board['slug']) . '&thread=' . ($thread['id'] ?? 0)) ?>">open the posting form</a>.</p></noscript><?php endif ?>
</form><?php endif ?>
