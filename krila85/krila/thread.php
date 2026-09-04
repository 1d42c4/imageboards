<?php
declare(strict_types=1);

/**
 * View a single thread – PHP 8.5+ compatible, original reply layout restored
 */

/**
 * Extract meta fields safely
 */
function parse_meta_line(string $line): array
{
    $line = trim(str_replace(['[', ']'], '', $line));
    $out = [
        'name'    => '',
        'date'    => '',
        'title'   => '',
        'include' => '',
    ];
    if (preg_match('/name="([^"]*)"/', $line, $m)) {
        $out['name'] = $m[1];
    }
    if (preg_match('/date="([^"]*)"/', $line, $m)) {
        $out['date'] = $m[1];
    }
    if (preg_match('/title="([^"]*)"/', $line, $m)) {
        $out['title'] = $m[1];
    }
    if (preg_match('/include="([^"]*)"/', $line, $m)) {
        $out['include'] = $m[1];
    }
    return $out;
}

// Original URLs look like thread.php?=123
$id = '';
if (isset($_GET['']) && is_string($_GET[''])) {
    $id = preg_replace('/[^0-9]/', '', $_GET['']) ?? '';
}
if ($id === '') {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $id = preg_replace('/[^0-9]/', '', $qs) ?? '';
}

if ($id === '') {
    http_response_code(400);
    exit('Missing thread id');
}

$threadlink = 'thread/' . $id . '.txt';
if (!is_file($threadlink)) {
    http_response_code(404);
    exit('Thread not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" type="text/css" href="static/krila.css">
  <title>/krila/</title>
</head>
<body>
  <div id="boardsection"></div>
  <a href="index.php"><img src="static/logo.png" id="logo" alt="Krila"></a><br>
  <div id="threadcreate">
    <form enctype="multipart/form-data" action="postcomment.php" method="post">
      <input type="text" name="name" id="name"><label id="threadlabel" for="name"> Name</label><br>
      <input type="text" name="title" id="title"><label id="threadlabel" for="title"> Subject</label><br>
      <textarea name="body" id="body"></textarea><label id="threadlabel" for="body"> Comment</label><br>
      <input type="file" name="image" accept="image/jpeg,image/png,image/gif">
      <input type="submit" name="submit" value="Post" style="float: right; margin-right: 50px;"><br><br>
    </form>
  </div>
  <hr>
  <?php
  $thread = fopen($threadlink, 'r');
  if ($thread === false) {
      echo 'Could not open thread.';
      exit;
  }

  $cnt         = 0;
  $pastinclude = 0;
  $firsttitle  = 0;

  while (!feof($thread)) {
      $cnt++;
      $line = fgets($thread);
      if ($line === false) {
          break;
      }

      // Meta line
      if (str_starts_with($line, '[')) {
          // Close previous post container (exact original spacing)
          if ($cnt !== 1) {
              if ($pastinclude === 1) {
                  echo '<br><br><br><br><br><br><br><br><br></div>';
              } else {
                  echo '<br></div>';
              }
          }

          $meta    = parse_meta_line($line);
          $name    = $meta['name'];
          $date    = $meta['date'];
          $title   = $meta['title'];
          $include = $meta['include'];

          echo '<div id="postcontainer">';
          if ($include !== '') {
              echo '<span id="metadata">File: <a href="cdn/' . htmlspecialchars($include, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($include, ENT_QUOTES, 'UTF-8') . '</a></span><br>';
              echo '<a href="cdn/' . htmlspecialchars($include, ENT_QUOTES, 'UTF-8') . '"><img id="thumb" style="vertical-align: top;" src="cdn/' . htmlspecialchars($include, ENT_QUOTES, 'UTF-8') . '" alt=""></a>';
              $pastinclude = 1;
          } else {
              $pastinclude = 0;
          }
          echo '<span id="posttitle">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '    </span>';
          echo '<span id="name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '    </span>';
          echo '<span id="date">' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '   </span>     <br><br>';
      }
      // Body line starting with #
      elseif (str_starts_with($line, '#')) {
          $content = substr($line, 1); // strip leading #

          if (str_starts_with($content, '>')) {
              // Pure greentext
              echo '<span id="body"><span id="greentext">' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</span></span><br>';
              if ($firsttitle === 0) {
                  echo '<title>/krila/ - ' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</title>';
                  $firsttitle = 1;
              }
          } else {
              // May contain greentext mid-line
              $arr = explode('>', $content, 2);
              if (count($arr) > 1) {
                  $plain = htmlspecialchars($arr[0], ENT_QUOTES, 'UTF-8');
                  $green = '<span id="greentext">>' . htmlspecialchars($arr[1], ENT_QUOTES, 'UTF-8') . '</span>';
                  echo '<span id="body">' . $plain . ' ' . $green . '</span><br>';
                  if ($firsttitle === 0) {
                      echo '<title>/krila/ - ' . $plain . '</title>';
                      $firsttitle = 1;
                  }
              } else {
                  echo '<span id="body">' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</span><br>';
                  if ($firsttitle === 0) {
                      echo '<title>/krila/ - ' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</title>';
                      $firsttitle = 1;
                  }
              }
          }
      }
      // Rare continuation lines
      else {
          if (str_starts_with($line, '>')) {
              echo '<span id="body"><span id="greentext">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</span></span><br>';
          } else {
              $arr = explode('>', $line, 2);
              if (count($arr) > 1) {
                  $plain = htmlspecialchars($arr[0], ENT_QUOTES, 'UTF-8');
                  $green = '<span id="greentext">>' . htmlspecialchars($arr[1], ENT_QUOTES, 'UTF-8') . '</span>';
                  echo '<span id="body">' . $plain . ' ' . $green . '</span>';
              } else {
                  echo '<span id="body">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</span><br>';
              }
          }
      }
  }
  // Close the last open post container
  if ($cnt > 0) {
      if ($pastinclude === 1) {
          echo '<br><br><br><br><br><br><br><br><br></div>';
      } else {
          echo '<br></div>';
      }
  }
  fclose($thread);
  ?>
</body>
</html>
