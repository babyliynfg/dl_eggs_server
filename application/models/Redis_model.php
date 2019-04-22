<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/4/8
 * Time: 6:05 PM
 */

class Redis_model extends MY_Model
{
    public function get_redis()
    {
        $redis = new \Redis();
        $redis->connect('r-2zelh1bso8osjkebfa.redis.rds.aliyuncs.com', '6379');
        $redis->auth('2013XinNian#*');
        //        echo "Server is running: " . $redis->ping();
        return $redis;
    }
}