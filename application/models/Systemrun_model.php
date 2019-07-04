<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/3/28
 * Time: 2:20 PM
 */
class Systemrun_model extends MY_Model{

    public function make_eggs($all_miner)
    {
        $coin_config = $this->db->select()
            ->where('name', 'xpot')
            ->get('coin_config')
            ->row_array();
        $total_output_day = $coin_config['total_output_day'];

        $total_capacity = $this->db->select('SUM(fuli) as total_capacity')
            ->where('is_active', '1')
            ->get('ugame')
            ->row_array();
        $total_capacity = $total_capacity['total_capacity'];

        $coin_single0 = $total_output_day / $total_capacity / 48.0;

        $ore_type_id = 1;

        $time = time();

        if (count($all_miner) > 0)
        {
            $sql = "INSERT INTO wakuang(`uid`,`ore_type_id`,`ore_num`,`find_time`) VALUES ";
            foreach ($all_miner as $k => $item) {

                $uid = $item['uid'];

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

















