<?php
/**
 * Created by PhpStorm.
 * User: wps
 * Date: 2018/5/2
 * Time: 16:45
 */
class Ugame_model extends MY_Model{

    function __construct()
    {
        parent::__construct();
        $this->load->model('wxinfo_model');
        $this->load->model('compensate_model');
    }

    //查询某个用户的ugame数据
    public function get_ugame_info($uid)
    {
        $result = $this->db_r()->query("select * from ugame where uid='$uid'")->result_array();
        return $result;
    }

    //检测是否注册
    public function check_register_ugame($uid)
    {
        $result = $this->db_r()->query("select uid from ugame where uid = '$uid'")->result_array();
        return $result;
    }

    //生成邀请码
    function getcode($id, $uid)
    {
        return strtoupper(md5($uid.$id));
    }
    //注册
    public function register_ugame($id, $uid, $os, $app_bundle_id, $app_channel)
    {
        $ip = $this->getIp();
        $fuli = 6;
        if (substr($uid, 0, 6) == 'phone_')
        {
            $fuli = 8;
        }
        $data = array(
            'uid' => $uid,
            'channel_id' => $app_channel,
            'app_bundle_id' => $app_bundle_id,
            'os' => $os,
            'fuli' => $fuli,
            'register_ip' => $ip,
            'register_time' => time());
        $result = $this->db_w()->insert('ugame', $data);

        if ($result)
        {
            $info = $this->db_r()->query("select * from rongyun_info where uid = '$uid'")->result_array();
            if (empty($info))
            {
                $this->get_rong_yun_token($uid);
            }
        }

        return $result;
    }

    public function update_ugame($uid, $os, $app_bundle_id)
    {
        $this->db_w()->query("update ugame set app_bundle_id = '$app_bundle_id', os = '$os' where uid = '$uid'");
    }

    //每日登陆
    public function everyday_login_wx($uid)
    {
        $now = time();
        $this->db_w()->query("update ugame set logintime = $now where uid = '$uid'");
    }

    //每日签到
    public function signin($uid)
    {
        $everyday_data = $this->db_r()->query("select * from everyday_user_record WHERE uid = '$uid'")->result_array();
        if (empty($everyday_data))
        {
            $this->db_w()->insert('everyday_user_record', array("uid" => $uid, "sign_in" => 0));
        }
        $ok = false;
        if ($everyday_data[0]['sign_in'] == 0)
        {
            $ok = true;
            $this->db_w()->query("update everyday_user_record set sign_in = 1 WHERE uid = '$uid'");
            $this->compensate_model->inset_compensate_xpot($uid, "每日签到", "由于您完成每日签到任务，特在此奉上0.1斤鸡蛋，请查收~", 0.1);
        }

        return $ok;
    }

    //每日冒泡
    public function everyday_bubbling($uid)
    {
        $everyday_data = $this->db_r()->query("select * from everyday_user_record WHERE uid = '$uid'")->result_array();

        $now = time();
        $this->db_w()->query("update ugame set logintime = $now, is_active = 1 where uid = '$uid'");

        $ok = false;
        if (empty($everyday_data))
        {
            $ok = true;
            $this->db_w()->insert('everyday_user_record', array("uid" => $uid, "sign_in" => 0));
        }

        if ($ok)
        {
            $now = time();
            $now_date = strval(date("Y-m-d", $now));

            $this->db_w()->insert('user_sign_in_record', array('uid' => $uid, 'sign_in_date' => $now_date));

            $c_h_info = $this->db_w()->query("select * from c_h_staff WHERE uid = '$uid'")->result_array();
            if (!empty($c_h_info))
            {
                $c_h_info = $c_h_info[0];
                $owner_uid = $c_h_info['owner_uid'];
                if ($c_h_info['is_active'] == 0)
                {
                    $now_date = strval(date("Y-m-d H:i:s", $now));
                    $this->db_w()->query("update c_h_staff set is_active = 1,login_date = '$now_date'  where uid = '$uid'");
                    $this->db_w()->query("insert ignore into c_h_staff_owner_dynamic(`owner_uid`) value('$owner_uid')");
                    $this->db_w()->query("update c_h_staff_owner_dynamic set active_staff_count = active_staff_count + 1 where owner_uid = '$owner_uid'");
                }
            }
            return true;
        }

        return false;
    }

