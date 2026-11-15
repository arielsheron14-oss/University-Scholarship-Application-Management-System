
<?php

require __DIR__.'/../config/database.php';
require __DIR__.'/../includes/functions.php';

require_role(['staff','admin']);

$id = (int)($_GET['id'] ?? 0);

$q = $pdo->prepare(
    'SELECT original_filename, stored_path, mime_type
    FROM application_documents
    WHERE document_id=?'
);

$q->execute([$id]);

$doc = $q->fetch(PDO::FETCH_ASSOC);

if (!$doc) {

    http_response_code(404);

    exit('Document not found.');

}

$root = realpath(__DIR__ . '/../uploads/application_documents');

$file = realpath(__DIR__ . '/../' . $doc['stored_path']);

if (
    !$root ||
    !$file ||
    strpos($file, $root . DIRECTORY_SEPARATOR) !== 0 ||
    !is_file($file)
) {

    http_response_code(404);

    exit('Document not found.');

}

$name = preg_replace(
    '/[^A-Za-z0-9._ -]/',
    '_',
    basename($doc['original_filename'])
);

header('Content-Type: ' . $doc['mime_type']);

header('Content-Length: ' . filesize($file));

header('Content-Disposition: attachment; filename="' . $name . '"');

header('X-Content-Type-Options: nosniff');

readfile($file);

exit;

?>
