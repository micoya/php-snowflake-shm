<?php

namespace Micoya\PhpSnowflakeShm;

class SnowflakeShm implements Snowflake
{

    /**
     * 16 bytes shared memory length
     * -------------------------------
     * | int64 timestamp | int64 seq |
     * -------------------------------
     */
    const SHM_LENGTH = 16;

    /**
     * Shared memory resources
     * @var false|resource
     */
    private $shm_id;
    private $shm_sem;
    private $shm_key;

    /**
     * Max values
     * @var int
     */
    private $max_seq;
    private $max_machine_id;
    private $seq_length;
    private $machine_id;

    /**
     * Snowflake config
     * @var Config|null
     */
    private $config;

    /**
     * @param int $machine_id
     * @param Config $config
     * @throws \Exception
     */
    public function __construct($machine_id = 0, $config = null)
    {
        if (is_null($config)) {
            $this->config = new Config();
            $this->config->machine_id_length = 12;
            $this->config->start_timestamp = 1685603045000;
        } else {
            $this->config = $config;
        }
        if ($this->config->machine_id_length > 21 || $this->config->machine_id_length < 0) {
            throw new \Exception("Invalid machine id length, should be 0-21");
        }

        $this->shm_key = ftok(__FILE__, 'a');
        $this->shm_id = shmop_open($this->shm_key, 'c', 0666, 16);
        if ($this->shm_id === false) {
            throw new \Exception("failed to shmop_open");
        }

        $this->shm_sem = sem_get($this->shm_key);
        if ($this->shm_sem === false) {
            throw new \Exception("failed to sem_get");
        }

        $this->max_seq = pow(2, (22 - $this->config->machine_id_length)) - 1;
        $this->max_machine_id = pow(2, $this->config->machine_id_length) - 1;
        $this->seq_length = 22 - $this->config->machine_id_length;

        if ($machine_id > $this->max_machine_id) {
            throw new \Exception("The machine_id {$machine_id} exceeds the maximum value representable by {$this->config->machine_id_length} bits.");
        }

        $this->machine_id = $machine_id;
    }


    /**
     * @return int
     */
    private function getNowMilliSecond()
    {
        return (int)(microtime(true) * 1000);
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function lock()
    {
        if (sem_acquire($this->shm_sem) === false) {
            sem_release($this->shm_sem);
            throw new \Exception("failed to get lock");
        }
    }

    /**
     * @return void
     */
    private function unlock()
    {
        sem_release($this->shm_sem);
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function read()
    {
        $read = shmop_read($this->shm_id, 0, self::SHM_LENGTH);
        if ($read === false) {
            sem_release($this->shm_sem);
            throw new \Exception("failed to read shared memory");
        }
        $data = unpack('qt/qs', $read);
        return [$data['t'], $data['s']];
    }

    /**
     * @param int $timestamp
     * @param int $seq
     * @return void
     * @throws \Exception
     */
    private function write($timestamp, $seq)
    {
        $data = pack('q2', $timestamp, $seq);
        if (shmop_write($this->shm_id, $data, 0) === false) {
            sem_release($this->shm_sem);
            throw new \Exception("failed to write shared memory");
        }
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function id()
    {
        $this->lock();

        $now = $this->getNowMilliSecond();
        list($last_timestamp, $seq) = $this->read();
        if ($now === $last_timestamp) {
            if ($seq > $this->max_seq) {
                usleep(1000);
                $now = $this->getNowMilliSecond();
            }
        }
        if ($last_timestamp !== $now) {
            $last_timestamp = $now;
            $seq = 0;
        }

        // Generate Snowflake ID
        $id = ($last_timestamp - $this->config->start_timestamp) << 22
            | (($this->machine_id % ($this->max_machine_id + 1)) << $this->seq_length)
            | $seq % ($this->max_seq + 1);
        $seq++;

        $this->write($last_timestamp, $seq);

        $this->unlock();
        return $id;
    }

}
