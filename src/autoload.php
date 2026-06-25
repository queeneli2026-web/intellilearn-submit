<?php
declare(strict_types=1);

/**
 * PSR-4-like autoloader using spl_autoload_register.
 *
 * Maps the App\ namespace prefix to the src/ directory.
 * Converts namespace separators to directory separators.
 *
 * Example: App\Controllers\AuthController → src/Controllers/AuthController.php
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Not in our namespace — let another autoloader handle it
        return;
    }

    // Get the relative class name (strip prefix)
    $relativeClass = substr($class, $len);

    // Convert namespace separators to directory separators
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // Load the file if it exists
    if (file_exists($file)) {
        require_once $file;
    }
});
