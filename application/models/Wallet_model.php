<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2018/9/26
 * Time: 下午5:12
 */

class Wallet_model extends MY_Model{
    // 手机验证码
    function __construct()
    {
        parent::__construct();
        $this->load->model('ugame_model');
    }

    public function get_xpot_cost_record($start_date, $end_date)
    {
        $result = $this->db->query("select * from xpot_cost_record WHERE date >= '$start_date' and date < '$end_date'")->result_array();
        $date = date('Y-m-d', time());
        if ($date.' 00:00' <= $end_date)
        {
            $xpot_cost = $this->ugame_model->get_config('xpot_cost');
            array_push($result, array('id' => 0, 'title' => $date, 'date' => date('Y-m-d H:i:s', time()), 'xpot_cost' => $xpot_cost));
        }

        return $result;
    }

    public function get_today_withdraw_xpot($uid)
    {
        $data = $this->db->query("select today_withdraw_xpot from everyday_user_record WHERE uid = '$uid'")->result_array();
        if (empty($data))
        {
            $this->db_w()->insert('everyday_user_record', array("uid" => $uid));
        }
        $result = $data[0]['today_withdraw_xpot'];
        return $result;
    }

    public function make_withdraw_cny_flow_id($uid, $number, $sign)
    {
        return 0;
        die;

        $result['code'] = 0;
        $result['flow_id'] = '';

        if ($number < 100)
        {
            return $result;
            die;
        }
        $number = 100;

        $balance = $this->db->query("SELECT cny FROM ugame WHERE uid = '$uid'")->result_array();
        $balance = $balance[0]['cny'];
        if ($balance < $number)
        {
            $result['code'] = 2004;
            return $result;
        }
        $info = $this->db->query("SELECT fullname, id_card, phone_number, nickname, user_sign FROM wx_info WHERE openid = '$uid'")->result_array();
        $info = $info[0];
        $phone_number = $info['phone_number'];
        if (empty($phone_number))
        {
            $result['code'] = 15;
            return $result;
        }
        $fullname = $info['fullname'];
        $id_card = $info['id_card'];
        if (empty($fullname) || empty($id_card))
        {
            $result['code'] = 17;
            return $result;
        }
        $nickname = addslashes($info['nickname']);
        $date = strval(date("Y-m-d H:i:s", time()));
        $flow_id = 'eggs_'.strval(md5('CNY'.$phone_number.$this->msectime()));
        $user_sign = $info['user_sign'];
        if ($sign != $user_sign)
        {
            $result['code'] = 997;
            return $result;
        }

        $this->db->query("UPDATE ugame SET cny = cny - $number WHERE uid = '$uid'");

        $data = array(
            'flow_id' => $flow_id,
            'uid' => $uid,
            'phone_number' => $phone_number,
            'nickname' => $nickname,
            'cny' => $number,
            'date' => $date
        );
        $this->db->insert('withdraw_cny', $data);

        $result['code'] = 1;
        $result['flow_id'] = $flow_id;
        return $result;
    }

    public function make_withdraw_xpot_flow_id($uid, $number, $sign)
    {
        $result['code'] = 0;
        $result['flow_id'] = '';

        if ($number <= 0)
        {
            return $result;
        }
        $ugame_info = $this->db->query("SELECT * FROM ugame WHERE uid = '$uid'")->result_array();
        $ugame_info = $ugame_info[0];
        $balance = $ugame_info['xpot'];
        if ($balance < $number)
        {
            $result['code'] = 2004;
            return $result;
        }
        $wallet_info = $this->db->query("SELECT * FROM everyday_user_record WHERE uid = '$uid'")->result_array();
        $wallet_info = $wallet_info[0];
        $today_withdraw_xpot = $wallet_info['today_withdraw_xpot'];
        if ($today_withdraw_xpot + $number > 100)
        {
            $result['code'] = 2012;
            return $result;
        }
        $info = $this->db->query("SELECT fullname, id_card, phone_number, nickname, user_sign FROM wx_info WHERE openid = '$uid'")->result_array();
        $info = $info[0];
        $phone_number = $info['phone_number'];
        if (empty($phone_number))
        {
            $result['code'] = 15;
            return $result;
        }
        $fullname = $info['fullname'];
        $id_card = $info['id_card'];
        if (empty($fullname) || empty($id_card))
        {
            $result['code'] = 17;
            return $result;
        }
        $nickname = addslashes($info['nickname']);
        $date = strval(date("Y-m-d H:i:s", time()));
        $flow_id = 'eggs_'.strval(md5('XPOT'.$phone_number.$this->msectime()));
        $user_sign = $info['user_sign'];
        if ($sign != $user_sign)
        {
            $result['code'] = 997;
            return $result;
        }

        $everyday_user_record = $this->db->query("SELECT * FROM everyday_user_record WHERE uid = '$uid'")->row_array();
        if (!isset($everyday_user_record))
        {
            $result['code'] = 997;
            return $result;
        }

        $today_withdraw_total = $everyday_user_record['today_withdraw_xpot'] + $everyday_user_record['today_withdraw_segg'];
        if ($everyday_user_record['today_withdraw_times'] > 2 || $today_withdraw_total >= 100)
        {
            $result['code'] = 2012;
            return $result;
        }

        $xpot_cost = $this->db->query("SELECT key_value FROM config WHERE key_name = 'xpot_cost'")->result_array();
        $xpot_cost = floatval($xpot_cost[0]['key_value']);
        $cny = round($number * $xpot_cost,2);

        $this->db->query("UPDATE ugame SET xpot = xpot - $number, total_sell_xpot = total_sell_xpot + $number WHERE uid = '$uid'");
        $this->db->query("UPDATE everyday_user_record SET today_withdraw_times = today_withdraw_times + 1, today_withdraw_xpot = today_withdraw_xpot + $number WHERE uid = '$uid'");

        $data = array(
            'flow_id' => $flow_id,
            'uid' => $uid,
            'phone_number' => $phone_number,
            'nickname' => $nickname,
            'xpot' => $number,
            'xpot_cost' => $xpot_cost,
            'cny' => $cny,
            'date' => $date
        );
        $this->db->insert('withdraw_xpot', $data);

        $result['code'] = 1;
        $result['flow_id'] = $flow_id;
        return $result;
    }

    public function get_cny_withdraw_list($uid, $start, $count)
    {
        $result = $this->db->query("select * from withdraw_cny where uid = '$uid' and id >= $start order by id desc limit $count")->result_array();
        return $result;
    }

    public function get_xpot_withdraw_list($uid, $start, $count)
    {
        $result = $this->db->query("select * from withdraw_xpot where uid = '$uid' and id >= $start order by id desc limit $count")->result_array();
        return $result;
    }

    public function get_withdraw_hrl_list($uid)
    {
        $list['xpot_withdraw_list'] = $this->db->query("select w.*, info.headimgurl from withdraw_xpot w left join wx_info info on info.openid = w.uid where w.state = 1 order by w.id desc limit 0, 10")->result_array();
        $list['cny_withdraw_lis'] = $this->db->query("select w.*, info.headimgurl from withdraw_cny w left join wx_info info on info.openid = w.uid where w.state = 1 order by w.id desc limit 0, 10")->result_array();
        return $list;
    }

    public function get_withdraw_flow_id($flow_id)
    {
        $data = $this->db->query("select * from withdraw_xpot where flow_id = '$flow_id'")->result_array();
        if (empty($data))
        {
            $data = $this->db->query("select * from withdraw_cny where flow_id = '$flow_id'")->result_array();
        }
        return $data;
    }

    private function msectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        return intval(sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000));
    }
}