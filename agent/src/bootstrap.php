<?php

// When we run the agent within a Laravel application, the consuming Laravel
// application will have run its own autoloader. When this happens, Composer
// fills the `$GLOBALS['__composer_autoload_files']` variable with all
// autoloaded files, i.e., those listed under the `"files"` key in the
// `composer.json` of the project and its dependencies.
//
// Because we run the agent PHAR within the context of the consuming
// application we inherit the `$GLOBALS` variable from the outer scope. The
// PHAR is not an isolated environment.
//
// As Composer encounters each file to autoload it checks to ensure that
// Composer has not already required the file, by checking
// `$GLOBALS['__composer_autoload_files']`, based on the file's hash. If the
// agent and the consuming package require the same dependencies it is entirely
// possible that the agent's autoloader will mistakely think it has already
// autoloaded the shared dependencies files.
//
// To get around this, we temporarily empty the composer autoload files from
// from `$GLOBALS`, require the agent's autoloader, and then restore the global
// scope's autoloaded files in the `$GLOBALS` variable. This allows composer to
// autoload our depedencies files again.
$composerAutoloadFiles = $GLOBALS['__composer_autoload_files'] ?? [];
$GLOBALS['__composer_autoload_files'] = [];

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require __DIR__.'/../vendor/autoload.php';

$GLOBALS['__composer_autoload_files'] = $composerAutoloadFiles;

// PHP lazily autoloads classes as they are encountered. A PHAR contains a
// virtual file system, to some degree, where each file exists as a line
// position within the PHAR. When running a PHAR, all classes in the PHAR are
// not loaded by default, they continue to be autoloaded as they are
// encountered. When the PHAR attempts to autoload a file, it will reach into
// the PHAR again, the PHAR will tell it the lines that the file exists on
// within the PHAR, and that "file" is then read into memory.
//
// When deploying, we replace the PHAR on the filesystem while the PHAR is
// still running.
//
// We then ask the in-memory running PHAR to shutdown. As it shutsdown, it
// wants classes it has not yet loaded. It attempts to read them from the new
// PHAR on disk and the line numbers do not match up. This results in strange
// syntax errors as it gets invalid "files".
//
// This results in shutdown errors that blast PHP code into the log files.
//
// To counteract this, we now eagerly load all known classes using the
// `class_exists` method. We cannot manually require the classes, as if the
// file has been required before it would be problematic. We also cannot use
// `require_once` as Composer does not use `require_once`.
//
// `class_exists` seems like a good way to have Composer do the autoloading
// legwork for us. It also successfully loads traits, interfaces, etc. The
// method simply returns `false` in those non-class cases, but the file is
// still autoloaded.
foreach ($autoloader->getClassMap() as $class => $path) {
    class_exists($class);
}
