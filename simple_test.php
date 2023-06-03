<?php

use Micoya\PhpSnowflakeShm\Config;
use Micoya\PhpSnowflakeShm\MachineIdHelper;
use Micoya\PhpSnowflakeShm\SnowflakeFactory;
use Micoya\PhpSnowflakeShm\SnowflakeShm;

include 'vendor/autoload.php';

/**
 * Due to some environmental reasons,I simply put unit-test in this file.
 */

/**
 * @param mixed $v1
 * @param mixed $v2
 * @return void
 * @throws Exception
 */
function assertSame($expect, $value)
{
    if ($expect !== $value) {
        throw new \Exception("expect {$expect}, but {$value}");
    }
}

/**
 * @return void
 * @throws Exception
 */
function testHelper()
{

    $h = new MachineIdHelper(8);
    $h->pushToHigh(1, 0);
    $h->pushToHigh(7, 127);
    assertSame(127, $h->make());

    $h = new MachineIdHelper(4);
    assertSame(0, $h->make());

    $h = new MachineIdHelper(1);
    $h->pushToHigh(1, 1);
    assertSame(1, $h->make());

    $h = new MachineIdHelper(5);
    $h->pushToHigh(1, 0);
    $h->pushToHigh(2, 1);
    $h->pushToHigh(1, 1);
    $h->pushToHigh(1, 0);
    // 00110
    assertSame(6, $h->make());

    $h = new MachineIdHelper(10);
    $h->pushToHigh(10, 1023);
    assertSame(1023, $h->make());

}

function testSnowflake()
{

    $config = new Config();
    $config->machine_id_length = 12;
    $config->start_timestamp = 1685603045000;

    $s = new SnowflakeShm(8, $config);

    $t = (int)(microtime(true) * 1000);
    $id = $s->id();

    $timestamp = ($id >> 22) + 1685603045000;
    $machine_id = ($id >> 10) & 0xfff;
    $seq = $id & 0x3ff;

    if (abs($t - $timestamp) > 5) {
        throw new \Exception("Wrong timestamp");
    }
    assertSame(8, $machine_id);
    assertSame(0, $seq);

    $s = new SnowflakeShm(252, $config);
    $id = $s->id();
    $machine_id = ($id >> 10) & 0xff;
    assertSame(252, $machine_id);

    $config->machine_id_length = 12;
    $s = new SnowflakeShm(4095, $config);
    $id = $s->id();
    $machine_id = ($id >> 10) & 0xfff;
    assertSame(4095, $machine_id);


    $catch = false;
    try {
        SnowflakeFactory::makeShm(PHP_INT_MAX);
    } catch (\Exception $e) {
        $catch = true;
    }
    assertSame(true, $catch);

    $catch = false;
    try {
        $config->machine_id_length = 99;
        SnowflakeFactory::makeShm(PHP_INT_MAX, $config);
    } catch (\Exception $e) {
        $catch = true;
    }
    assertSame(true, $catch);


}

function testMultiProcess()
{
    $total_process = 5;
    $id_per_process = 10000;

    $pid_list = [];
    for ($i = 0; $i < $total_process; $i++) {
        $pid = pcntl_fork();
        if ($pid == -1) {
            echo "Failed to fork";
            exit(-1);
        } else if ($pid == 0) {
            $conf = new Config();
            $conf->machine_id_length = 12;
            $conf->start_timestamp = 0;

            $snow = new SnowflakeShm(0, $conf);

            $r = [];

            for ($j = 0; $j < $id_per_process; $j++) {
                $r[] = $snow->id();
            }

            file_put_contents("r{$i}.txt", implode("\n", $r) . "\n");
            exit(0);
        } else {
            $pid_list[] = $pid;
        }
    }

    $st = microtime(true);
    foreach ($pid_list as $pid) {
        $code = 0;
        pcntl_waitpid($pid, $code);
    }
    $t = microtime(true) - $st;
    $t = (int)($t * 1000);
    $t = $t / ($total_process * $id_per_process);
    echo "{$total_process}*{$id_per_process} total, {$t}ms per op\n";


    $content = '';
    for ($i = 0; $i < $total_process; $i++) {
        $content = $content . "\n" . file_get_contents("r{$i}.txt");
        unlink("r{$i}.txt");
    }
    $content = explode("\n", $content);
    $m = [];
    foreach ($content as $c) {
        if (empty($c)) {
            continue;
        }
        if (isset($m[$c])) {
            throw new \Exception("repeat:" . $c);
        } else {
            $m[$c] = 1;
        }
    }
}

function testSeq()
{
    // sequence check
    $max_machine_id = pow(2, 20) - 1;
    $config = new Config();
    $config->machine_id_length = 20;    // only 2bit seq, max value is 3
    $config->start_timestamp = 1685603045000;
    $s = SnowflakeFactory::makeShm($max_machine_id, $config);
    $st = (int)(microtime(true) * 1000);
    for ($i = 0; $i < 2400; $i++) {
        $id = $s->id();
        $machine_id = ($id >> 2) & $max_machine_id;
        if ($machine_id !== $max_machine_id) {
            throw new \Exception("error machine id, maybe seq is large than max");
        }
    }
    $ed = (int)(microtime(true) * 1000);
    $r = $ed - $st;
    if ($r < 600) {
        throw new \Exception("generate so fast, maybe seq is large than max");
    }
}

testHelper();
testSnowflake();
testSeq();
testMultiProcess();
