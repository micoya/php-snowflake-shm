<?php

namespace Micoya\PhpSnowflakeShm;

abstract class SnowflakeFactory
{

    /**
     * Make shared memory resolver
     * @param int $machine_id
     * @param Config $config
     * @return Snowflake
     * @throws \Exception
     */
    public static function makeShm($machine_id = 0, $config = null)
    {
        return new SnowflakeShm($machine_id, $config);
    }

}
