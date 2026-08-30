<?php
// Legacy entry kept for backward-compatibility: redirect to the MVC route.
parse_str($_SERVER['QUERY_STRING'], $q);
$q['r'] = 'checkin/index';
header('Location: index.php?' . http_build_query($q));
exit;
