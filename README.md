## Description

Generate unique snowflake using PHP and shared memory. Maybe it is the fastest resolver.

It requires shmop and sysvsem, so you can't run it on Windows OS.

## Performance

* 13us per op (WSL2)
* 7us per op (Linux)

## Install

```shell
composer require micoya/php-snowflake-shm
```

## Examples

### Simple Use

```php
/**
 * Default snowflake have
 * 41 bit milliseconds timestamps
 * 12 bit machine id 
 * 10 bit sequence 
*/

// Machine-id is 3
$s = Micoya\PhpSnowflakeShm\SnowflakeFactory::makeShm(3);
echo $s->id();
```

### Custom machine-id length or time

```php
$config = new Micoya\PhpSnowflakeShm\Config();

// 16bit machine id , 6bit sequence
$config->machine_id_length = 16;
$config->start_timestamp = 1685603045000;

// use custom config
$s = Micoya\PhpSnowflakeShm\SnowflakeFactory::makeShm(3, $config);
echo $s->id();
```

## About Unit-Test

Due to some environmental reasons, I didn't use PHPUnit and instead wrote a simple script, Check simple_test.php.
