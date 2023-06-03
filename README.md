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

### Custom machine-id format helper

If you want to customize the composition of your machine id, for example, if you need to distinguish between machine and worker, here is a simple tool to use.

```php

// 12 bit id includes
// 4 bit machine id, current value is 15
// 8 bit worker id, current value is 1
$helper = new \Micoya\PhpSnowflakeShm\MachineIdHelper(12);
$helper->pushToHigh(4, 15);
$helper->pushToHigh(8, 1);
$result_machine_id = $helper->make();

$config = new Micoya\PhpSnowflakeShm\Config();

// 16bit machine id , 6bit sequence
$config->machine_id_length = 12;

// use custom config
$s = Micoya\PhpSnowflakeShm\SnowflakeFactory::makeShm($result_machine_id, $config);
echo $s->id();

```

## About Unit-Test

Due to some environmental reasons, I didn't use PHPUnit and instead wrote a simple script, Check simple_test.php.
