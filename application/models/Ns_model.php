<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/3/4
 * Time: 3:01 PM
 */

class Ns_model extends MY_Model
{
//    public function hen_hatch($uid, $hen, $feed, $type)
//    {
//        if ($type == 1)
//        {
//            $hen = 1;
//            $feed = 1;
//        }
//        else if ($type == 2)
//        {
//            $hen = 1;
//            $feed = 5;
//        }
//        else if ($type == 3)
//        {
//            $hen = 1;
//            $feed = 10;
//        }
//        else if ($type == 4)
//        {
//            $hen = 1;
//            $feed = 20;
//        }
//
//        $data = $this->db_r()->query("select * from ugame where uid = '$uid'")->row_array();
//        if (!isset($data))
//            return array(
//                'code' => 0,
//                'msg' => '数据异常'
//            );
//
//        if ($data['feed'] < $feed)
//            return array(
//                'code' => 0,
//                'msg' => '饲料不足'
//            );
//        if ($data['fuli'] < $hen)
//            return array(
//                'code' => 0,
//                'msg' => '母鸡不足'
//            );
//
//        // 0失败
//
//        $number = rand(1, 100);
//
//        if ($type == 1)
//        {
//            if ($number <= 70)
//            {
//                $this->db_w()->query("update ugame set feed = feed - $feed, fuli = fuli - $hen, hatch_times = hatch_times + 1, da_fuli = da_fuli + 1 where uid = '$uid'");
//            }
//            else
//            {
//                $type = 0;
//            }
//        }
//        else if ($type == 2)
//        {
//            if ($number <= 70)
//            {
//                $this->db_w()->query("update ugame set feed = feed - $feed, fuli = fuli - $hen, hatch_times = hatch_times + 1, luhua_fuli = luhua_fuli + 1 where uid = '$uid'");
//            }
//            else
//            {
//                $type = 0;
//            }
//        }
//        else if ($type == 3)
//        {
//            if ($number <= 70)
//            {
//                $this->db_w()->query("update ugame set feed = feed - $feed, fuli = fuli - $hen, hatch_times = hatch_times + 1, gujv_fuli = gujv_fuli + 1 where uid = '$uid'");
//            }
//            else
//            {
//                $type = 0;
//            }
//        }
//        else if ($type == 4)
//        {
//            if ($number <= 70)
//            {
//                $this->db_w()->query("update ugame set feed = feed - $feed, fuli = fuli - $hen, hatch_times = hatch_times + 1, zhandou_fuli = zhandou_fuli + 1 where uid = '$uid'");
//            }
//            else
//            {
//                $type = 0;
//            }
//        }
//
//        if ($type == 0)
//        {
//            $this->db_w()->query("update ugame set feed = feed - $feed, fuli = fuli - $hen, hatch_times = hatch_times + 1 where uid = '$uid'");
//        }
//
//        $data = $this->db_w()->query("select * from ugame where uid = '$uid'")->row_array();
//
//        return array(
//            'code' => 1,
//            'msg' => '',
//            'type' => $type,
//            'feed' => $data['feed'],
//            'fuli' => $data['fuli'],
//            'dy_fuli' => $data['dy_fuli'],
//            'da_fuli' => $data['da_fuli'],
//            'luhua_fuli' => $data['luhua_fuli'],
//            'gujv_fuli' => $data['gujv_fuli'],
//            'zhandou_fuli' => $data['zhandou_fuli']
//        );
//    }
}