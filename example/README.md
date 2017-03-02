Drupal Day 2017 Environment Project
========================

The project is based on [Drupal8](https://www.drupal.org/) with using [Composer](https://getcomposer.org/) and [Robo](http://robo.li/).

Local installation 
------------

- Copy and rename the following files:
  * `./docker-compose.yml.dist` -> `./docker-composer.yml`
  * `./docroot/build/build.[env].[project].yml.dist` -> `./docroot/build/build.[env].[project].yml`
- instantiate/launch of docker containers:
  ```bash
  ./docker-compose up -d
  ```
- access the main container containing Apache-PHP, by opening the shell::
  ```bash
  ./docker exec -i -t -u www-data **_web_1 bash
  ```
- navigate to the project folder and launch the construction site for the script of the project:
  ```bash
  cd /var/www/html
  vendor/bin/robo build:new
  ```

#### Load project from configuration dir

Navigate to the project folder and launch the construction site for the script of the project:

```bash
cd /var/www/html
vendor/bin/robo build:conf
```

#### Load project from database

Copy dump of database in [./docroot/build/import-backups](docroot/build/import-backups).  
Navigate to the project folder and launch the construction site for the script of the project:

```bash
cd /var/www/html
vendor/bin/robo build:from-database
```

Export configuration
--------------------

Following modifications of Drupal8 configurations, you can automatically proceed to export through the use of a Robo command.  
The configuration relating to this project in [./docroot/config/default](docroot/config/default) folder will then be updated.   
Following a export you have to re-run the build of the project to verify the correct import.

```bash
cd /var/www/html
vendor/bin/robo configuration:export
vendor/bin/robo build:conf
```



