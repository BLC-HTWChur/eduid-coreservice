<?php

    /**
     * Autoloader for the eduid subsystem for the PHP5 autoloader system.
     *
     * In order to use autoloading this file needs to be loaded during the
     * initialization of your root script.
     */
    spl_autoload_register(function ($class) {
        $class = ltrim($class, '\\');
        $parts = explode('\\', $class);
        $NSRoot = array_shift($parts);

        if (isset($NSRoot) &&
            !empty($NSRoot) &&
            $NSRoot == "Lcobucci") {

            $jwt = strtolower(array_shift($parts));
            array_unshift($parts, "src");
            array_unshift($parts, $jwt);
            
            $path = implode( '/', $parts) . ".php";
            
            $prefixes = explode(PATH_SEPARATOR, get_include_path());

            array_push($prefixes, "..");
            foreach ( $prefixes as $p ) {
                if (file_exists($p . "/" . $path)) {
                    include_once $p . "/" . $path;
                    break;
                }
            }
        }
    });
?>