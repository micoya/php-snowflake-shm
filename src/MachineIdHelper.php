<?php

namespace Micoya\PhpSnowflakeShm;


class MachineIdHelper
{

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var int
     */
    private $total_length;

    /**
     * @var int
     */
    private $used_length;

    /**
     * Scope 0-21
     * @param $total_length
     * @throws \Exception
     */
    public function __construct($total_length)
    {
        if ($total_length < 0 || $total_length > 21) {
            throw new \Exception("Invalid machine id length, should be 0-21");
        }
        $this->total_length = (int)$total_length;
    }

    /**
     * @param int $length
     * @param int $value
     * @return void
     * @throws \Exception
     */
    public function pushToHigh($length, $value)
    {
        $remain = $this->total_length - $this->used_length;
        if ($length > ($remain)) {
            throw new \Exception("Have not enough bits, {$length} required, {$remain} remain");
        }
        $max = pow(2, $length) - 1;
        if ($value > $max) {
            throw new \Exception("{$length}bit value cannot large than {$max}, but {$value} input");
        }
        $this->used_length += $length;
        $this->data[] = [$length, $value];
    }

    /**
     * @return int
     */
    public function make()
    {
        $r = 0;
        $p = $this->total_length;
        foreach ($this->data as $item) {
            $length = $item[0];
            $value = $item[1];
            $r = $r | ($value << (($p - $length)));
            $p -= $length;
        }
        return $r;
    }

}
