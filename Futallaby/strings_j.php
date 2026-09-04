<?php

define('S_HOME', 'Home');
define('S_ADMIN', 'Admin');
define('S_RETURN', 'Return to the board');
define('S_POSTING', 'Reply Mode');
define('S_NOTAGS', 'HTML tags are allowed');
define('S_NAME', 'Name');
define('S_EMAIL', 'E-mail');
define('S_SUBJECT', 'Subject');
define('S_SUBMIT', 'Submit');
define('S_COMMENT', 'Comment');
define('S_UPLOADFILE', 'File');
define('S_NOFILE', 'No image');
define('S_DELPASS', 'Password');
define('S_DELEXPL', '(For deleting posts. Alphanumeric, max 8 characters)');
define('S_RULES', '<LI>Allowed file types: GIF, JPG, PNG. Some browsers may not attach files correctly.
<LI>Maximum post size is '.MAX_KB.' KB. Sage is supported.
<LI>Images larger than '.MAX_W.'�~'.MAX_H.' pixels will be displayed as thumbnails.');
define('S_REPORTERR', 'Post not found');
define('S_THUMB', 'Thumbnail is being displayed. Click to view the original size.');
define('S_PICNAME', 'Image title: ');
define('S_REPLY', 'Reply');
define('S_OLD', 'This thread is old and will soon be deleted.');
define('S_RESU', 'replies');
define('S_ABBR', ' posts omitted. Press the Reply button to view the full thread.');
define('S_REPDEL', '�yDelete Post�z');
define('S_DELPICONLY', 'Delete image only');
define('S_DELKEY', 'Password');
define('S_DELETE', 'Delete');
define('S_PREV', 'Previous');
define('S_FIRSTPG', 'First');
define('S_NEXT', 'Next');
define('S_LASTPG', 'Last');
define('S_FOOT', '- <a href="http://php.s3.to" target=_blank>GazouBBS</a> + <a href="http://www.2chan.net/" target=_blank>futaba</a> + <a href="http://www.mapored.com/futallaby/" target=_blank>futallaby</a> -');
define('S_RELOAD', 'Reload');
define('S_UPFAIL', 'Upload failed<br>The server may not support this file type');
define('S_NOREC', 'Upload failed<br>Only image files are accepted');
define('S_SAMEPIC', 'Upload failed<br>Duplicate image detected');
define('S_TOOBIG', 'Upload failed<br>File is too large<br>Maximum size is '.MAX_KB.' KB');
define('S_TOOBIGORNONE','Upload failed<br>Image is too large or<br>no image was provided.');
define('S_UPGOOD', 'Image uploaded successfully<br><br>');
define('S_STRREF', 'Rejected (str)');
define('S_UNJUST', 'Please do not make improper posts (post)');
define('S_NOPIC', 'No image');
define('S_NOTEXT', 'Please write something');
define('S_MANAGEMENT', 'Delete');
define('S_DELETION', 'Delete');
define('S_TOOLONG', 'Comment is too long!');
define('S_UNUSUAL', 'Abnormal request');
define('S_BADHOST', 'Rejected (host)');
define('S_PROXY80', 'ERROR! Public proxy is restricted!! (80)');
define('S_PROXY8080', 'ERROR! Public proxy is restricted!! (8080)');
define('S_SUN', 'Sun');
define('S_MON', 'Mon');
define('S_TUE', 'Tue');
define('S_WED', 'Wed');
define('S_THU', 'Thu');
define('S_FRI', 'Fri');
define('S_SAT', 'Sat');
define('S_ANONAME', 'Anonymous');
define('S_ANOTEXT', 'No text');
define('S_ANOTITLE', 'No subject');
define('S_RENZOKU', 'Please wait a while before posting again');
define('S_RENZOKU2', 'Please wait a while before uploading another image');
define('S_RENZOKU3', 'Please wait a while before posting again');
define('S_DUPE', 'Upload failed<br>Duplicate image detected');
define('S_NOTHREADERR', 'Thread does not exist');
define('S_SCRCHANGE', 'Switching screen');
define('S_BADDELPASS', 'Post not found or incorrect password');
define('S_WRONGPASS', 'Incorrect password');
define('S_RETURNS', 'Return to the board');
define('S_LOGUPD', 'Update log');
define('S_MANAMODE', 'Admin Mode');
define('S_MANAREPDEL', 'Delete Posts');
define('S_MANAPOST', 'Admin Post');
define('S_MANASUB', ' Authentication');
define('S_DELLIST', 'Check the boxes of the posts you want to delete, then press the Delete button.');
define('S_ITDELETES', 'Delete');
define('S_MDRESET', 'Reset');
define('S_MDONLYPIC', 'Delete image only');
define('S_MDTABLE1', '<th>Delete</th><th>No.</th><th>Date</th><th>Subject</th>');
define('S_MDTABLE2', '<th>Name</th><th>Comment</th><th>Host</th><th>File<br>(Bytes)</th><th>md5</th>');
define('S_RESET', 'Reset');
define('S_IMGSPACEUSAGE', '�y Total image data : <b>$all</b> KB �z');
define('S_CANNOTWRITE', 'Cannot write to the current directory<br>');
define('S_NOTWRITE', ' cannot be written<br>');
define('S_NOTREAD', ' cannot be read<br>');
define('S_NOTDIR', ' does not exist<br>');
/* begin MySQL specific section */
define('S_SQLCONF', 'Database connection failed'); //MySQL connection failure
define('S_SQLDBSF', 'mysql_select_db failed<br>'); //database select failure
define('S_TCREATE', 'Creating table<br>\n'); //creating table
define('S_TCREATEF', 'Table creation failed<br>'); //table creation failed
define('S_SQLFAIL', 'SQL failed<br>'); //SQL Failure
?>