<?php

spl_autoload_register(function ($class) {
    $class = ltrim($class, '\\');
    $parts = explode('\\', $class);

    $root = array_shift($parts);

    if (isset($root) && !empty($root)) {
        $cpath = array();
        if ($root == "EduID") {
            $cpath[] = $root . "/" . implode("/", $parts) . ".class.php";
        }
        else {
            $cpath[] = $root . "/classes/" . . implode("/", $parts) . ".class.php";
            $root = array_shift($parts);
            $cpath[] = strtolower($root) . "/src/" . . implode("/", $parts) . ".class.php";
        }

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
