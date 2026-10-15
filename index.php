<?php
/** Gerbang aplikasi — dialihkan ke portal publik SPMB. */
require_once __DIR__ . '/includes/config.php';

header('Location: ' . url('app/spmb/index.php'));
exit;
