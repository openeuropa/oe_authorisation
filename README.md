# OpenEuropa Authorisation

The OpenEuropa Authorisation module offers default features related to user authorisation for the OpenEuropa project.

It provides the following user roles:

* Site Manager (general administrative permissions)
* Support Engineer (general administrative permissions without user management)
* Editor (content related permissions)

It also provides the OpenEuropa Authorisation Syncope submodule that is used to integrate with the Syncope authorisation service.
The latter can also be provisioned as part of this repository for development purposes. See the [Project setup](#project-setup) for details.

**Table of contents:**

- [Installation](#installation)
- [Development](#development)
  - [Project setup](#project-setup)
  - [Using Docker Compose](#using-docker-compose)
  - [Disable Drupal 8 caching](#disable-drupal-8-caching)
- [Demo module](#demo-module)

## Installation

The recommended way of installing the OpenEuropa Authorisation module is via a [Composer-based workflow][2].

In your Drupal project's main `composer.json` add the following dependency:

```json
{
    "require": {
        "openeuropa/oe_authorisation": "dev-master"
    }
}
```

And run:

```
$ composer update
```

### Enable the module

In order to enable the module in your project run:

```
$ ./vendor/bin/drush en oe_authorisation
```

## Development

The OpenEuropa Authorisation project contains all the necessary code and tools for an effective development process,
such as:

- All PHP development dependencies (Drupal core included) are required by [composer.json](composer.json)
- Project setup and installation can be easily handled thanks to the integration with the [Task Runner][3] project.
- All system requirements are containerized using [Docker Composer][4]

### Project setup

Download all required PHP code by running:

```
$ composer install
```

This will build a fully functional Drupal test site in the `./build` directory that can be used to develop and showcase
the module's functionality.

Before setting up and installing the site make sure to customize default configuration values by copying [runner.yml.dist](runner.yml.dist)
to `./runner.yml` and overriding relevant properties.

To set up the project run:

```
$ ./vendor/bin/run drupal:site-setup
```

This will:

- Symlink the module in  `./build/modules/custom/oe_authorisation` so that it's available for the test site
- Setup Drush and Drupal's settings using values from `./runner.yml.dist`
- Setup PHPUnit and Behat configuration files using values from `./runner.yml.dist`

After a successful setup install the site by running:

```
$ ./vendor/bin/run drupal:site-install
```

This will:

- Install the test site
- Enable the OpenEuropa Authorisation module

**For the OpenEuropa Authorisation Syncope module you need to set up the project using Docker**

### Using Docker Compose

The setup procedure described above can be sensitively simplified by using Docker Compose.

Requirements:

- [Docker][6]
- [Docker-compose][7]

Copy docker-compose.yml.dist into docker-compose.yml.

You can make any alterations you need for your local Docker setup. However, the defaults should be enough to set the project up.

Run:

```
$ docker-compose up -d
```

Syncope Console is available at: http://localhost:28080/syncope-console (admin/password)

Install PHP dependencies by running:

```
$ docker-compose exec web composer install
```

The Syncope provisioning should happen before the site is installed. To provision the Syncope container you need to run this command:

```
$ docker-compose exec web ./vendor/bin/run oe-authorisation-service:setup
```

Then, in order to have a Site realm and system user for your test site, you can run this command:

```
$ docker-compose exec web ./vendor/bin/run oe-authorisation-service:site-setup --site_id=sitea
```

(where `sitea` is the Site ID (realm) of your local site).

Then you can install the Drupal site:

```
$ docker-compose exec web ./vendor/bin/run drupal:site-install
```

Your test site will be available at [http://localhost:8080/build](http://localhost:8080/build) and the OpenEuropa Authorisation Syncope module
will be enabled by default.

Run tests as follows:

```
$ docker-compose exec web ./vendor/bin/phpunit
$ docker-compose exec web ./vendor/bin/behat
```

### Disable Drupal 8 caching

Manually disabling Drupal 8 caching is a laborious process that is well described [here][8].

Alternatively you can use the following Drupal Console commands to disable/enable Drupal 8 caching:

```
$ ./vendor/bin/drupal site:mode dev  # Disable all caches.
$ ./vendor/bin/drupal site:mode prod # Enable all caches.
```

Note: to fully disable Twig caching the following additional manual steps are required:

1. Open `./build/sites/default/services.yml`
2. Set `cache: false` in `twig.config:` property. E.g.:
```
parameters:
 twig.config:
   cache: false
```
3. Rebuild Drupal cache: `./vendor/bin/drush cr`

This is due to the following [Drupal Console issue][9].

[2]: https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies#managing-contributed
[3]: https://github.com/openeuropa/task-runner
[4]: https://docs.docker.com/compose
[7]: https://www.drupal.org/project/config_devel
[8]: https://www.drupal.org/node/2598914
[9]: https://github.com/hechoendrupal/drupal-console/issues/3854
[10]: https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules
[11]: https://www.drush.org/
