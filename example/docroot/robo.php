#!/usr/bin/env php
<?php

/**
 * If we're running from phar load the phar autoload file.
 */
$pharPath = \Phar::running(TRUE);
if ($pharPath) {
  require_once "$pharPath/vendor/autoload.php";
}
else {
  if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
  }
  elseif (file_exists(__DIR__ . '/../../autoload.php')) {
    require_once __DIR__ . '/../../autoload.php';
  }
}

// Define static.
$commandClasses = [
  \Lucacracco\Drupal8\Robo\RoboFileBase::class,
];
$statusCode = \Robo\Robo::run(
  $_SERVER['argv'],
  $commandClasses,
  'MyAppName',
  '0.0.0-alpha0'
);
exit($statusCode);