    public function get_rong_yun_token($uid)
    {
        $user_info = $this->db_r()->query("select nickname, headimgurl from wx_info where openid = '$uid'")->row_array();
        $data = array(
            'userId' => $uid,
            'name' => $user_info['nickname'],
            'portraitUri' => $user_info['headimgurl']
        );

        $info = $this->db_r()->query("select * from rongyun_info where uid = '$uid'")->result_array();
        if (empty($info))
        {
            $post_data = '';
            $index = 0;
            foreach ($data as $k=>$v)
            {
                $post_data = $post_data.$k.'='.$v.'&';
                $index += 1;
            }

            // 重置随机数种子。
            srand((double)microtime()*1000000);

            $appKey = '25wehl3u2sblw';
            $appSecret = 'v3V8kGWFlk8W';
            $nonce = rand(); // 获取随机数。
            $timeStamp = time()*1000; // 获取时间戳（毫秒）。

            $signature = sha1($appSecret.$nonce.$timeStamp);

            $httpHeader = array(
                'POST /user/getToken.json HTTP/1.1',
                'Host: api.cn.ronghub.com',
                'App-Key:'.$appKey, //	平台分配
                'Nonce:'.$nonce, //	随机数
                'Timestamp:'.$timeStamp, //	时间戳
                'Signature:'.$signature, //	签名
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length:'.strlen($post_data)
            );

            $url = "http://api.cn.ronghub.com/user/getToken.json";
            // 初始化curl
            $ch = curl_init();
            // 设置你需要抓取的URL
            curl_setopt($ch, CURLOPT_URL, $url);
            // post提交方式
            curl_setopt($ch, CURLOPT_POST, 1);

            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            // 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // 设置header
            curl_setopt($ch, CURLOPT_HEADER, false);
            // 增加 HTTP Header（头）里的字段
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);

            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            // 终止从服务端进行验证
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
            // 运行curl
            $output = curl_exec($ch);
            // 关闭URL请求
            curl_close($ch);

            $output = json_decode($output, true);

            if ($output['code'] == 200 && $uid == $output['userId'])
            {
                $token = $output['token'];
                $insert_data = array(
                    'uid' => $uid,
                    'token' => $token
                );
                $this->db_w()->insert('rongyun_info', $insert_data);

                return $token;
            }
            return '';
        }
        return '';
    }

    public function get_user_token($uid)
    {
        $info = $this->db_r()->query("select token from rongyun_info where uid = '$uid'")->result_array();
        if (empty($info))
        {
            return $this->get_rong_yun_token($uid);
        }
        $token = $info[0]['token'];
        return $token;
    }

    //提交邀请码
    function sub_share_code($uid, $code)
    {
        $data = $this->db_r()->query("select * from c_h_staff where uid = '$uid'")->row_array();
        if (isset($data))
            return 0;

        //通过邀请码获取雇主uid 以及aid
        $other = $this->db_r()->query("select c.* from wx_info w left join c_h_staff c on c.uid = w.openid where w.id = '$code'")->row_array();
        if (!isset($other))
            return 0;
        if (empty($other['uid']))
        {
            $other = $this->db_r()->query("select openid as uid from wx_info where id = '$code'")->row_array();
        }

        $p_uid = $other['uid'];
        $p_owner_uid = isset($other['owner_uid']) ? $other['owner_uid'] : '';
        $p_owner2_uid = isset($other['owner2_uid']) ? $other['owner2_uid'] : '';
        if ($p_uid == $uid)
            return 0;

        $other_info = $this->db_w()->query("select * from ugame where uid = '$p_uid'")->row_array();
        if ($other_info['fuli_task_level'] == 0)
        {
            if ($other_info['share_cnt'] >= 20)
            {
                $other_info['fuli_task_level'] = 1023;
            }
            else if ($other_info['share_cnt'] >= 10)
            {
                $other_info['fuli_task_level'] = 1022;
            }
            else if ($other_info['share_cnt'] >= 5)
            {
                $other_info['fuli_task_level'] = 1021;
            }
            else if ($other_info['share_cnt'] >= 1)
            {
                $other_info['fuli_task_level'] = 1020;
            }
            $this->db_w()->query("update ugame set fuli_task_level = ".$other_info['fuli_task_level']." where uid = '$p_uid'");

            $fix_share_cnt = $this->db_w()->query("select count(*) as share_cnt from c_h_staff where owner_uid = '$p_uid'")->row_array();
            $fix_share_cnt = $fix_share_cnt['share_cnt'];

            if ($other_info['share_cnt'] != $fix_share_cnt)
            {
                $this->db_w()->query("update ugame set share_cnt = $fix_share_cnt where uid = '$p_uid'");
            }
            $other_info['share_cnt'] = $fix_share_cnt;
        }

        $share_cnt = $other_info['share_cnt'] + 1;

        $share_cnt_title = 0;
        $add_fuli = 0;
        $task = $this->db_r()->query("select * from task where type_id = 2")->result_array();
        foreach ($task as $k=>$value)
        {
            if($share_cnt >= $value['need_cnt'] and $other_info['fuli_task_level'] < $value['id'])
            {
                $add_fuli = $value['fuli_cnt'];
                $share_cnt_title = $value['need_cnt'];
                $this->db_w()->query("update ugame set fuli_task_level = ".$value['id']." where uid = '$p_uid'");
                break;
            }
        }

        $this->db_w()->query("update ugame set share_cnt = share_cnt + 1 where uid = '$p_uid'");

        $res_insert = $this->db_w()->insert("c_h_staff", array(
            'uid' => $uid,
            'owner_uid' => $p_uid,
            'owner2_uid' => $p_owner_uid,
            'owner3_uid' => $p_owner2_uid,
            'login_date' => date("Y-m-d H:i:s", time())
            ));

        if (!$res_insert)
            return 0;

        $this->db_w()->query("insert ignore into c_h_staff_owner_dynamic(`owner_uid`,`owner2_uid`,`owner3_uid`) value('$p_uid','$p_owner_uid','$p_owner2_uid')");
        $this->db_w()->query("update c_h_staff_owner_dynamic set active_staff_count = active_staff_count + 1 where owner_uid = '$p_uid'");

        $row = $this->db_r()->query("select * from small_answer where uid = '$p_uid'")->row_array();
        if (isset($row))
        {
            $this->db_w()->query("update small_answer set surplus_times = surplus_times + 1 where uid = '$p_uid'");
        }

        if ($add_fuli > 0)
        {
            $this->compensate_model->inset_compensate_fuli($p_uid, "系统奖励", "由于您完成邀请".$share_cnt_title."名好友任务，特在此奉上".$add_fuli."只母鸡，请查收~", $add_fuli);
        }

        return 1;
    }

    //更新算力
    public function add_fuli($uid, $num)
    {
        return $this->db_w()->query("update ugame set fuli = fuli + $num where uid = '$uid'");
    }
    //获取算力
    public function get_ugame_fuli($uid){
        $data = $this->db_w()->query("select fuli, dy_fuli from ugame where uid='$uid'")->row_array();
        $result['suanli'] = $data['fuli'];
        $result['dy_suanli'] = $data['dy_fuli'];
        return $result;
    }
    //获取算力
    public function get_ugame_fuli_id($id){
        $data = $this->db_w()->query("select w.openid, u.fuli, u.dy_fuli from wx_info w left join ugame u on u.uid = w.openid where id = $id")->row_array();
        $result['suanli'] = $data['fuli'];
        $result['dy_suanli'] = $data['dy_fuli'];
        $result['uid'] = $data['openid'];
        return $result;
    }
    //通过任意参数查询符合条件的数据列表
    public function get_config($key_name)
    {
        $result = $this->db_r()->query("select key_value from config where key_name = '$key_name'")->result_array();
        return $result[0]['key_value'];
    }
    //一键领取所有矿工收益uid
    public function get_all_miner_tax($uid)
    {
        $all_tax = $this->db_r()->query("select sum(tax) as all_tax from c_h_staff where owner_uid = '$uid'")->row_array();
        $all_tax = $all_tax['all_tax'];
        if ($all_tax > 0)
        {
            $this->add_coins($uid, "xpot", $all_tax);
            $all_miner = $this->db_r()->query("select uid from c_h_staff where owner_uid = '$uid'")->result_array();
            $arr = '(';
            foreach ($all_miner as $k=>$v)
            {
                $uid = $v['uid'];
                $arr .= "'".$uid."',";
            }
            $arr = rtrim($arr, ',');
            $arr .= ")";
            $this->db_w()->query("update c_h_staff set tax = 0, tax_total = tax_total + tax where uid in ".$arr);
        }
        return $all_tax;
    }
    //累加某种货币数值
    public function add_coins($uid, $coin_name, $num)
    {
        $this->db_w()->query("update ugame set $coin_name = $coin_name + $num where uid = '$uid'");
        return $this->db_w()->affected_rows();
    }
    //获取某种货币数值
    public function get_coins_num($uid, $coin_name)
    {
        $result = $this->db_w()->query("select $coin_name from ugame where uid = '$uid'")->result_array();
        if (empty($result))
            return 0;
        return $result[0][$coin_name];
    }
    //获取某种货币冻结的数值
    public function get_frozen_coins_num($uid, $coin_name)
    {
        $coin_name = "frozen_".$coin_name;
        $result=$this->db_w()->query("select $coin_name from ugame where uid = '$uid'")->result_array();
        return $result[0][$coin_name];
    }

    public function exchange_chicken($uid, $eggs)
    {
        $data = $this->db_r()->query("select xpot from ugame where uid = '$uid'")->row_array();
        $xpot = $data['xpot'];
        if ($xpot < $eggs)
        {
            return 0;
        }

        $chicken = 0;
        if ($eggs < 100) $chicken = $eggs * 0.1;
        else if ($eggs < 200) $chicken = $eggs * 0.11;
        else if ($eggs < 300) $chicken = $eggs * 0.12;
        else if ($eggs < 500) $chicken = $eggs * 0.14;
        else if ($eggs < 1000) $chicken = $eggs * 0.17;
        else if ($eggs >= 1000) $chicken = $eggs * 0.2;

        $this->db_w()->query("update ugame set xpot = xpot - $eggs, fuli = fuli + $chicken where uid = '$uid'");

        return 1;
    }

    public function everyday_share($uid)
    {
        $result = $this->db_r()->query("select share from everyday_user_record where uid = '$uid'")->result_array();
        if (empty($result))
        {
            return 0;
        }
        $result = $result[0];
        if ($result['share'])
        {
            return 0;
        }
        $this->db_w()->query("update everyday_user_record set share = 1 where uid = '$uid'");

        $eggs= 0.3;

        $this->compensate_model->inset_compensate_xpot($uid, "每日分享", "由于您完成每日分享任务。在此，特奉上".$eggs."斤鸡蛋，请查收！", 0.3);
        return 1;
    }

    public function appstore_buy_hen($uid, $hen)
    {
        $this->db_w()->query("update ugame set fuli = fuli + $hen where uid = 'ovBB91WVgWHGN_QrrFVt9g0rBN18'");
        return 1;
    }

    public function appstore_hen_ranging_list()
    {
        return $this->db_r()->query("select u.uid, u.fuli, w.nickname, w.sex, w.headimgurl from ugame u left join wx_info w on w.openid = u.uid ORDER BY fuli DESC limit 0, 30")->result_array();
    }

    public function verifying_friends($uid, $other_uid)
    {
        $data1 = $this->db_r()->query("select * from ugame where uid = '$uid'")->row_array();
        $data2 = $this->db_r()->query("select * from ugame where uid = '$other_uid'")->row_array();

        if ($data2['xpot'] + $data2['total_sell_xpot'] < 10)
        {
            // 新手保护
            return 41;
        }
        return 0;

    }

    public function get_make_level_info($all_xpot, $fuli)
    {
        $coin_single = 0;
        $tax_pro = 0.05;
        $base_coin_single = 0.00010;
        $decay = 0.7;
        if ($all_xpot < 20)
        {
            $coin_single = 0.003;
            $tax_pro = 0.05;
        }
        else if ($all_xpot >= 20 && $all_xpot < 40)
        {
            $coin_single = 0.0003;
            $tax_pro = 0.03;
        }
        else if ($all_xpot >= 40 && $all_xpot < 50)
        {
            $coin_single = $base_coin_single;
            $tax_pro = 0.02;
        }
        else if ($all_xpot >= 50 && $all_xpot < 150)
        {
            if ($fuli >= 100)
            {
                $coin_single = $base_coin_single;
                $tax_pro = 0.02;
            }
            else
            {
                $coin_single = 0.00010 * $decay;
                $tax_pro = 0.02 * $decay;
            }
        }
        else if ($all_xpot >= 150 && $all_xpot < 250)
        {
            if ($fuli >= 150)
            {
                $coin_single = $base_coin_single * $decay;
                $tax_pro = 0.02 * pow($decay,2);
            }
            else
            {
                $coin_single = $base_coin_single * pow($decay,2);
                $tax_pro = 0.02 * pow($decay,2);
            }
        }
        else
        {
            $all_xpot_level = intval(($all_xpot - 150) / 50);

            $is_hen_ask = boolval($fuli >= 50 + $all_xpot_level * 50);

            if (!$is_hen_ask)
            {
                $coin_single_power = $all_xpot_level;
            }
            else
            {
                $coin_single_power = $all_xpot_level - 1;
            }
            $tax_power = $all_xpot_level;

            $coin_single = $base_coin_single * pow($decay, $coin_single_power);
            $tax_pro = 0.02 * pow($decay, $tax_power);
        }

        $result['coin_single'] = $coin_single;
        $result['tax_pro'] = $tax_pro;
        return $result;
    }

    public function get_user_staff($uid)
    {
        return $this->db_r()->query("select w.nickname, w.headimgurl, s.uid, s.tax, s.tax_total, s.login_date, s.is_active from c_h_staff s left join wx_info w on w.openid = s.uid where owner_uid = '$uid'")->result_array();
    }

    public function get_user_active_staff_count($uid)
    {
        $result = $this->db_r()->query("select * from c_h_staff_owner_dynamic where owner_uid = '$uid'")->result_array();
        if (empty($result))
        {
            return 0;
        }
        $result = $result[0]['active_staff_count'];
        return $result;
    }

    public function get_ugame_data($uid)
    {
        return $this->db_r()->query("select fuli, da_fuli, luhua_fuli, gujv_fuli, zhandou_fuli, dy_fuli, feed, cny, xpot, frozen_xpot from ugame where uid = '$uid'")->row_array();
    }

    function getIp()
    {
        if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown"))
            $ip = getenv("HTTP_CLIENT_IP");
        else if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown"))
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        else if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown"))
            $ip = getenv("REMOTE_ADDR");
        else if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown"))
            $ip = $_SERVER['REMOTE_ADDR'];
        else
            $ip = "unknown";
        return ($ip);
    }
}