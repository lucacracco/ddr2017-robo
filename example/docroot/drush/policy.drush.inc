<?php

/*
 * Customize this file as desired. See https://github.com/drush-ops/drush/blob/8.x/examples/policy.drush.inc for documentation.
 */

use Drush\Log\LogLevel;

/**
 * Implements drush_hook_COMMAND_validate().
 *
 * Encourage folks to use `composer` instead of Drush pm commands
 */
function drush_policy_pm_updatecode_validate() {
  return _deny_message();
}

/**
 * Implements drush_hook_COMMAND_validate().
 *
 * Encourage folks to use `composer` instead of Drush pm commands
 */
function drush_policy_pm_update_validate() {
  return _deny_message();
}

/**
 * Implements drush_hook_COMMAND_validate().
 *
 * Encourage folks to use `composer` instead of Drush pm commands
 */
function drush_policy_pm_download_validate() {
  return _deny_message();
}

/**
 * Implements hook_drush_help_alter().
 */
function policy_drush_help_alter(&$command) {
  if (in_array($command['command'], [
    'pm-updatecode',
    'pm-update',
    'pm-download'
  ])) {
    $command['options']['pm-force'] = 'Override site policy and allow Drush codebase management (pm-* commands)';
  }
}

function drush_policy_pre_pm_uninstall($modules) {
  $args = pm_parse_arguments(func_get_args());
  _drush_custom_pm_uninstall($args);

  // TODO: how block execution after original functions/commands?.
}

/**
 * Get deny message.
 */
function _deny_message() {
  if (!drush_get_option('pm-force')) {
    $msg = 'This codebase is assembled with Composer instead of Drush. Use `composer update` and `composer require` instead of `drush pm-updatecode` and `drush pm-download`. You may override this error by using the --pm-force option.';
    return drush_set_error('POLICY_PM_DENY', dt($msg));
  }
}

/**
 * Command callback. Uninstall one or more extensions.
 *
 * @param array $extensions
 *   Arguments from the command line.
 *
 * @return bool
 */
function _drush_custom_pm_uninstall($extensions) {
  $extension_info = drush_get_extensions();
  $required = drush_drupal_required_modules($extension_info);

  // Discards extensions which are enabled, not found or already uninstalled.
  $extensions = array_combine($extensions, $extensions);
  foreach ($extensions as $extension) {
    if (!isset($extension_info[$extension])) {
      unset($extensions[$extension]);
      drush_log(dt('Extension !extension was not found and will not be uninstalled.', ['!extension' => $extension]), LogLevel::WARNING);
    }
    elseif (in_array($extension, $required)) {
      unset($extensions[$extension]);
      $info = $extension_info[$extension]->info;
      $explanation = !empty($info['explanation']) ? ' ' . dt('Reason: !explanation.', ['!explanation' => strip_tags($info['explanation'])]) : '';
      drush_log(dt('!extension is a required extension and can\'t be uninstalled.', ['!extension' => $extension]) . $explanation, LogLevel::OK);
    }
    elseif (!$extension_info[$extension]->status) {
      unset($extensions[$extension]);
      drush_log(dt('!extension is already uninstalled.', ['!extension' => $extension]), LogLevel::OK);
    }
    elseif (drush_extension_get_type($extension_info[$extension]) == 'module') {
      $dependents = [];
      foreach (drush_module_dependents([$extension], $extension_info) as $dependent) {
        if (!in_array($dependent, $required) && ($extension_info[$dependent]->status)) {
          $dependents[] = $dependent;
        }
      }
      if (count($dependents)) {
        drush_log(dt('To uninstall !extension, the following extensions must be uninstalled first: !required. The following extensions will be uninstalled automatically: !required', [
          '!extension' => $extension,
          '!required' => implode(', ', $dependents)
        ]), LogLevel::ERROR);
//        unset($extensions[$extension]);
      }
    }
  }

  // Discard default theme.
  $default_theme = drush_theme_get_default();
  if (in_array($default_theme, $extensions)) {
    unset($extensions[$default_theme]);
    drush_log(dt('!theme is the default theme and can\'t be uninstalled.', ['!theme' => $default_theme]), LogLevel::OK);
  }

  // Inform the user which extensions will finally be disabled.
  if (empty($extensions)) {
    return drush_log(dt('There were no extensions that could be uninstalled.'), LogLevel::OK);
  }
  else {
    drush_print(dt('The following extensions will be uninstalled: !extensions', ['!extensions' => implode(', ', $extensions)]));
    if (!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }
  }

  // Classify extensions in themes and modules.
  $modules = [];
  $themes = [];
  drush_pm_classify_extensions($extensions, $modules, $themes, $extension_info);

  drush_module_uninstall($modules);
  drush_theme_uninstall($themes);

  // Inform the user of final status.
  foreach ($extensions as $extension) {
    drush_log(dt('!extension was successfully uninstalled.', ['!extension' => $extension]), LogLevel::OK);
  }
}

