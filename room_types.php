<?php
// Legacy entry kept for backward-compatibility: redirect to the MVC route.
parse_str($_SERVER['QUERY_STRING'], $q);
$q['r'] = 'room_types/index';
header('Location: index.php?' . http_build_query($q));
exit;
