<?php

namespace dpi\DrupalPhpunitBootstrap;

/**
 * Utility.
 */
final class Utility
{
  /**
   * Finds all valid extension directories recursively within a given directory.
   *
   * @param string $scan_directory
   *   The directory that should be recursively scanned.
   *
   * @return array
   *   An associative array of extension directories found within the scanned
   *   directory, keyed by extension name.
   */
  public static function drupal_phpunit_find_extension_directories($scan_directory) {
    $extensions = [];
    $dirs = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($scan_directory, \RecursiveDirectoryIterator::FOLLOW_SYMLINKS));
    foreach ($dirs as $dir) {
      if (strpos($dir->getPathname(), '.info.yml') !== FALSE) {
        // Cut off ".info.yml" from the filename for use as the extension name. We
        // use getRealPath() so that we can scan extensions represented by
        // directory aliases.
        $extensions[substr($dir->getFilename(), 0, -9)] = $dir->getPathInfo()
          ->getRealPath();
      }
    }
    return $extensions;
  }

  /**
   * Returns directories under which contributed extensions may exist.
   *
   * @param string $root
   *   (optional) Path to the root of the Drupal installation.
   *
   * @return array
   *   An array of directories under which contributed extensions may exist.
   */
  public static function drupal_phpunit_contrib_extension_directory_roots($root = NULL) {
    if ($root === NULL) {
      $root = dirname(__DIR__, 2);
    }
    $paths = [
      $root . '/core/modules',
      $root . '/core/profiles',
      $root . '/core/themes',
      $root . '/modules',
      $root . '/profiles',
      $root . '/themes',
    ];
    $sites_path = $root . '/sites';
    // Note this also checks sites/../modules and sites/../profiles.
    foreach (scandir($sites_path) as $site) {
      if ($site[0] === '.' || $site === 'simpletest') {
        continue;
      }
      $path = "$sites_path/$site";
      $paths[] = is_dir("$path/modules") ? realpath("$path/modules") : NULL;
      $paths[] = is_dir("$path/profiles") ? realpath("$path/profiles") : NULL;
      $paths[] = is_dir("$path/themes") ? realpath("$path/themes") : NULL;
    }
    return array_filter($paths);
  }

  /**
   * Registers the namespace for each extension directory with the autoloader.
   *
   * @param array $dirs
   *   An associative array of extension directories, keyed by extension name.
   *
   * @return array
   *   An associative array of extension directories, keyed by their namespace.
   */
  public static function drupal_phpunit_get_extension_namespaces($dirs) {
    $suite_names = [
      'Unit',
      'Kernel',
      'Functional',
      'Build',
      'FunctionalJavascript'
    ];
    $namespaces = [];
    foreach ($dirs as $extension => $dir) {
      if (is_dir($dir . '/src')) {
        // Register the PSR-4 directory for module-provided classes.
        $namespaces['Drupal\\' . $extension . '\\'][] = $dir . '/src';
      }
      $test_dir = $dir . '/tests/src';
      if (is_dir($test_dir)) {
        foreach ($suite_names as $suite_name) {
          $suite_dir = $test_dir . '/' . $suite_name;
          if (is_dir($suite_dir)) {
            // Register the PSR-4 directory for PHPUnit-based suites.
            $namespaces['Drupal\\Tests\\' . $extension . '\\' . $suite_name . '\\'][] = $suite_dir;
          }
        }
        // Extensions can have a \Drupal\Tests\extension\Traits namespace for
        // cross-suite trait code.
        $trait_dir = $test_dir . '/Traits';
        if (is_dir($trait_dir)) {
          $namespaces['Drupal\\Tests\\' . $extension . '\\Traits\\'][] = $trait_dir;
        }
      }
    }
    return $namespaces;
  }
}
