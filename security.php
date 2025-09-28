<?php
// Session security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Only if using HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 3600); // 1 hour
ini_set('session.gc_maxlifetime', 3600); // 1 hour

?>