<a href="https://www.highwirepress.com"><img src="https://www.highwirepress.com/wp-content/themes/highwirepress/assets/images/highwirepress-logo.svg" height="auto" width="300"></a>

## SMART Site url(s):
 - Add your SMART site url here...

## Documentation
 - [Config Management](docs/CONFIG.md)

## Base Directory Structure
```bash
.
├── composer.json
├── config
│   └── sync
├── scripts
│   └── composer
│       └── FreebirdHandler.php
├── web
│   ├── modules
│   │   └── freebird
│   │       └── (freebird repository)
│   │   └── highwire
│   │       └── README.md
│   └── themes
│       └── highwire
│           └── README.md
└── vendor
    └── highwire
        └── hwphp
        └── marc-records-client-php
        └── personalization-client-php
        └── ...
```
This directory overview is from the base directory of your site once installed, as its more insightful towards the updated workflow.

The config/sync directory is where exported configuration for the site will live. This can be done via `drush cex` and imported via `drush cim`.

The `scripts/composer` directory is for composer scripts. The FreebirdHandler class is responsible for correctly setting up the freebird repository and profile.

The `web/modules/freebird` directory is where Freebird gets installed. It is no longer in `/vendor/highwire/freebird`.

The `web/modules/highwire` directory is where site specific custom modules should be placed.

The `web/themes/highwire` directory is where the sites theme should be placed.

HWPHP still lives in `vendor/highwire/hwphp`, along with other resources such as `personalization-client-php` or `marc-records-client-php`

If you take a look at the `.gitignore` file, you'll see how Drupal core along with freebird and contrib modules are all being excluded from the site repo. If you do not set a branch override for Freebird via SMART, you may find upon deployment that the Freebird repo is in a `detached from HEAD` state. This is due to composer deploying to specific commit hashes vs branchnames. Switching to `tags` would help with this, but that requires some additional effort in SMART/Jenkins.

## Additional Notes
Once composer install has been run, the Freebird profile will be placed in `web/profiles/contrib/freebird`, it's a symlink to the `freebird.info.yml` file present in the Freebird repo.

The Freebird repo (aka modules) will be placed in `web/modules/freebird`. This is where changes/development of Freebird code should be done for your site.

HWPHP along with other included HighWire services, such as P13N will still exist in the `vendor/highwire` directory once the site is installed.
