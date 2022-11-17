![CrowdSec Logo](images/logo_crowdsec.png)
# CrowdSec PHP remediation engine

## User Guide


<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

<!-- END doctoc generated TOC please keep comment here to allow auto update -->


## Description

The main purpose of this library is to determine what action to take for a given IP.

This kind of action is called a remediation and can be: 

- a `bypass`: in case there is no associated CrowdSec decision for the IP (i.e. this is a clean IP).
- any of available CrowdSec decision types : `ban`, `captcha` and other custom types.


## Features

- CrowdSec CAPI remediations
  - Retrieve and cache decisions from CAPI
    - Handle IP scoped decisions for Ipv4 and IPv6
    - Handle Range scoped decisions for IPv4
  - Determine remediation for a given IP using the cached decisions.
  

- Overridable cache handler (built-in support for `Redis`, `Memcached` and `PhpFiles` caches)


- Large PHP matrix compatibility: 7.2.x, 7.3.x, 7.4.x, 8.0.x and 8.1.x



## Quick start

### Installation

First, install CrowdSec PHP remediation engine via the [composer](https://getcomposer.org/) package manager:
```bash
composer require crowdsec/remediation-engine
```

Please see the [Installation Guide](./INSTALLATION_GUIDE.md) for mor details.

### CapiRemediation instantiation

To instantiate a CapiRemediation object, you have to:

- Pass its `configs` array as a first parameter. You will find below [the list of other available
  settings](#remediation-engine-configurations).


- Pass a CrowdSec CAPI Watcher client as a second parameter. Please see [CrowdSec CAPI PHP client](https://github.com/crowdsecurity/php-capi-client) for details.


- Pass an implementation of the provided `CacheStorage\AbstractCache` in the third parameter.  You will find 
  examples of such implementation with the `CacheStorage\PhpFiles`,  `CacheStorage\Memcached` and `CacheStorage\Redis` 
  class.


- Optionally, to log some information, you can pass an implementation of the `Psr\Log\LoggerInterface` as a fourth
    parameter. You will find an example of such implementation with the provided `Logger\FileLog` class.


```php
use CrowdSec\CapiClient\Storage\FileStorage;
use CrowdSec\CapiClient\Watcher;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CapiRemediation;
use CrowdSec\RemediationEngine\Logger\FileLog;

// Init logger
$logger = new FileLog(['debug_mode' => true]);
// Init client
$clientConfigs = [
    'machine_id_prefix' => 'remediationtest',
    'scenarios' => ['crowdsecurity/http-sensitive-files'],
];
$capiClient = new Watcher($clientConfigs, new FileStorage(), null, $logger);
// Init PhpFiles cache storage
$cacheFileConfigs = [
    'fs_cache_path' => __DIR__ . '/.cache',
];
$phpFileCache = new PhpFiles($cacheFileConfigs, $logger);
// Init CAPI remediation
$remediationConfigs = [];
$remediationEngine = new CapiRemediation($remediationConfigs, $capiClient, $phpFileCache, $logger);
```
#### CapiRemediation features

Once your CAPI remediation engine is instantiated, you can perform the following calls:


##### Retrieve fresh decisions from CAPI

```php
$remediationEngine->refreshDecisions();
```

This method will use the CrowdSec CAPI client (`$capiClient`) to retrieve arrays of new and deleted decisions 
from CAPI. Then, new decisions will be cached using the `CacheStorage` implementation (`$phpFileCache` here) and 
deleted ones will be removed if necessary.

Practically, you should use some cron task to refresh decisions on a daily basis (you could increase frequency but 
there should be at least two hours between each refresh). 


##### Get remediation for an IP

```php
$ip = ...;// Could be the current user IP
$remediationEngine->getIpRemediation($ip);
```

This method will ask the `CacheStorage` to know if there are any decisions matching the IP in cache. If there is no 
cached decision, a `bypass` will be return. If there are one or more decisions, the decision type with the highest priority 
will be return. By default, remediation priority rule is just `ban > bypass` but it can be fully configured using 
the `$remediationConfigs` parameter.


##### Clear cache

```php
$remediationEngine->clearCache();
```

This method will delete all the cached items.

##### Prune cache

```php
$remediationEngine->pruneCache();
```

Unlike Memcached and Redis, there is no PhpFiles pruning mechanism that automatically removes expired items.
Thus, if you are using the PhpFiles cache, you should use this method.


## Remediation engine configurations

The first parameter `$configs` of the CapiRemediation constructor can be used to pass the following settings:

### Remediation priorities

```php
$configs = [
        ... 
        'ordered_remediations' => ['ban', 'captcha']
        ...
];
```

The `ordered_remediations` setting accepts an array of remediations ordered by priority. 

If there are more than one decision for an IP, remediation with the highest priority will be return.

The specific remediation `bypass` will always be considered as the lowest priority (there is no need to specify it 
in this config).

This setting is not required. If you don't set any value, `['ban']` will be used by default.


In the example above, priorities can be summarized as `ban > captcha > bypass`.


### Remediation fallback

```php
$configs = [
        ... 
        'fallback_remediation' => 'ban'
        ...
];
```

The `fallback_remediation` setting will be used to determine which remediation to use in case a decision contain a 
remediation that does not belong to the `ordered_remediations` setting.

This setting is not required. If you don't set any value, `'bypass'` will be used by default.

If you set some value, be aware to include this value in the `ordered_remediations` setting too.

In the example above, if a retrieved decision contains a `mfa` remediation, the `ban` fallback will be use instead.



