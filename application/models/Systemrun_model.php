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
        $coin_single1 = 0.0003 * 1.1;
        $coin_single2 = 0.0003 * 1.2;
        $coin_single3 = 0.0003 * 1.3;
        $coin_single4 = 0.0003 * 2;

        $ore_type_id = 1;

        $time = time();

        $sql = "INSERT INTO wakuang(`uid`,`ore_type_id`,`ore_num`,`find_time`) VALUES ";
        foreach ($all_miner as $k => $item) {

            $uid = $item['uid'];

            if (isset($dict[$uid]) && $dict[$uid] >= 15)
                continue;

            $nums0 = ($item['fuli'] + $item['dy_fuli']) * $coin_single0;
            $nums1 = $item['da_fuli'] * $coin_single1;
            $nums2 = $item['luhua_fuli'] * $coin_single2;
            $nums3 = $item['gujv_fuli'] * $coin_single3;
            $nums4 = $item['zhandou_fuli'] * $coin_single4;

            $nums = $nums0 + $nums1 + $nums2 + $nums3 + $nums4;

            $sql .= '(' . "'" . $item['uid'] . "'" . ',' . $ore_type_id . ',' . $nums . ',' . $time . '),';
        }
        $sql = rtrim($sql, ',');
        $this->db->query($sql);
    }


}