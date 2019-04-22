<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2018/6/15
 * Time: 下午5:12
 */

class Activity_model extends MY_Model{
    // 活动
    public function get_a_app_download_info()
    {
        $data = $this->db_r()->query("select * from a_app_download_info where `open` = 1 and (is_test = 0 or (is_test = 1 and times < 300))")->result_array();
        return $data;
    }

    public function get_a_finish_download($uid)
    {
        $data = $this->db_r()->query("select * from a_finish_download where uid = '$uid' and is_install = 1")->result_array();
        $data2 = $this->db_r()->query("select * from a_finish_awaken where uid = '$uid'")->result_array();
        return array_merge($data, $data2);
    }

    public function task_q_cny($uid)
    {
        $date = date('Y-m-d', time());
        $data = $this->db_r()->query("select * from a_finish_q_cny where uid = '$uid' and date = '$date'")->result_array();
        if (!empty($data))
            return 0;

        $data = array(
            'uid' => $uid,
            'date' => $date
        );
        $result = $this->db->insert('a_finish_q_cny', $data);

        if ($result)
        {
            $data2 = array(
                'uid' => $uid,
                'title' => '看广告，抢现金',
                'text' => '您成功抢到现金0.20元，请查收。',
                'cny' => 0.20,
                'send_date' => date('Y-m-d H:i:s', time())
            );
            $result = $this->db->insert('compensate', $data2);
        }
        return boolval($result);
    }

    public function callback_app_is_install($uid, $app_name, $os)
    {
        $result = 1;
        $is_finish = $this->db_r()->query("select * from a_finish_download where uid = '$uid' and app_name = '$app_name'")->result_array();

        if (!empty($is_finish))
        {
            $is_finish = $is_finish[0]['is_install'];
            if ($is_finish == 1) $result = 0;
        }
        else
        {
            $data = array(
                'uid' => $uid,
                'app_name' => $app_name,
                'is_install' => 1,
                'state' => 1,
                'os' => $os,
                'date' => date('Y-m-d H:i:s', time())
            );
            $this->db->insert('a_finish_download', $data);
            $this->db->query("update a_app_download_info set times = times + 1 where app_name = '$app_name' and type != '唤醒'");
        }

        if ($result)
        {
            $app_info = $this->db_r()->query("select * from a_app_download_info where app_name = '$app_name' and type != '唤醒'")->result_array();
            if (empty($app_info))
            {
                $result = 0;
            }
            else
            {
                $app_info = $app_info[0];

                $title_type = '';
                if ($app_info['type'] == '下载')
                {
                    $title_type = '下载试用';
                }
                else if ($app_info['type'] == '唤醒')
                {
                    $title_type = '唤醒';
                }
                else if ($app_info['type'] == '网页')
                {
                    $title_type = '试用';
                }


                $cny = 0;
                $xpot = 0;
                $fuli = 0;
                $dy_fuli = 0;
                if ($app_info['coin_type'] == '元')
                {
                    $cny = $app_info['value'];
                }
                else if ($app_info['coin_type'] == '鸡蛋')
                {
                    $xpot = $app_info['value'];
                }
                else if ($app_info['coin_type'] == '母鸡')
                {
                    $fuli = $app_info['value'];
                }
                else if ($app_info['coin_type'] == '福利鸡')
                {
                    $dy_fuli = $app_info['value'];
                }

                if ($cny > 0 || $xpot > 0 || $fuli > 0 || $dy_fuli > 0)
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
                    if ($dy_fuli > 0)
                    {
                        $data2['dy_fuli'] = $dy_fuli;
                        $data2['dy_fuli_tov'] = 30;
                    }
                    $this->db->insert('compensate', $data2);
                }
            }
        }

