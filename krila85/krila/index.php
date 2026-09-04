<?php
declare(strict_types=1);

/**
 * /krila/ index – PHP 8.5+ compatible
 * - Newest threads on top
 * - Simple pagination (10 per page)
 * - Fully relative paths → works in any subdirectory
 */

function parse_meta_line(string $line): array
{
    $line = trim(str_replace(['[', ']'], '', $line));
    $out = ['name' => '', 'date' => '', 'title' => '', 'include' => ''];
    if (preg_match('/name="([^"]*)"/', $line, $m))   $out['name']    = $m[1];
    if (preg_match('/date="([^"]*)"/', $line, $m))   $out['date']    = $m[1];
    if (preg_match('/title="([^"]*)"/', $line, $m))  $out['title']   = $m[1];
    if (preg_match('/include="([^"]*)"/', $line, $m)) $out['include'] = $m[1];
    return $out;
}

// Pagination
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Krila</title>
  <link rel="stylesheet" type="text/css" href="static/krila.css">
</head>
<body>
  <div id="boardsection"></div>
  <a href="index.php"><img src="static/logo.png" id="logo" alt="Krila"></a><br>
  <div id="boardname">/krila/ - Anything & Everything</div><br>
  <div id="threadcreate">
    <form enctype="multipart/form-data" action="post.php" method="post">
      <input type="text" name="name" id="name"><label id="threadlabel" for="name"> Name</label><br>
      <input type="text" name="title" id="title"><label id="threadlabel" for="title"> Subject</label><br>
      <textarea name="body" id="body"></textarea><label id="threadlabel" for="body"> Comment</label><br>
      <input type="file" name="image" accept="image/jpeg,image/png,image/gif">
      <input type="submit" name="submit" value="Post" style="float: right; margin-right: 50px;"><br><br>
    </form>
  </div>
  <hr>
  <?php
  $path = 'thread/';
  $threads = [];
  if (is_dir($path)) {
      $raw = scandir($path) ?: [];
      $threads = array_values(array_filter($raw, static function (string $f) use ($path): bool {
          return is_file($path . $f) && str_ends_with($f, '.txt');
      }));
      natsort($threads);
      $threads = array_reverse(array_values($threads)); // newest first
  }

  $total     = count($threads);
  $totalPages = max(1, (int)ceil($total / $perPage));
  $page      = min($page, $totalPages);
  $offset    = ($page - 1) * $perPage;
  $slice     = array_slice($threads, $offset, $perPage);

  foreach ($slice as $threadFile) {
      $f = @fopen($path . $threadFile, 'r');
      if ($f === false) {
          continue;
      }
      $metainformation = fgets($f) ?: '';
      $body = fgets($f) ?: '';
      fclose($f);

      $meta     = parse_meta_line($metainformation);
      $name     = $meta['name'];
      $date     = $meta['date'];
      $title    = $meta['title'];
      $include  = $meta['include'];
      $threadId = str_replace('.txt', '', $threadFile);

      echo '<div id="main_postcontainer">';
      if ($include !== '') {
          $safe = htmlspecialchars($include, ENT_QUOTES, 'UTF-8');
          echo '<span id="metadata">File: <a href="cdn/' . $safe . '">' . $safe . '</a></span><br>';
          echo '<a href="cdn/' . $safe . '"><img id="thumb" style="vertical-align: top;" src="cdn/' . $safe . '" alt=""></a>';
      }
      echo '<span id="posttitle">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '    </span>';
      echo '<span id="name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '    </span>';
      echo '<span id="date">' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '   </span>';
      echo '<span id="postno">No. ' . htmlspecialchars($threadId, ENT_QUOTES, 'UTF-8') . '</span><br><br>';
      echo '<span id="body">' . htmlspecialchars(ltrim($body, '#'), ENT_QUOTES, 'UTF-8') . '</span><br>';
      echo '<a id="body" href="thread.php?=' . htmlspecialchars($threadId, ENT_QUOTES, 'UTF-8') . '">Show more</a><br><br><br><br><br><br><br><br><br>';
      echo '</div><hr>';
  }

  // Pagination controls
  if ($totalPages > 1) {
      echo '<div style="text-align:center; margin:1em 0;">';
      if ($page > 1) {
          echo '<a href="index.php?page=' . ($page - 1) . '">[Previous]</a> ';
      }
      for ($i = 1; $i <= $totalPages; $i++) {
          if ($i === $page) {
              echo '<strong>[' . $i . ']</strong> ';
          } else {
              echo '<a href="index.php?page=' . $i . '">[' . $i . ']</a> ';
          }
      }
      if ($page < $totalPages) {
          echo '<a href="index.php?page=' . ($page + 1) . '">[Next]</a>';
      }
      echo '</div>';
  }
  ?>
</body>
</html>
