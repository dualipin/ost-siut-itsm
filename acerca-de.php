<?php
/* @var Latte\Engine $latte */
$latte = require_once __DIR__ . '/src/latte.php';

$latte->render(__DIR__ . '/plantillas/acerca-de.latte');