        return $result;
    }

    public function callback_app_is_awaken($uid, $app_name, $os)
    {
        $result = 1;
        $is_finish = $this->db_r()->query("select * from a_finish_awaken where uid = '$uid' and app_name = '$app_name'")->result_array();

        if (!empty($is_finish))
        {
            $result = 0;
        }
        else
        {
            $data = array(
                'uid' => $uid,
                'app_name' => $app_name,
                'os' => $os
            );
            $this->db->insert('a_finish_awaken', $data);

            $data = $this->db_r()->query("select * from a_app_download_info where type = '下载' and app_name = '$app_name'")->result_array();
            if (!empty($data))
            {
                $this->db->query("update a_finish_download set awaken_count = awaken_count + 1 where uid = '$uid' and app_name = '$app_name'");
            }
        }

        if ($result)
        {
            $app_info = $this->db_r()->query("select * from a_app_download_info where app_name = '$app_name' and type = '唤醒'")->result_array();
            if (empty($app_info))
            {
                $result = 0;
            }
            else
            {
                $app_info = $app_info[0];

                $title_type = '';
                if ($app_info['type'] == '下载')
                {
                    $title_type = '下载试用';
                }
                else if ($app_info['type'] == '唤醒')
                {
                    $title_type = '唤醒';
                }
                else if ($app_info['type'] == '网页')
                {
                    $title_type = '试用';
                }


                $cny = 0;
                $xpot = 0;
                $fuli = 0;
                if ($app_info['coin_type'] == '元')
                {
                    $cny = $app_info['value'];
                }
                else if ($app_info['coin_type'] == '鸡蛋')
                {
                    $xpot = $app_info['value'];
                }
                else if ($app_info['coin_type'] == '母鸡')
                {
                    $fuli = $app_info['value'];
                }
                else if ($app_info['coin_type'] == '福利鸡')
                {
                    $dy_fuli = $app_info['value'];
                }

                if ($cny > 0 || $xpot > 0 || $fuli > 0 || $dy_fuli > 0)
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
                    if ($dy_fuli > 0)
                    {
                        $data2['dy_fuli'] = $dy_fuli;
                        $data2['dy_fuli_tov'] = 30;
                    }
                    $this->db->insert('compensate', $data2);
                }
            }
        }

        return $result;
    }

    // 生成下载订单，用户对接环境
    public function make_download_app_third_party($uid, $app_name, $sign)
    {
        $user_sign = $this->db_r()->query("select * from wx_info where openid = '$uid'")->result_array();
        if (empty($user_sign))
            return 0;
        $user_sign = $user_sign[0]['user_sign'];
        if ($user_sign != $sign)
            return 0;

        $is_finish = $this->db_r()->query("select * from a_finish_download where app_name = '$app_name' and uid = '$uid'")->result_array();
        if (!empty($is_finish))
        {
            $is_finish = $is_finish[0]['is_install'];
            if ($is_finish)
            {
                return 0;
            }
            else
            {
                return 1;
            }
        }

        $data = array(
            'app_name' => $app_name,
            'uid' => $uid,
            'date' => date('Y-m-d H:i:s', time()),
            'os' => 'iOS'
        );
        $this->db->insert('a_finish_download', $data);
        return 1;
    }

    // 生成下载订单，用户对接环境
    public function callback_app_is_install_third_party($uid, $app_name)
    {
        $is_finish = $this->db_r()->query("select * from a_finish_download where app_name = '$app_name' and uid = '$uid'")->result_array();
        if (!empty($is_finish))
        {
            $is_finish = $is_finish[0]['is_install'];
            if ($is_finish)
            {
                return 0;
            }
        }

        $app_info = $this->db_r()->query("select * from a_app_download_info where app_name = '$app_name' and type = '下载'")->result_array();
        if (!empty($app_info))
        {
            $date = date('Y-m-d H:i:s', time());
            $this->db->query("update a_finish_download set is_install = 1, state = 1, `date` = '$date' where uid = '$uid' and app_name = '$app_name'");

            $app_info = $app_info[0];

            $title_type = '';
            if ($app_info['type'] == '下载')
            {
                $title_type = '下载试用';
            }
            else if ($app_info['type'] == '唤醒')
            {
                $title_type = '唤醒';
            }
            else if ($app_info['type'] == '浏览器')
            {
                $title_type = '试用';
            }


            $cny = 0;
            $xpot = 0;
            $fuli = 0;
            if ($app_info['coin_type'] == '元')
            {
                $cny = $app_info['value'];
            }
            else if ($app_info['coin_type'] == '鸡蛋')
            {
                $xpot = $app_info['value'];
            }
            else if ($app_info['coin_type'] == '母鸡')
            {
                $fuli = $app_info['value'];
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
        }
    }
}