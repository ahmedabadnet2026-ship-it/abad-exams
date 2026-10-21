<?php
// Folder root: same login gate as api/page.php (Cloudways nginx serves *.html statically, so the root must enforce in PHP itself).
$_GET['f'] = 'index.html';
require __DIR__ . '/api/page.php';
