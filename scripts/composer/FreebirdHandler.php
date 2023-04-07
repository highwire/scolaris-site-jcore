<?php

/**
 * @file
 * Contains \HighWireSite\composer\FreebirdHandler.
 */

namespace HighWireSite\composer;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

class FreebirdHandler {

  /**
   * Get freebird modules and profile into correct locations.
   * 
   * This is annoying but necessary, because in order for us to keep
   * the profile file (freebird.info.yml) in the Freebird repo, but be identified
   * by Drupal as an actual profile, we need to set `type: profile`, which
   * Drupal installs into the web/profiles/contrib directory...
   * This location doesn't allow us to work on Freebird and it's code/modules 'live',
   * so we move it to web/modules/freebird and symlink the profile 
   * back into web/profiles/contrib/freebird.
   */
  public static function setupFreebirdCode(Event $event) {
    $fs = new Filesystem();
    $composerRoot = dirname($event->getComposer()->getConfig()->get('vendor-dir'));
    $drupalRoot = $composerRoot . '/web';

    // Determine if the following setup is needed first, running composer install
    // on the command line is different than running 'Build site' in SMART.
    $modules_dir = $drupalRoot . '/modules/freebird';
    // SMART build actually pulls the site repo a-fresh, which means web/modules/freebird
    // doesn't exist. If it already exists, assume we're running 'composer install' on the
    // CLI and do not perform any actions.
    if (is_dir($modules_dir)) return;

    // Hard move Freebird modules straight away.
    $freebird_repo = $drupalRoot . '/profiles/contrib/freebird';
    $fs->mkdir($modules_dir);
    $event->getIO()->write("Moving $freebird_repo to $modules_dir");
    $fs->mirror($freebird_repo, $modules_dir);
    $fs->remove($freebird_repo);

    // Create the Freebird profile directory.
    $freebird_profile_dir = $drupalRoot . '/profiles/contrib/freebird';
    $event->getIO()->write("Creating $freebird_profile_dir");
    $fs->mkdir($freebird_profile_dir);

    // Symlink the Freebird profile file.
    $freebird_profile_source = $drupalRoot . '/modules/freebird/freebird.info.yml';
    $freebird_profile_dest = $freebird_profile_dir . '/freebird.info.yml';
    $event->getIO()->write("Symlinking $freebird_profile_source to $freebird_profile_dest");
    $fs->symlink($freebird_profile_source, $freebird_profile_dest);
  }

}
