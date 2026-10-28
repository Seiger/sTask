<?php

declare(strict_types=1);

// The contract owns its in-memory container; a consuming CMS helper would
// otherwise boot a real site when SupervisorService translates an event.
if (!function_exists('app')) {
    function app($abstract = null, array $parameters = [])
    {
        $container = \Illuminate\Container\Container::getInstance();

        return $abstract === null ? $container : $container->make($abstract, $parameters);
    }
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    $autoload = (string)getenv('STASK_AUTOLOAD');
}
if ($autoload === '' || !is_file($autoload)) {
    $autoload = dirname(__DIR__, 3) . '/autoload.php';
}
if (!is_file($autoload)) {
    throw new RuntimeException('Set STASK_AUTOLOAD to a Composer autoload.php path.');
}

$loader = require $autoload;
$loader->addPsr4('Seiger\\sTask\\', dirname(__DIR__) . '/src/', true);
foreach ($loader->getClassMap() as $class => $file) {
    if (str_starts_with($class, 'Seiger\\sTask\\')) {
        $localFile = dirname(__DIR__) . '/src/'
            . str_replace('\\', '/', substr($class, strlen('Seiger\\sTask\\'))) . '.php';
        if (is_file($localFile)) {
            $loader->addClassMap([$class => $localFile]);
        }
    }
}
if (realpath((new ReflectionClass(\Seiger\sTask\Services\SupervisorService::class))->getFileName())
    !== realpath(dirname(__DIR__) . '/src/Services/SupervisorService.php')) {
    throw new RuntimeException('The contract must load this package checkout.');
}

