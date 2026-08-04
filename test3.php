<?php
$rawInput = '{"tree": [{"id": "b3", "title": "Book 3"}, {"id": "b1", "title": "Book 1"}, {"id": "b2", "title": "Book 2"}]}';
$json = json_decode($rawInput, true);
$tree = $json['tree'] ?? $_POST['tree'] ?? null;
var_dump($tree);
