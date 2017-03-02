<?php

/**
 * @file Robofile.php.
 *
 * Contains a Robofile.
 */

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Robo\Exception\TaskException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Class RoboFile.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks {

  /**
   * Constant variable environment.
   */
  const ENV_LOCAL = 'local';
  const ENV_STAGE = 'stage';
  const ENV_PROD = 'prod';

  /**
   * Options arguments for command line.
   */
  const OPTS = [
    'site|s' => 'default',
    'environment|e' => self::ENV_LOCAL,
  ];

  /**
   * Directory backups and others.
   */
  const FOLDER_PROPERTIES = 'build';
  const FOLDER_TEMPLATES = 'build/templates';
  const FOLDER_BACKUPS_DATABASE = 'build/backups';
  const FOLDER_IMPORT_DATABASE = 'build/import-backups';

  /**
   * Twig Environment.
   *
   * @var \Twig_Environment
   */
  protected $twig;

  /**
   * Store properties used.
   *
   * @var array
   */
  protected $properties = [];

  /**
   * Store environment used.
   *
   * @var string
   */
  protected $environment = 'local';

  /**
   * Store site name used.
   *
   * @var string
   */
  var $site = 'default';

  /**
   * Store if use default site or not.
   *
   * @var bool
   */
  var $use_default = TRUE;

  /**
   * RoboFile constructor.
   */
  public function __construct() {
    // Stop a command on first failure of a task.
    $this->stopOnFail(TRUE);
  }

  /**
   * Build a site from scratch.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   */
  public function buildNew($opts = self::OPTS) {

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    // Execute functions.
    $this->backupDatabase();
    $this->setupInstallation();
    $this->install();
    $this->configureSettings();
    $this->setSystemSiteUuid();
    $this->protectSite();
    $this->coreCron();
    $this->rebuildCache();

    // Install development modules.
    if ($opts['environment'] == 'local') {
      $this->installModules($this->properties['modules_dev']);
    }

    $this->entityUpdates();
    $this->getInfoSite();
  }

  /**
   * Build a site from configuration files.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   */
  public function buildConf($opts = self::OPTS) {

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    // Execute functions.
    $this->backupDatabase();
    $this->setupInstallation();
    $this->installFromConfig();
    $this->setSystemSiteUuid();
    $this->protectSite();
    $this->coreCron();
    $this->rebuildCache();

    // Install development modules.
    if ($opts['environment'] == 'local') {
      $this->installModules($this->properties['modules_dev']);
    }

    $this->entityUpdates();
    $this->getInfoSite();
  }

  /**
   * Build an existing site by importing the database.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   * @option $dbname|d Database name
   */
  public function buildFromDatabase($opts = self::OPTS + ['dbname|d' => NULL,]) {

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    // Execute functions.
    $this->backupDatabase();
    $this->configureSettings();
    $this->importDatabase($opts['dbname']);
    $this->setSystemSiteUuid();
    $this->protectSite();
    $this->coreCron();
    $this->rebuildCache();
    $this->entityUpdates();
    $this->getInfoSite();
  }

  /**
   * Deploy.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   */
  public function deploy($opts = self::OPTS) {

    $this->say("Init deploy with Drupal installed.");

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    // Execute functions.
    $this->backupDatabase();
    $this->setSystemSiteUuid();
    $this->importConfig();
    $this->protectSite();
    $this->rebuildCache();
    $this->entityUpdates();
    $this->coreCron();
  }

  /**
   * Export configuration after clear cache.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   */
  public function configurationExport($opts = self::OPTS) {
    $this->init($opts['environment'], $opts['site']);

    $this->rebuildCache();
    $this->exportConfig();
  }

  /**
   * Import configuration.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   */
  public function configurationImport($opts = self::OPTS) {
    $this->init($opts['environment'], $opts['site']);

    $this->rebuildCache();
    $this->uninstallModules($this->properties['modules_dev']);
    $this->importConfig();
    $this->installModules($this->properties['modules_dev']);
  }

  /**
   * Import features.
   *
   * @param string $feature_name
   *   Feature name.
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   * @option $bundle|b Feature bundle
   */
  public function featuresImport($feature_name, $opts = self::OPTS) {
    $this->say("Import all Features configurations.");

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    // Execution.
    $this->rebuildCache();
    $this->importFeatures($feature_name);
    $this->entityUpdates();
    $this->rebuildCache();
  }

  /**
   * Import All features.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   * @option $bundle|b Feature bundle
   */
  public function featuresImportAll($opts = self::OPTS + ['bundle|b' => 'athena_features',]) {
    $this->say("Import all Features configurations.");

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    // Execution.
    $this->rebuildCache();
    $this->importAllFeatures($opts['bundle']);
    $this->entityUpdates();
    $this->rebuildCache();
  }

  /**
   * Import All features.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   */
  public function fixturesImport($opts = self::OPTS) {
    $this->say("Import Fixtures");

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);
    $migration_fixtures = "fixtures";

    $this->migrateInstall();
    $this->migrateStatus($migration_fixtures);
    $this->migrateImport($migration_fixtures);
    $this->migrateStatus($migration_fixtures);
    $this->migrateUninstall();
  }

  /**
   * Migration install modules dependencies.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   */
  public function migrationInstall($opts = self::OPTS) {
    $this->say("Migration Install");

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    $this->migrateInstall();
  }

  /**
   * Migration uninstall modules dependencies.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   */
  public function migrationUninstall($opts = self::OPTS) {
    $this->say("Migration Uninstall");

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    $this->migrateUninstall();
  }

  /**
   * Perform one or more migration processes. Install, migrate, uninstall.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   * @option $migration|m Migration name
   * @option $extra|x Extra options
   */
  public function migrationImportSingle($opts = self::OPTS + [
    'migration|m' => '',
    'extra|x' => '',
  ]) {
    $this->say("Migration import.");

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    $this->migrateInstall();
    $this->migrateStatus($opts['migration'], $opts['extra']);
    $this->migrateImport($opts['migration'], $opts['extra']);
    $this->migrateUninstall();
    $this->rebuildCache();
  }

  /**
   * Perform one or more migration processes.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   * @option $migration|m Migration name
   * @option $extra|x Extra options
   */
  public function migrationImport($opts = self::OPTS + [
    'migration|m' => '',
    'extra|x' => '',
  ]) {
    $this->say("Migration import.");

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    $this->migrateImport($opts['migration'], $opts['extra']);
    $this->rebuildCache();
  }

  /**
   * Migrate status.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   * @option $migration|m Migration name
   * @option $extra|x Extra options
   */
  public function migrationStatus($opts = self::OPTS + [
    'migration|m' => '',
    'extra|x' => '',
  ]) {
    $this->say("Migration status.");

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    $this->migrateStatus($opts['migration'], $opts['extra']);
    $this->rebuildCache();
  }

  /**
   * Reset a active migration's status to idle.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   * @option $migration|m Migration name
   */
  public function migrationResetStatus($opts = self::OPTS + [
    'migration|m' => '',
  ]) {
    $this->say("Migration reset status.");

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    $this->migrateResetStatus($opts['migration']);
    $this->rebuildCache();
  }

  /**
   * Migrate rollback.
   *
   * @param array $opts Options.
   *   Options.
   *
   * @option $environment|e Environment
   * @option $site|s Site
   * @option $migration|m Migration name
   * @option $extra|x Extra options
   */
  public function migrationRollback($opts = self::OPTS + [
    'migration|m' => '',
    'extra|x' => '',
  ]) {
    $this->say("Migration Rollback.");

    // Init parameters.
    $this->init($opts['environment'], $opts['site']);

    $this->migrateRollback($opts['migration'], $opts['extra']);
    $this->rebuildCache();
  }

  /*
   * == PRIVATE FUNCTIONS ==.
   */

  /**
   * Init.
   *
   * @param string $environment
   *   Environment variable (local|stage|prod|custom1|..).
   * @param string $site
   *   Match to the sites that you want to start, useful for multi-site.
   *   In the case of a single installation to use/leave 'default'.
   *
   * @throws \Robo\Exception\TaskException
   */
  private function init($environment = 'local', $site = 'default') {

    if ($site == 'default' && !$this->use_default) {
      throw new TaskException($this, 'Default site not implement. Please select another site.');
    }

    // Set Environment and site for load configuration file.
    $this->setEnvironment($environment);
    $this->setSite($site);
    $this->setPathProperties(self::FOLDER_PROPERTIES);
    $this->properties = $this->getProperties();

    // Load Twig and Filesystem.
    $loader = new Twig_Loader_Filesystem($this->getBasePath() . '/' . self::FOLDER_TEMPLATES);
    $this->twig = new Twig_Environment($loader);
  }

  /**
   * Print info site.
   */
  private function getInfoSite() {
    $this->say('Site Drupal Info');
    $this->getDrushWithUri()->status()->run();
  }

  /**
   * Setup file and directory for installation.
   *
   * Include:
   *  - clear file and directory site;
   *  - init file and directory site.
   */
  private function setupInstallation() {
    $this->clearFilesystem();
    $this->initFilesystem();
  }

  /**
   * Install Drupal.
   */
  private function install() {
    $this->say('Install Drupal');
    $properties = $this->properties;
    $this->getDrush()
      ->siteName($properties['site_configuration']['name'])
      ->siteMail($properties['site_configuration']['mail'])
      ->accountMail($properties['account']['mail'])
      ->accountName($properties['account']['name'])
      ->accountPass($properties['account']['pass'])
      ->dbUrl($properties['database']['url'])
      ->locale($properties['site_configuration']['locale'])
      ->sitesSubdir($properties['site_configuration']['sub_dir'])
      ->siteInstall($properties['site_configuration']['profile'])
      ->run();
  }

  /**
   * Install Drupal from config.
   *
   * TODO: update the current method of importing configurations!
   * Args <em>--config-dir</em>, that defined a path pointing to a full
   * set of configuration which should be imported after installation
   * (https://drushcommands.com/drush-8x/core/site-install/ ), not worked well.
   * Currently use <em>Configuration installer</em>
   * (https://www.drupal.org/project/config_installer)
   */
  private function installFromConfig() {

    $this->say('Install Drupal from Config');
    $properties = $this->properties;

    // Override properties.
    /* TODO: check if config_installer exist */
    /*
    if (!class_exists("\\Drupal\\config_installer\\Storage")) {

      $message = "Profile installation <em>Configuration installer</em>
        not found! Please download or include in your composer.json
        if you want to use function <em>InstallDrupalFromConfig</em>\n";
      $this->printTaskError($message);

      // TODO: create or use a exception better
      throw new Exception($message);
    }
    */
    $properties['site_configuration']['profile'] = 'config_installer';

    // Create settings.php: used from config_installer.
    /* TODO: remove this after change or update import configuration */
    $this->createFileSettings();
    $this->configureSettings();

    $this->getDrush()
      ->siteName($properties['site_configuration']['name'])
      ->siteMail($properties['site_configuration']['mail'])
      ->accountMail($properties['account']['mail'])
      ->accountName($properties['account']['name'])
      ->accountPass($properties['account']['pass'])
      ->dbUrl($properties['database']['url'])
      ->sitesSubdir($properties['site_configuration']['sub_dir'])
      /* ->args('--config-dir='.$properties['site_configuration']['config_dir']) */
      ->siteInstall($properties['site_configuration']['profile'])
      ->run();
  }

  /**
   * Clear Cache.
   */
  private function rebuildCache() {
    $this->say('Rebuild cache');
    $this->getDrushWithUri()
      ->drush('cache-rebuild')
      ->run();
  }

  /**
   * Execute Core cron.
   */
  private function coreCron() {
    $this->say('Core cron');
    $this->getDrushWithUri()
      ->drush('core-cron')
      ->run();
  }

  /**
   * Downloads translations from localize.drupal.org.
   */
  private function updateTranslations() {
    $this->say('Update translations');
    $this->getDrushWithUri()
      ->drush('locale-update')
      ->run();
  }

  /**
   * Install modules.
   *
   * @param array $modules
   *   If you want install modules.
   */
  private function installModules(array $modules) {

    // Convert array to string.
    $modules = implode(' ', $modules);

    $this->say('Install modules: ' . $modules);
    $this->getDrushWithUri()
      ->drush('pm-enable ' . $modules)
      ->run();
  }

  /**
   * Uninstall modules.
   *
   * @param array $modules
   *   If you want install modules.
   */
  private function uninstallModules(array $modules) {

    // Convert array to string.
    $modules_list = implode(' ', $modules);
    $this->say('Uninstall modules: ' . $modules_list);

    $this->getDrushWithUri()
      ->drush('pm-uninstall ' . $modules_list)
      ->run();
  }

  /**
   * Install modules for migrate.
   */
  private function migrateInstall() {
    $migration = $this->properties['migration'];
    $this->installModules($migration['modules']);
  }

  /**
   * Uninstall modules for migrate.
   */
  private function migrateUninstall() {
    $migration = $this->properties['migration'];
    $this->uninstallModules($migration['modules']);
  }

  /**
   * Perform one or more migration processes.
   *
   * @param string $migration
   *   Group to migrate. Empty to all.
   * @param string $extra
   *   Extra options. es. --limit
   */
  private function migrateImport($migration = '', $extra = '') {
    $command = 'migrate-import ' . (!empty($migration) ? "--group={$migration}" : '--all') . $extra;
    $command .= $extra;
    $this->getDrushWithUri()
      ->drush($command)
      ->run();
  }

  /**
   * Migrate status.
   *
   * @param string $migration
   *   Migration name.
   * @param string $extra
   *   Extra options. es. --limit
   */
  private function migrateStatus($migration = '', $extra = '') {
    $command = 'migrate-status ' . (!empty($migration) ? "--group={$migration}" : '') . $extra;
    $this->getDrushWithUri()
      ->drush($command)
      ->run();
  }

  /**
   * Reset a active migration's status to idle.
   *
   * @param string $migration
   *   Migration name.
   */
  private function migrateResetStatus($migration = '') {
    $command = 'migrate-reset-status ' . (!empty($migration) ? "$migration" : '--all');
    $this->getDrushWithUri()
      ->drush($command)
      ->run();
  }

  /**
   * Migrate rollback.
   *
   * @param string $migration
   *   Group to rollback.
   * @param string $extra
   *   Extra options. es. --limit
   */
  private function migrateRollback($migration = '', $extra = '') {
    $command = 'migrate-rollback ' . (!empty($migration) ? "--group={$migration}" : '--all') . $extra;
    $this->getDrushWithUri()
      ->drush($command)
      ->run();
  }

  /**
   * Clears the directory structure for site.
   */
  private function clearFilesystem() {
    $this->say('Clears the directory structure for site');
    $base_path = "{$this->getSiteRoot()}/sites/{$this->properties['site_configuration']['sub_dir']}";
    $this->taskFilesystemStack()
      ->chmod($base_path, 0775, 0000, TRUE)
      ->chmod($base_path, 0775)
      ->remove($base_path . '/files')
      ->remove($base_path . '/settings.php')
      ->remove($base_path . '/services.yml')
      ->run();
  }

  /**
   * Creates the directory structure for site.
   */
  private function initFilesystem() {
    $this->say('Creates the directory structure for site');
    $base_path = "{$this->getSiteRoot()}/sites/{$this->properties['site_configuration']['sub_dir']}";
    $this->taskFilesystemStack()
      ->chmod($base_path, 0775, 0000, TRUE)
      ->mkdir($base_path . '/files')
      ->chmod($base_path . '/files', 0775, 0000, TRUE)
      ->copy($base_path . '/default.settings.php', $base_path . '/settings.php')
      ->copy($base_path . '/default.services.yml', $base_path . '/services.yml')
      ->run();
  }

  /**
   * Setup correct permission for settings.php.
   *
   * @TODO: update permission.
   */
  private function protectSite() {
    $base_path = "{$this->getSiteRoot()}/sites/{$this->properties['site_configuration']['sub_dir']}";
    $this->say('Protect settings.php');
    $this->taskFilesystemStack()
      ->chmod($base_path . '/default.settings.php', 0755)
      ->chmod($base_path . '/settings.php', 0755)
      ->chmod($base_path . '/default.services.yml', 0755)
      ->chmod($base_path . '/services.yml', 0755)
      ->chmod($base_path, 0775)
      ->run();
  }

  /**
   * Create file settings for site from default.settings.php.
   */
  private function createFileSettings() {
    $this->say('Create file settings.php');
    $site_path = "{$this->getSiteRoot()}/sites/{$this->properties['site_configuration']['sub_dir']}";
    $this->taskFilesystemStack()
      ->chmod($site_path, 0775)
      ->copy("{$site_path}/default.settings.php", "{$site_path}/settings.php")
      ->run();
  }

  /**
   * Create file services.yml for site from default.services.yml.
   */
  private function createFileServices() {
    $this->say('Create file services.yml');
    $site_path = "{$this->getSiteRoot()}/sites/{$this->properties['site_configuration']['sub_dir']}";
    $this->taskFilesystemStack()
      ->chmod($site_path, 0775)
      ->copy("{$site_path}/default.services.yml", "{$site_path}/services.yml")
      ->run();
  }

  /**
   * Configure settings.
   *
   * Using templates based on the name of the site and the environment,
   * update the settings file.
   *
   * TODO: load and use extra.build.*.*.yml configuration
   */
  private function configureSettings() {
    $this->say('Configure settings');

    $settings_file_path = "{$this->getSiteRoot()}/sites/{$this->properties['site_configuration']['sub_dir']}/settings.php";

    $this->taskFilesystemStack()->chmod($settings_file_path, 0777)->run();

    // Get Variable.
    $db_url = $this->getProperties()['database']['url'];
    $settings['database'] = $this->convertDatabaseFromDatabaseUrl($db_url);
    $settings['hash_salt'] = Crypt::randomBytesBase64(55);
    $variables = NestedArray::mergeDeepArray([
      $this->getProperties(),
      $settings
    ], TRUE);

    $template_name = "settings.{$this->environment}.{$this->properties['site_configuration']['sub_dir']}.html.twig";
    $local_settings = $this->templateRender($template_name, $variables);

    $task_write = $this->task(\Robo\Task\File\Write::class, $settings_file_path);
    $task_write->line($local_settings)->append()->run();
  }

  /**
   * Set system.site.uuid.
   */
  private function setSystemSiteUuid() {
    $system_site_uuid = $this->properties['site_configuration']['site_uuid'];
    $this->getDrushWithUri()
      ->drush("config-set \"system.site\" uuid \"{$system_site_uuid}\"")
      ->drush('cache-rebuild')
      ->run();
  }

  /**
   * Import config.
   *
   * Import configurations from folder defined in the configuration file yml.
   */
  private function importConfig() {

    // Uninstall modules dev.
    $this->uninstallModules($this->properties['modules_dev']);

    $this->say('Import config');

    // This task refer to $config_directories[CONFIG_SYNC_DIRECTORY].
    $this->getDrushWithUri()
      ->drush('cim')
      ->run();

    // Reinstall modules dev (not in production).
    if (!$this->isProduction()) {
      $this->installModules($this->properties['modules_dev']);
    }
  }

  /**
   * Import config partial.
   *
   * Import configurations from folder defined in the configuration file yml.
   * Used drush with option command <em>partial</em>:
   * "Allows for partial config imports from the source directory.
   * Only updates and new configs will be processed with this flag
   * (missing configs will not be deleted)."
   *
   * @see https://drushcommands.com/drush-8x/config/config-import/
   */
  private function importConfigPartial() {

    // Uninstall module dev.
    $this->uninstallModules($this->properties['modules_dev']);

    $this->say('Import config (partial)');

    // This task refer to $config_directories[CONFIG_SYNC_DIRECTORY].
    $this->getDrushWithUri()
      ->drush('cim --partial')
      ->run();

    // Reinstall modules dev (not in production).
    if (!$this->isProduction()) {
      $this->installModules($this->properties['modules_dev']);
    }
  }

  /**
   * Export configuration.
   *
   * Export configurations in the folder defined in the configuration file yml.
   */
  private function exportConfig() {

    // Uninstall modules dev.
    $this->uninstallModules($this->properties['modules_dev']);

    $this->say('Export config');

    // This task refer to $config_directories[CONFIG_SYNC_DIRECTORY].
    $this->getDrushWithUri()
      ->drush('cex')
      ->run();

    // Reinstall modules dev (not in production).
    if (!$this->isProduction()) {
      $this->installModules($this->properties['modules_dev']);
    }
  }

  /**
   * Import features.
   *
   * @param string $features_name
   *   Name of features.
   */
  private function importFeatures($features_name) {
    $this->getDrushWithUri()
      ->drush('features-import ' . $features_name)
      ->run();
  }

  /**
   * Import all features.
   *
   * @param string $bundle
   *   Name of feature bundle.
   */
  private function importAllFeatures($bundle = 'athena_features') {
    $this->getDrushWithUri()
      ->drush('features-import-all --bundle=' . $bundle)
      ->run();
  }

  /**
   * Backup database.
   *
   * The folder destination is configured in file yml:
   * - backups:
   *   - export_dir: destination backups.
   */
  private function backupDatabase() {

    if (!$this->isSiteInstalled()) {
      $this->say("Backup Database not execute. Site not installed.");
      return;
    }

    $this->say('Backup database.');

    $database_name = date("Y") . date("m") . date("d") . '_' . date("H") . date("i") . date("s") . '.sql';
    $folder_backups = $this->getBasePath() . "/" . self::FOLDER_BACKUPS_DATABASE;
    $this->getDrushWithUri()
      ->drush("sql-dump --result-file={$folder_backups}/{$this->properties['site_configuration']['uri']}_{$database_name} --ordered-dump")
      ->run();
  }

  /**
   * Import database.
   *
   * The source folder and name of dump are configured in file yml:
   * - backups:
   *   - import_dir: source backups;
   *   - import_dump: name of dump.
   *
   * @param null|string $dump_name
   *   A name of dump.
   */
  private function importDatabase($dump_name = NULL) {

    // Clear exist database.
    $this->getDrushWithUri()
      ->drush("sql-drop")
      ->run();

    $path_backup = $this->getBasePath() . "/" . self::FOLDER_IMPORT_DATABASE;

    if (!isset($dump_name)) {
      $finder = new Finder();
      $finder->files()->name('*.sql')->sortByChangedTime();
      $finder->in($path_backup);
      $iterator = $finder->getIterator();
      $iterator->rewind();
      /** @var SplFileInfo $file */
      $file = $iterator->current();
      $dump_name = $file->getRelativePathname();
    }

    $this->say('Import dump database: ' . $dump_name);
    $this->getDrushWithUri()
      ->drush("sql-cli < \"{$path_backup}/{$dump_name}\"")
      ->run();
  }

  /**
   * Update entity.
   */
  private function entityUpdates() {
    $this->say('Entity Update');
    $this->getDrushWithUri()
      ->drush('entity-updates')
      ->run();
  }

  /**
   * Exec command.
   *
   * @param string $command
   *   Command to execute.
   */
  private function drush($command) {
    $this->say('Exec command');
    $this->getDrushWithUri()
      ->drush($command)
      ->run();
  }

  /**
   * Retrieve a DrushStack.
   *
   * @return \Boedah\Robo\Task\Drush\DrushStack
   *   Retrieve an object DrushStack with the root folder set.
   */
  private function getDrush() {
    /** @var \Boedah\Robo\Task\Drush\DrushStack $drush_stack */
    $drush_stack = $this->task(\Boedah\Robo\Task\Drush\DrushStack::class, $this->properties['drush_path']);
    $drush_stack->drupalRootDirectory("{$this->getBasePath()}/{$this->properties['site_configuration']['root']}");
    return $drush_stack;
  }

  /**
   * Retrieve a DrushStack with the URI configuration set.
   *
   * @return \Boedah\Robo\Task\Drush\DrushStack
   *   Retrieve an object DrushStack with the root folder and URI sets.
   */
  private function getDrushWithUri() {
    return $this->getDrush()
      ->uri($this->properties['site_configuration']['uri']);
  }

  /**
   * Get Base Path (path absolute files).
   *
   * @return string
   *   Path absolute files.
   */
  private function getBasePath() {
    return $this->properties['base_path'];
  }

  /**
   * Get Site Path (path absolute files).
   *
   * @return string
   *   Path absolute files of root installation.
   */
  private function getSiteRoot() {
    return "{$this->getBasePath()}/{$this->properties['site_configuration']['site_root']}";
  }

  /**
   * Retrieve if site is installed. Check exist settings.php.
   *
   * TODO: update!I do not like.
   *
   * @return bool
   *   True if site is installed.
   */
  private function isSiteInstalled() {
    $filesystem = new Filesystem();
    return $filesystem->exists("{$this->getSiteRoot()}/sites/{$this->properties['site_configuration']['sub_dir']}/settings.php");
  }

  /**
   * Return a skip modules for export/import configuration.
   *
   * @return string
   *   String of all modules to skipped.
   */
  private function skipModules() {
    $modules = '';
    $array_modules = $this->properties['modules_dev'];
    $modules .= implode(',', $array_modules);
    return $modules;
  }

  /**
   * Renders a template.
   *
   * @param string $template
   *   Template.
   * @param array $variables
   *   Variables.
   *
   * @return string
   *   Template rendered.
   */
  private function templateRender($template, $variables) {
    return $this->twig->render($template, $variables);
  }

  /**
   * Set environment.
   *
   * @param string $environment
   *   Environment.
   */
  private function setEnvironment($environment = 'local') {
    $this->environment = $environment;
  }

  /**
   * Is production.
   */
  private function isProduction() {
    return $this->environment == self::ENV_PROD;
  }

  /**
   * Set Site.
   *
   * @param string $site
   *   Site.
   */
  private function setSite($site = 'default') {
    $this->site = $site;
  }

  /**
   * Set PathProperties.
   *
   * @param string $pathProperties
   *   Properties.
   */
  private function setPathProperties($pathProperties) {
    $this->pathProperties = $pathProperties;
  }

  /**
   * Get Properties.
   *
   * @return array
   *   Properties.
   */
  private function getProperties() {
    $file_name = "build.{$this->environment}.{$this->site}.yml";
    $base_path = "./" . self::FOLDER_PROPERTIES;
    $file_content = file_get_contents("$base_path/$file_name");
    $properties = Yaml::parse($file_content);

    /*
     * TODO: Validation properties.
     * checks if the required fields are present:
     * - environment
     * - drush_path
     * - domain
     * - database:url
     * - site-configuration:*
     * - account:*
     */

    return $properties;
  }

  /**
   * Convert from an old-style database URL to an array of database settings.
   *
   * @param db_url
   *   A Drupal 6 db url string to convert, or an array with a 'default' element.
   * @return array
   *   An array of database values containing only the 'default' element of
   *   the db url. If the parse fails the array is empty.
   */
  private function convertDatabaseFromDatabaseUrl($db_url) {
    $db_spec = [];

    if (is_array($db_url)) {
      $db_url_default = $db_url['default'];
    }
    else {
      $db_url_default = $db_url;
    }

    // If it's a sqlite database, pick the database path and we're done.
    if (strpos($db_url_default, 'sqlite://') === 0) {
      $db_spec = [
        'driver' => 'sqlite',
        'database' => substr($db_url_default, strlen('sqlite://')),
      ];
    }
    else {
      $url = parse_url($db_url_default);
      if ($url) {
        // Fill in defaults to prevent notices.
        $url += [
          'scheme' => NULL,
          'user' => NULL,
          'pass' => NULL,
          'host' => NULL,
          'port' => NULL,
          'path' => NULL,
        ];
        $url = (object) array_map('urldecode', $url);
        $db_spec = [
          'driver' => $url->scheme == 'mysqli' ? 'mysql' : $url->scheme,
          'username' => $url->user,
          'password' => $url->pass,
          'host' => $url->host,
          'port' => $url->port,
          'database' => ltrim($url->path, '/'),
        ];
      }
    }

    return $db_spec;
  }

}
