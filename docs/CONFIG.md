Config Management Workflow
======
This method leverages the native 'config sync' workflow included in Drupal 8 core, NOT features, woo! Essentially, ALL configuration is stored as .yml files in the site repo config/sync directory.

Below is a tree of the config directory illustrating the config location.

```sh
config/
└── sync
    └── ALL CONFIGURATION
```

In order to ensure the correct directory is used for config sync we need to set a settings.php variable override via SMART. This should be done on the site, so that all instances inherit the config.

```sh
// General configuration dir.
$config_directories[CONFIG_SYNC_DIRECTORY] = '../config/sync';
```

Intended Workflow
======
When making changes to configuration, instead of updating features, we'll now want to export those changes directly to .yml files, and place those files in the relevant /config directory.

When doing a full export, the sync dir is where the configs are written to, unless a --destination is speficied when running the command, `drush cex`.

The UI can also be used to export individual configurations, as well as view overridden configurations.
/admin/config/development/configuration
Configuration -> Development -> Configuration syncronization

To import changes Drush should be used be used. If the confuration is already in the config directory, simply run `drush cim`.

When viewing a diff of configuration: 
* *Active* = stored in database currently. 
* *Staged* = changes to import, aka, yml file contents.

When running a `drush cex` you may notice way more files exported than anticipated by looking at the config overview page listing overrides. This is ok. Once the export is done and the files have been placed in /config/sync, run a `git status` to see what files actually changed. You can then use `git diff` to look for changes to files you didn't expect to have changed.

**Running a content sync**

Before running a `drush hw-sync` ensure that there are no overridden configurations.

After running `drush hw-sync` you will likely see quite a few overridden changes. These changes (node types, field data, and field strcutures) should all be exported and saved in the /config/sync dir.

Useful Drush Commands
======
`drush cex` - Export ALL configuration [Documentation.](https://drushcommands.com/drush-8x/config/config-export/)
  Note the --destination option, this will allow you to specify an arbitrary directory that should receive the exported files.

`drush cim` - Import ALL configuration [Documentation](https://drushcommands.com/drush-8x/config/config-import/)

Notes
======
Make sure apache solr is enabled on the site node in SMART

Manually enable apache solr module, this should be a dependency in highwire_search...

Manually enable pathauto module and configure some rules. Don't forget to make sure to set Slash (/) to 'No action (do not replace)' in the Settings for pathauto.
