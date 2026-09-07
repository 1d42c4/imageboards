<?php
declare(strict_types=1);
// Integration tests use a newly created, isolated installation. Never point this at live data.
if (PHP_SAPI !== 'cli') { exit; }
ob_start();
$source = dirname(__DIR__);
$sandbox = sys_get_temp_dir() . '/vichan-modern-test-' . bin2hex(random_bytes(6));
mkdir($sandbox, 0700, true);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $relative = substr($file->getPathname(), strlen($source) + 1);
    $relative = str_replace('\\', '/', $relative);
    if (str_starts_with($relative, 'var/') || str_starts_with($relative, 'tests/') || (str_starts_with($relative, 'public/') && str_ends_with($relative, '.html'))) { continue; }
    $destination = $sandbox . '/' . $relative;
    if (!is_dir(dirname($destination))) { mkdir(dirname($destination), 0700, true); }
    copy($file->getPathname(), $destination);
}
putenv('VICHAN_ADMIN_USER=admin');
putenv('VICHAN_ADMIN_PASSWORD=Test-Only-Admin-Password!');
$pipes = [];
$install = proc_open([PHP_BINARY, $sandbox . '/bin/install.php'], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, options: ['bypass_shell' => true]);
fclose($pipes[0]); $installOutput = stream_get_contents($pipes[1]); $installError = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
if (proc_close($install) !== 0) { throw new RuntimeException('Installation failed: ' . $installError); }
putenv('VICHAN_ADMIN_PASSWORD'); putenv('VICHAN_ADMIN_USER');
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
$address = stream_socket_get_name($socket, false); fclose($socket);
$base = 'http://' . $address;
$server = proc_open([PHP_BINARY, '-d', 'upload_max_filesize=32M', '-d', 'post_max_size=136M', '-d', 'memory_limit=256M', '-S', $address, '-t', $sandbox . '/public', $sandbox . '/router.php'], [0 => ['pipe','r'], 1 => ['file',$sandbox . '/server.out','a'], 2 => ['file',$sandbox . '/server.err','a']], $pipes, $sandbox, options: ['bypass_shell' => true]);
fclose($pipes[0]);
/** @var \VichanModern\App $app */
$app = require $sandbox . '/bootstrap.php';
$checks = [];
function check(bool $condition, string $description): void { global $checks; if (!$condition) { throw new RuntimeException('FAIL: ' . $description); } $checks[] = $description; echo 'PASS: ' . $description . "\n"; }
function client(): CurlHandle { $c = curl_init(); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]); return $c; }
/** @return array{status:int,body:string,headers:string} */
function request(CurlHandle $c, string $path, ?array $data = null, array $headers = []): array
{
    global $base;
    curl_setopt_array($c, [CURLOPT_URL => $base . $path, CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $data === null ? 'GET' : 'POST', CURLOPT_POSTFIELDS => $data === null ? null : $data]);
    $raw = curl_exec($c);
    if ($raw === false) { throw new RuntimeException(curl_error($c)); }
    $headerLength = curl_getinfo($c, CURLINFO_HEADER_SIZE);
    return ['status' => curl_getinfo($c, CURLINFO_RESPONSE_CODE), 'body' => substr($raw, $headerLength), 'headers' => substr($raw, 0, $headerLength)];
}
function token(CurlHandle $c): string { $r = request($c, '/session.php'); return json_decode($r['body'], true, flags: JSON_THROW_ON_ERROR)['csrf']; }
function challenge(CurlHandle $c): string
{
    global $sandbox;
    $r = request($c, '/captcha.php');
    if ($r['status'] !== 200 || !str_starts_with($r['body'], "\x89PNG")) { throw new RuntimeException('CAPTCHA PNG failed: ' . $r['body']); }
    $id = null;
    foreach (curl_getinfo($c, CURLINFO_COOKIELIST) as $cookie) { $parts = explode("\t", $cookie); if ($parts[5] === 'vichan_modern') { $id = $parts[6]; } }
    if ($id === null) { throw new RuntimeException('No session cookie.'); }
    session_save_path($sandbox . '/var/sessions'); session_id($id); session_start();
    // This fixture is written only into this isolated test session; the shipped application has no CAPTCHA bypass.
    $_SESSION['captcha_hash'] = hash('sha256', 'ABC234'); $_SESSION['captcha_until'] = time() + 300;
    session_write_close();
    return 'ABC234';
}
function post(CurlHandle $c, array $fields = [], array $headers = [], bool $reset = true): array
{
    global $app;
    if ($reset) { $app->db->execute('DELETE FROM throttle'); }
    return request($c, '/post.php', array_replace(['csrf' => token($c), 'board' => 'chess', 'thread' => '0', 'name' => 'Tester', 'subject' => 'Test topic', 'body' => 'Test comment ' . bin2hex(random_bytes(4)), 'password' => 'delete-test-password', 'captcha' => challenge($c), 'json_response' => '1'], $fields), $headers);
}
function mod(CurlHandle $c, array $fields): array { return request($c, '/mod.php', ['csrf' => token($c)] + $fields); }
function countPosts(): int { global $app; return (int) $app->db->one('SELECT COUNT(*) AS n FROM posts')['n']; }
try {
    $anon = client();
    for ($attempt = 0; $attempt < 40; $attempt++) { try { $r = request($anon, '/'); if ($r['status'] === 200) { break; } } catch (Throwable) {} usleep(100000); }
    check($r['status'] === 200 && str_contains($r['body'], 'Chessboard'), 'Fresh install serves the static home page');
    check(str_contains($r['headers'], "script-src 'self'") && str_contains(strtolower($r['headers']), 'x-content-type-options: nosniff'), 'Static HTML has CSP and MIME protection');
    $r = request($anon, '/session.php');
    check(str_contains(strtolower($r['headers']), 'httponly') && str_contains(strtolower($r['headers']), 'samesite=lax') && str_contains(strtolower($r['headers']), 'no-store'), 'Session cookies and private response caching are configured');
    foreach (['/config.php','/bootstrap.php','/schema.sql','/var/board.sqlite','/var/first-login.txt','/src/App.php','/bin/install.php','/tests/run.php','/.git/config','/%2e%2e/config.php','/static/evil.php','/post.php/extra'] as $path) { check(request($anon, $path)['status'] === 404, 'Private path blocked: ' . $path); }
    check(request($anon, '/post.php')['status'] === 405, 'GET cannot create a post');
    $before = countPosts();
    check(post($anon, ['csrf' => 'wrong'])['status'] === 403 && countPosts() === $before, 'CSRF rejection leaves database unchanged');
    check(post($anon, [], ['Sec-Fetch-Site: cross-site'])['status'] === 403, 'Cross-site submission is rejected');
    check(post($anon, ['captcha' => 'WRONG'])['status'] === 400, 'Incorrect CAPTCHA is rejected');
    check(post($anon, ['website' => 'spam'])['status'] === 400, 'Honeypot submission is rejected');
    check(post($anon, ['board' => '../var'])['status'] === 400, 'Board path traversal is rejected');
    check(post($anon, ['thread' => '-1'])['status'] === 400, 'Negative thread identifier is rejected');
    check(post($anon, ['body' => str_repeat('a', 30001)])['status'] === 400, 'Oversized post text is rejected');
    $r = post($anon, ['body' => '**Chess article**\n<script>alert(1)</script>\nhttps://example.org/\n[spoiler]mate[/spoiler]', 'subject' => '"><img src=x onerror=alert(1)>'], ['X-Forwarded-For: 192.0.2.88', 'Forwarded: for=192.0.2.88']);
    check($r['status'] === 200, 'Anonymous article creates a thread: ' . $r['body']);
    $thread = json_decode($r['body'], true)['id'];
    $html = request($anon, '/chess/res/' . $thread . '.html')['body'];
    check(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>alert') && !str_contains($html, '<img src=x'), 'Post text and subject cannot inject HTML');
    check(str_contains($html, '<strong>Chess article</strong>') && str_contains($html, 'rel="nofollow noreferrer noopener ugc"'), 'Article formatting and safe links render');
    $app->db->execute('DELETE FROM throttle');
    $r = request($anon, '/post.php', ['csrf'=>token($anon),'board'=>'chess','body'=>'Replay','captcha'=>'ABC234','json_response'=>'1']);
    check($r['status'] === 400, 'Consumed CAPTCHA cannot be replayed');
    $r = post($anon, ['thread' => (string) $thread, 'body' => '>>' . $thread . ' A reply']);
    check($r['status'] === 200, 'Text reply succeeds');
    $reply = json_decode($r['body'], true)['id'];
    check(str_contains(request($anon, '/chess/res/' . $thread . '.html')['body'], 'A reply'), 'Reply regenerates its static thread');
    check(request($anon, '/read.php?board=chess&post=' . $reply)['status'] === 303, 'Quoted-post permalink resolves');
    $app->db->execute('INSERT INTO boards(slug,title,subtitle) VALUES(?,?,?)', ['other','Other','Test']);
    check(post($anon, ['board'=>'other','thread'=>(string)$thread])['status'] === 404, 'Cross-board reply is rejected');
    $r = post($anon, ['files[0]' => new CURLFile($source . '/tests/fixtures/chess.png','image/png','board.png'), 'files[1]' => new CURLFile($source . '/tests/fixtures/chess.gif','image/gif','animation.gif'), 'files[2]' => new CURLFile($source . '/tests/fixtures/chess.mp4','video/mp4','clip.mp4')]);
    check($r['status'] === 200, 'PNG, animated GIF and MP4 upload together: ' . $r['body']);
    $mediaPost = json_decode($r['body'], true)['id'];
    $files = $app->db->all('SELECT * FROM files WHERE post_id=? ORDER BY id', [$mediaPost]);
    check(count($files) === 3, 'Multiple attachments are stored');
    $gif = $files[1]; $video = $files[2];
    check(hash_file('sha256',$sandbox.'/var/media/'.$gif['token']) === hash_file('sha256',$source.'/tests/fixtures/chess.gif'), 'Animated GIF data remains intact');
    check(request($anon, '/media.php?id=' . $files[0]['token'] . '&thumb=1')['status'] === 200, 'Generated image thumbnail is served');
    $r = request($anon, '/media.php?id=' . $video['token'], headers:['Range: bytes=0-99']);
    check($r['status'] === 206 && strlen($r['body']) === 100 && str_contains($r['headers'],'Content-Range: bytes 0-99/'), 'MP4 byte ranges support seeking');
    check(request($anon, '/media.php?id=' . $video['token'], headers:['Range: bytes=999999999-'])['status'] === 416, 'Unsatisfiable video range is rejected');
    check(request($anon, '/media.php?id=../../config.php')['status'] === 404, 'Media path traversal is rejected');
    file_put_contents($sandbox.'/fake.png','<?php echo "evil";');
    check(post($anon, ['files[0]'=>new CURLFile($sandbox.'/fake.png','image/png','fake.png')])['status'] === 400, 'PHP disguised as a PNG is rejected');
    check(post($anon, ['files[0]'=>new CURLFile($source.'/tests/fixtures/chess.png','image/png','image.php')])['status'] === 400, 'Executable upload extension is rejected');
    file_put_contents($sandbox.'/bad.mp4', pack('N',24).'ftypisom'.str_repeat("\0",12).pack('N',999999999).'moov');
    check(post($anon, ['files[0]'=>new CURLFile($sandbox.'/bad.mp4','video/mp4','bad.mp4')])['status'] === 400, 'Malformed MP4 box lengths are rejected');
    $fields=[]; for($i=0;$i<5;$i++){$fields['files['.$i.']']=new CURLFile($source.'/tests/fixtures/chess.png','image/png','x.png');}
    check(post($anon,$fields)['status']===400,'Excess attachment count is rejected');
    $r = request($anon, '/search.php?q=' . rawurlencode('Chess article'));
    check($r['status'] === 200 && str_contains($r['body'], 'Chess article'), 'Search finds article text');
    check(request($anon, '/search.php?q=' . rawurlencode("' OR 1=1 --"))['status'] === 200, 'SQL-like search input is treated as text');
    $r = request($anon, '/action.php', ['csrf'=>token($anon),'action'=>'report','post_id'=>(string)$thread,'reason'=>'Please review this test post.','json_response'=>'1']);
    check($r['status']===200,'Anonymous reporting works without network identifiers');
    check(request($anon,'/mod.php?view=staff')['status']===200 && !str_contains(request($anon,'/mod.php?view=staff')['body'],'Create staff account'),'Anonymous visitors cannot see staff management');
    check(mod($anon,['action'=>'delete','id'=>(string)$thread,'confirm'=>(string)$thread])['status']===401,'Anonymous moderator action is rejected');
    $admin=client(); $r=mod($admin,['action'=>'login','username'=>'admin','password'=>'wrong']);
    check($r['status']===403,'Wrong staff password is rejected');
    $oldToken=token($admin); $r=mod($admin,['action'=>'login','username'=>'admin','password'=>'Test-Only-Admin-Password!']);
    check($r['status']===303 && token($admin)!==$oldToken,'Staff sign-in rotates the session and CSRF token');
    check(str_contains(request($admin,'/mod.php')['body'],'Report queue (1)'),'Moderator dashboard shows reports');
    foreach(['posts','reports','boards','staff','log','settings','password'] as $view){check(request($admin,'/mod.php?view='.$view)['status']===200,'Moderator page renders: '.$view);}
    check(mod($admin,['action'=>'edit','id'=>(string)$reply,'name'=>'Editor','subject'=>'Edited','body'=>'Updated text'])['status']===303,'Moderator can edit a reply');
    check(str_contains(request($anon,'/chess/res/'.$thread.'.html')['body'],'Updated text'),'Edited reply appears in static HTML');
    check(mod($admin,['action'=>'sticky','id'=>(string)$thread])['status']===303 && (int)$app->boards->post($thread)['sticky']===1,'Thread can be made sticky');
    check(mod($admin,['action'=>'lock','id'=>(string)$thread])['status']===303,'Thread can be locked');
    check(post($anon,['thread'=>(string)$thread])['status']===403,'Locked thread rejects replies');
    check(mod($admin,['action'=>'lock','id'=>(string)$thread])['status']===303,'Thread can be unlocked');
    check(mod($admin,['action'=>'board-save','slug'=>'analysis','title'=>'Analysis','subtitle'=>'Game studies'])['status']===303,'Administrator creates a board');
    check(request($anon,'/analysis/index.html')['status']===200,'New board gets a static index');
    $analysis=$app->boards->board('analysis');
    check(mod($admin,['action'=>'board-save','id'=>(string)$analysis['id'],'title'=>'Analysis','subtitle'=>'Studies','locked'=>'1'])['status']===303,'Board settings can be edited');
    check(post($anon,['board'=>'analysis'])['status']===403,'Read-only board rejects posting');
    $staffFields=['username'=>'janitor','role'=>'janitor','new_password'=>'Test-Janitor-Password!','current_password'=>'Test-Only-Admin-Password!'];
    check(mod($admin,['action'=>'staff-save']+$staffFields)['status']===303,'Administrator creates a janitor');
    $janitor=client(); check(mod($janitor,['action'=>'login','username'=>'janitor','password'=>'Test-Janitor-Password!'])['status']===303,'Janitor can sign in');
    check(request($janitor,'/mod.php?view=staff')['status']===403,'Janitor cannot access staff settings');
    check(mod($janitor,['action'=>'edit','id'=>(string)$reply,'body'=>'Unauthorized edit'])['status']===403,'Janitor cannot edit posts');
    check(mod($janitor,['action'=>'sticky','id'=>(string)$thread])['status']===403,'Janitor cannot change thread flags');
    $report=$app->db->one('SELECT id FROM reports LIMIT 1');
    check(mod($janitor,['action'=>'report-dismiss','id'=>(string)$report['id']])['status']===303,'Janitor can dismiss a report');
    check(mod($admin,['action'=>'staff-delete','id'=>'1','current_password'=>'Test-Only-Admin-Password!'])['status']===400,'Last administrator cannot be deleted');
    check(mod($admin,['action'=>'settings','title'=>'Chess Club','theme'=>'dark'])['status']===303,'Site title and default theme can change');
    check(str_contains(request($anon,'/')['body'],'Chess Club') && str_contains(request($anon,'/')['body'],'stylesheets/dark.css'),'Appearance changes rebuild public pages');
    check(mod($admin,['action'=>'settings','title'=>'Chess','theme'=>'../../evil'])['status']===400,'Theme path traversal is rejected');
    check(mod($admin,['action'=>'delete','id'=>(string)$mediaPost,'confirm'=>'wrong'])['status']===400,'Moderator deletion requires matching confirmation');
    check(mod($janitor,['action'=>'files','id'=>(string)$mediaPost,'confirm'=>(string)$mediaPost])['status']===303,'Janitor can remove attachments');
    check(request($anon,'/media.php?id='.$video['token'])['status']===404 && !is_file($sandbox.'/var/media/'.$video['token']),'Deleted media is removed from disk and cannot be retrieved');
    $app->db->execute('DELETE FROM throttle');
    $r=request($anon,'/action.php',['csrf'=>token($anon),'action'=>'delete','post_id'=>(string)$reply,'password'=>'wrong','json_response'=>'1']);
    check($r['status']===403,'Wrong deletion password is rejected');
    $r=request($anon,'/action.php',['csrf'=>token($anon),'action'=>'delete','post_id'=>(string)$reply,'password'=>'delete-test-password','json_response'=>'1']);
    check($r['status']===200,'Poster can delete using their deletion password');
    check(!str_contains(request($anon,'/chess/res/'.$thread.'.html')['body'],'Updated text'),'Deleted reply disappears from static HTML');
    check(mod($admin,['action'=>'delete','id'=>(string)$thread,'confirm'=>(string)$thread])['status']===303,'Moderator can delete a thread');
    check(request($anon,'/chess/res/'.$thread.'.html')['status']===404,'Deleted thread static file is removed');
    check(mod($admin,['action'=>'board-delete','id'=>(string)$analysis['id'],'confirm'=>'analysis','current_password'=>'Test-Only-Admin-Password!'])['status']===303,'Administrator can delete a board with reauthentication');
    check(request($anon,'/analysis/index.html')['status']===404,'Deleted board pages are removed');
    check(mod($admin,['action'=>'rebuild'])['status']===303,'Manual rebuild works');
    $app->db->execute('DELETE FROM throttle');
    check(post($anon,reset:false)['status']===200 && post($anon,reset:false)['status']===429,'Posting is throttled without an IP address');
    $app->db->execute('DELETE FROM throttle');
    for($i=0;$i<11;$i++){$bad=mod($janitor,['action'=>'login','username'=>'janitor','password'=>'wrong']);}
    check($bad['status']===429,'Staff login brute force is limited by account');
    $app->db->execute('DELETE FROM throttle');
    check(mod($admin,['action'=>'password','current_password'=>'Test-Only-Admin-Password!','new_password'=>'A-New-Test-Admin-Password!'])['status']===303,'Administrator can change password');
    check(!is_file($sandbox.'/var/first-login.txt'),'Initial plaintext credential file is removed after password change');
    check(!str_contains(request($admin,'/mod.php')['body'],'Signed in as'),'Password change invalidates the old staff session');
    check(mod($admin,['action'=>'login','username'=>'admin','password'=>'A-New-Test-Admin-Password!'])['status']===303,'New administrator password works');
    check(mod($admin,['action'=>'logout'])['status']===303,'Staff logout works');
    check(request($anon,'/compose.php?board=chess')['status']===200 && str_contains(request($anon,'/compose.php?board=chess')['body'],'name="csrf" value="'),'Posting form works without JavaScript');
    check(request($anon,'/action.php')['status']===200,'Report and deletion form works without JavaScript');
    $schema=implode('\n',array_column($app->db->all("SELECT sql FROM sqlite_master WHERE type='table'"),'sql'));
    check(!preg_match('/\b(?:ip|ip_address|remote_addr|user_agent|fingerprint|ip_hash|bans)\b/i',$schema),'Database schema contains no network addresses, fingerprints or bans');
    $dump='';foreach($app->db->all("SELECT name FROM sqlite_master WHERE type='table'") as $table){$name=$table['name'];if(preg_match('/\A[a-z_]+\z/D',$name)){$dump.=json_encode($app->db->all('SELECT * FROM '.$name));}}
    check(!str_contains($dump,'192.0.2.88'),'Forwarded network address is never persisted');
    check(!is_file($sandbox.'/var/errors.log'),'No application warnings or exceptions occurred');
    check($app->db->query('PRAGMA integrity_check')->fetchColumn()==='ok','Database integrity is intact');
    echo "\n".count($checks)." checks passed. Isolated test installation: ".$sandbox."\n";
} catch (Throwable $error) {
    echo $error->getMessage()."\nTest installation: ".$sandbox."\n";
    if(is_file($sandbox.'/var/errors.log')){echo file_get_contents($sandbox.'/var/errors.log');}
    $failure=true;
} finally { proc_terminate($server); proc_close($server); ob_end_flush(); }
exit(isset($failure)?1:0);
