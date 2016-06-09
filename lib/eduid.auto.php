<?php

spl_autoload_register(function ($class) {
    $class = ltrim($class, '\\');
    $parts = explode('\\', $class);

    $root = array_shift($parts);

    if (isset($root) && !empty($root)) {
        $cpath = array();
        // direct namespace
        $cpath[] = $root . "/" . implode("/", $parts) . ".class.php";

        // sub-directory namespaces
        $cpath[] = $root . "/classes/" . implode("/", $parts) . ".class.php";
        $cpath[] = $root . "/src/" . implode("/", $parts) . ".class.php";
        $cpath[] = $root . "/lib/" . implode("/", $parts) . ".class.php";

        // for developer prefixed namespaces
        $root = array_shift($parts);
        $cpath[] = strtolower($root) . "/src/" . implode("/", $parts) . ".class.php";
        $cpath[] = strtolower($root) . "/lib/" . implode("/", $parts) . ".class.php";

        $prefixes = explode(PATH_SEPARATOR, get_include_path());

        foreach ( $prefixes as $p ) {
            foreach ($cpath as $path) {
                if (file_exists($p . "/" . $path)) {
                    include_once $p . "/" . $path;
                    break 2;
                }
            }
        }
    }
});


?>
