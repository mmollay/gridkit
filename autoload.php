<?php
spl_autoload_register(function (string $class): void {
    $prefix = 'GridKit\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
});
