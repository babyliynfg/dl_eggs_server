<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/3/28
 * Time: 2:20 PM
 */
class Systemrun_model extends MY_Model{

    public function make_eggs($all_miner, $dict)
    {
        $coin_single0 = 0.0003;

        $ore_type_id = 1;

        $time = time();

        if (count($all_miner) > 0)
        {
            $sql = "INSERT INTO wakuang(`uid`,`ore_type_id`,`ore_num`,`find_time`) VALUES ";
            foreach ($all_miner as $k => $item) {

                $uid = $item['uid'];

//            if (isset($dict[$uid]) && $dict[$uid] >= 15)
//                continue;

                $nums0 = ($item['fuli'] + $item['dy_fuli']) * $coin_single0;

                $nums = $nums0;

                $sql .= '(' . "'" . $uid . "'" . ',' . $ore_type_id . ',' . $nums . ',' . $time . '),';
            }
            $sql = rtrim($sql, ',');

//        echo '<<< '.$sql.' >>>';

            $this->db->query($sql);
        }
    }
}

















