PSR Log MongoDB
===============

[![Build Status](https://travis-ci.org/subjective-php/psr-log-mongodb.svg?branch=master)](https://travis-ci.org/subjective-php/psr-log-mongodb)
[![Code Quality](https://scrutinizer-ci.com/g/subjective-php/psr-log-mongodb/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/subjective-php/psr-log-mongodb/?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/subjective-php/psr-log-mongodb/badge.svg?branch=master)](https://coveralls.io/github/subjective-php/psr-log-mongodb?branch=master)

[![Latest Stable Version](https://poser.pugx.org/subjective-php/psr-log-mongodb/v/stable)](https://packagist.org/packages/subjective-php/psr-log-mongodb)
[![Latest Unstable Version](https://poser.pugx.org/subjective-php/psr-log-mongodb/v/unstable)](https://packagist.org/packages/subjective-php/psr-log-mongodb)
[![License](https://poser.pugx.org/subjective-php/psr-log-mongodb/license)](https://packagist.org/packages/subjective-php/psr-log-mongodb)

[![Total Downloads](https://poser.pugx.org/subjective-php/psr-log-mongodb/downloads)](https://packagist.org/packages/subjective-php/psr-log-mongodb)
[![Monthly Downloads](https://poser.pugx.org/subjective-php/psr-log-mongodb/d/monthly)](https://packagist.org/packages/subjective-php/psr-log-mongodb)
[![Daily Downloads](https://poser.pugx.org/subjective-php/psr-log-mongodb/d/daily)](https://packagist.org/packages/subjective-php/psr-log-mongodb)

This is an implementation of [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) using MongoDB as backend storage.

Document Structure
------------------
Each mongo document constructed will have the following fields.
* __timestamp__ The current UTC date/time
* __level__ The [RFC-5424](https://tools.ietf.org/html/rfc5424) Log Level
* __message__ The log message
* __context__ Extraneous information that does not fit well in a string

Example Document
----------------
```json
{
	"_id" : ObjectId("57fc0050fc77ae5c017e52b1"),
	"timestamp" : ISODate("2016-10-08T02:02:12.944Z"),
	"level" : "info",
	"message" : "api access",
	"context" : {
		"method" : "GET",
		"resource" : "/widgets/123",
		"status" : 200
	}
}
```

The logger does not handle log retention.
[Expire Data from Collections by Setting TTL](https://docs.mongodb.com/v3.2/tutorial/expire-data/)

Basic Usage
-----------

```php
<?php

use SubjectivePHP\Psr\Log\MongoLogger;
use MongoDB\Client;

$collection = (new Client())->selectDatabase('testing')->selectCollection('logs');

$logger = new MongoLogger($collection);

$logger->debug('Some debug info');
```

Message/Context Interpolation
-----------------------------

The message may contain placeholders which can be replaced with values from the context array. In the example below the final logged message will be
`User chadicus was created`

```php
<?php

use SubjectivePHP\Psr\Log\MongoLogger;
use MongoDB\Client;

$collection = (new Client())->selectDatabase('testing')->selectCollection('logs');

$logger = new MongoLogger($collection);

$logger->info('User {username} was created', ['username' => 'chadicus']);
```

## Requirements

PSR Log MongoDB requires PHP 7.0 (or later).

## Composer
To add the library as a local, per-project dependency use [Composer](http://getcomposer.org)! Simply add a dependency on `subjective-php/psr-log-mongodb` to your project's `composer.json`.
```sh
composer require subjective-php/psr-log-mongodb
```

## Contact
Developers may be contacted at:

 * [Pull Requests](/../../pulls)
 * [Issues](/../../issues)

## Run Build
With a checkout of the code get [Composer](http://getcomposer.org) in your PATH and run:

```sh
composer install
./vendor/bin/phpunit
./vendor/bin/phpcs
