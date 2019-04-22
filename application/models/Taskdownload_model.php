<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/3/8
 * Time: 10:36 AM
 */

class Taskdownload_model extends MY_Model{
    // 活动
    public function get_a_app_download_info()
    {
        $data = $this->db_r()->query("select * from a_app_download_info_new where `open` = 1 and (is_test = 0 or (is_test = 1 and times < 300))")->result_array();
        return $data;
    }

    public function get_a_finish_download($uid)
    {
        $data = $this->db_r()->query("select * from a_finish_download where uid = '$uid' and is_install = 1")->result_array();
        return $data;
    }

    public function callback_app_is_install($uid, $app_name, $os)
    {
        $is_finish = $this->db_r()->query("select * from a_finish_download where uid = '$uid' and app_name = '$app_name'")->row_array();

        if (isset($is_finish) && $is_finish['is_install'])
            return 0;

        $data = array(
            'uid' => $uid,
            'app_name' => $app_name,
            'is_install' => 1,
            'state' => 1,
            'os' => $os,
            'date' => date('Y-m-d H:i:s', time())
        );
        $this->db->insert('a_finish_download', $data);
        $this->db->query("update a_app_download_info_new set times = times + 1 where app_name = '$app_name'");


        $app_info = $this->db_r()->query("select * from a_app_download_info_new where app_name = '$app_name'")->row_array();
        if (!isset($app_info))
            return 0;

        $title_type = '下载试用';
        $cny = 0;
        $xpot = 0;
        $fuli = 0;
        if ($app_info['download_coin_type'] == '元')
        {
            $cny = $app_info['download_coin_value'];
        }
        else if ($app_info['download_coin_type'] == '鸡蛋')
        {
            $xpot = $app_info['download_coin_value'];
        }
        else if ($app_info['download_coin_type'] == '母鸡')
        {
            $fuli = $app_info['download_coin_value'];
        }

        if ($cny > 0 || $xpot > 0 || $fuli > 0)
        {
            $data2 = array(
                'uid' => $uid,
                'title' => $title_type.$app_name.'奖励发放',
                'text' => '恭喜您获得'.$title_type.$app_name.'抢现金活动奖励，点击领取到首页即可查看。',
                'cny' => $cny,
                'xpot' => $xpot,
                'fuli' => $fuli,
                'send_date' => date('Y-m-d H:i:s', time())
            );
            $this->db->insert('compensate', $data2);
        }

        return 1;
    }

    public function callback_app_is_awaken($uid, $app_name, $os)
    {
        $is_finish = $this->db_r()->query("select * from a_finish_awaken where uid = '$uid' and app_name = '$app_name'")->row_array();

        if (isset($is_finish))
            return 0;


        $data = array(
            'uid' => $uid,
            'app_name' => $app_name,
            'os' => $os
        );
        $this->db->insert('a_finish_awaken', $data);

        $app_info = $this->db_r()->query("select * from a_app_download_info_new where app_name = '$app_name'")->row_array();
        if (!isset($app_info))
            return 0;

        $yesterday_date = date('Y-m-d', time() - 86400);
        $date = date('Y-m-d', time());

        if ($app_info['last_awaken_date'] == $yesterday_date)
        {
            $this->db->query("update a_finish_download set awaken_count = awaken_count + 1, last_awaken_date = '$date' where uid = '$uid' and app_name = '$app_name'");

        }
        else
        {
            $this->db->query("update a_finish_download set last_awaken_date = '$date' where uid = '$uid' and app_name = '$app_name'");
            return 0;
        }

        $download_info = $this->db->query("select * from a_finish_download where uid = '$uid' and app_name = '$app_name'")->row_array();

        $cny = 0;
        $xpot = 0;
        $fuli = 0;

        $awaken_count = $download_info['awaken_count'];

        if ($awaken_count == 1)
        {
            $title_type = '唤醒';
            if ($app_info['awaken_coin_type'] == '元')
            {
                $cny = $app_info['awaken_coin_value1'];
            }
            else if ($app_info['awaken_coin_type'] == '鸡蛋')
            {
                $xpot = $app_info['awaken_coin_value1'];
            }
            else if ($app_info['awaken_coin_type'] == '母鸡')
            {
                $fuli = $app_info['awaken_coin_value1'];
            }
        }
        else if ($awaken_count == 3)
        {
            $title_type = '3日唤醒';
            if ($app_info['awaken_coin_type'] == '元')
            {
                $cny = $app_info['awaken_coin_value3'];
            }
            else if ($app_info['awaken_coin_type'] == '鸡蛋')
            {
                $xpot = $app_info['awaken_coin_value3'];
            }
            else if ($app_info['awaken_coin_type'] == '母鸡')
            {
                $fuli = $app_info['awaken_coin_value3'];
            }
        }
        else if ($awaken_count == 7)
        {
            $title_type = '7日唤醒';
            if ($app_info['awaken_coin_type'] == '元')
            {
                $cny = $app_info['awaken_coin_value7'];
            }
            else if ($app_info['awaken_coin_type'] == '鸡蛋')
            {
                $xpot = $app_info['awaken_coin_value7'];
            }
            else if ($app_info['awaken_coin_type'] == '母鸡')
            {
                $fuli = $app_info['awaken_coin_value7'];
            }
        }

        if ($cny > 0 || $xpot > 0 || $fuli > 0)
        {
            $data2 = array(
                'uid' => $uid,
                'title' => $title_type.$app_name.'奖励发放',
                'text' => '恭喜您获得'.$title_type.$app_name.'抢现金活动奖励，点击领取到首页即可查看。',
                'cny' => $cny,
                'xpot' => $xpot,
                'fuli' => $fuli,
                'send_date' => date('Y-m-d H:i:s', time())
            );
            $this->db->insert('compensate', $data2);
        }

        return 1;
    }
}