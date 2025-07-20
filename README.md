# BaksDev Ozon Support

[![Version](https://img.shields.io/badge/version-7.2.18-blue)](https://github.com/baks-dev/ozon-support/releases)
![php 8.4+](https://img.shields.io/badge/php-min%208.4-red.svg)
[![packagist](https://img.shields.io/badge/packagist-green)](https://packagist.org/packages/baks-dev/ozon-support)

Модуль техподдержки Ozon

## Установка

``` bash
$ composer require baks-dev/ozon-support
```

Для работы с модулем добавьте тип профиля Ozon Support, запустив команду:

``` bash
$ php bin/console baks:users-profile-type:ozon-support
```

Тесты

``` bash
$ php bin/phpunit --group=ozon-support
```

## Лицензия ![License](https://img.shields.io/badge/MIT-green)

The MIT License (MIT). Обратитесь к [Файлу лицензии](LICENSE.md) за дополнительной информацией.

