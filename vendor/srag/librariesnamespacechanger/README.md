Change the namespace of the libraries on dump-autoload to a plugin specific namespace

### Usage

#### Composer
First add the following to your `composer.json` file:
```json
"require": {
    "srag/librariesnamespacechanger": ">=0.1.0"
},
"config": {
    "optimize-autoloader": true,
    "sort-packages": true,
    "classmap-authoritative": true
},
"scripts": {
    "pre-autoload-dump": "srag\\LibrariesNamespaceChanger\\LibrariesNamespaceChanger::rewriteLibrariesNamespaces"
}
```

The optimized composer autoload is mandatory otherwise it will not work.

This script will change the namespace of the libraries on dump-autoload to a plugin specific namespace.

For instance the Library `DIC` and the the plugin `HelpMe`, the base namespace is `srag\DIC\HelpMe\`.

So you have to adjust it's namespaces in your code such in `classes` or `src` folder. You can use the replace feature of your IDE.

So you can force to use your libraries classes in the `vendor` folder of your plugin and come not in conflict to other plugins with different library versions and you don't need to adjust your plugins to newer library versions until you run `composer update` on your plugin.

It support the follow libraries:
* [srag/activerecordconfig](https://packagist.org/packages/srag/activerecordconfig)
* [srag/assessmentquestion](https://packagist.org/packages/srag/assessmentquestion)
* [srag/bexiocurl](https://packagist.org/packages/srag/bexiocurl)
* [srag/commentsui](https://packagist.org/packages/srag/commentsui)
* [srag/cqrs](https://packagist.org/packages/srag/cqrs)
* [srag/custominputguis](https://packagist.org/packages/srag/custominputguis)
* [srag/dclextensions](https://packagist.org/packages/srag/dclextension)
* [srag/dic](https://packagist.org/packages/srag/dic)
* [srag/gitcurl](https://packagist.org/packages/srag/gitcurl)
* [srag/iliascomponent](https://packagist.org/packages/srag/iliascomponent)
* [srag/jasperreport](https://packagist.org/packages/srag/jasperreport)
* [srag/jiracurl](https://packagist.org/packages/srag/jiracurl)
* [srag/notifications4plugin](https://packagist.org/packages/srag/notifications4plugin)
* [srag/removeplugindataconfirm](https://packagist.org/packages/srag/removeplugindataconfirm)
* [srag/tableui](https://packagist.org/packages/srag/tableui)

### php7backport
If your plugin needs a PHP 5.6 compatible of version of the library, you can also add additionally the follow composer script:
```json
 "post-update-cmd": "srag\\LibrariesNamespaceChanger\\PHP7Backport::PHP7Backport"
```

It uses the https://github.com/ondrejbouda/php7backport.git repo, but provides it as a composer script and patches it, amongst other things, it fix interfaces

### Requirements
* PHP >=5.6

### Adjustment suggestions
* External users can report suggestions and bugs at https://plugins.studer-raimann.ch/goto.php?target=uihk_srsu_LNAMESPACECHANGER
* Adjustment suggestions by pull requests via github
* Customer of studer + raimann ag: 
	* Adjustment suggestions which are not yet worked out in detail by Jira tasks under https://jira.studer-raimann.ch/projects/LNAMESPACECHANGER
	* Bug reports under https://jira.studer-raimann.ch/projects/LNAMESPACECHANGER
