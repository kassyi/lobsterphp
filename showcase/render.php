<?php
header('Content-Type: text/html');
require_once __DIR__ . '/../vendor/autoload.php';

use Kassyi\LobsterPhp\Lobster;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $markdown = $_POST['markdown'] ?? '';
    echo Lobster::toHtml($markdown);
    exit;
}
?>
