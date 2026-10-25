<?php
// Tiny PDO bridge used by Playwright helpers (no framework boot): php query.php <sqlite-file> <sql> [json-params]
// SELECT-like statements print a JSON array of rows; other statements print {"changes": n}.
$db = new PDO('sqlite:'.$argv[1]);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA busy_timeout = 10000');
$stmt = $db->prepare($argv[2]);
$stmt->execute(isset($argv[3]) ? json_decode($argv[3], true) : []);
if ($stmt->columnCount() > 0) {
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    echo json_encode(['changes' => $stmt->rowCount()]);
}
