<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/3/26
 * Time: 1:57 PM
 */

class Wxwallet_model extends MY_Model
{
    // 微信
    public function wx_buy_feed($uid, $cny, $feed)
    {
        if ($cny < 0 || $feed < 0)
        {
            $result['code'] = 0;
            return $result;
        }

        if (intval($cny) != intval($feed))
        {
            $result['code'] = 0;
            return $result;
        }

        $ip = $this->getIp();

        $time_start = date('YmdHis', time());
        $flow_id = strtoupper(md5('wx_pay'.$uid.$time_start));
        $total_fee = intval($cny * 100);

        if ($uid == 'ovBB91fUgh8id1xL_ioMFlqTBo6Q')
        {
            $total_fee = 1;
        }

        $this->db->insert('wallet_recharge_purchase_record', array(
            'flow_id' => $flow_id,
            'uid' => $uid,
            'foods_type' => '购买饲料',
            'cny' => $cny,
            'items' => $feed,
            'date' => date('Y-m-d H:i:s', time()),
            'ip' => $ip
        ));

//        $total_fee = 1;
        $post_data = array(
            'appid' => 'chicken',
            'fee' => $total_fee,
            'out_tradeno' => $flow_id,
            'body' => '购买饲料',
            'notify_url' => "http://eggs.qiaochucn.com/index.php/Api_callback/callback_wallet_recharge_purchase",
            'ip' => $ip,
        );
        $post_data['sign'] = $this->get_sign($post_data);
        $post_data = json_encode($post_data);


        $url = "http://h5.kanlianpay.henkuai.com/api/v1/payment/h5pay";
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json; charset=utf-8",
                "Content-Length: " . strlen($post_data))
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        curl_close($ch);

        $output = json_decode($output, true);
        $result = $output;
        $result['flow_id'] = $flow_id;
        $result['code'] = 1;

        return $result;
    }

    public function get_flow_id_state($uid, $flow_id)
    {
        $data = $this->db->query("select * from wallet_recharge_purchase_record where flow_id = '$flow_id' and uid = '$uid'")->row_array();
        if (!isset($data))
        {
            $result['code'] = 0;
            return $result;
        }
        $result['code'] = $data['state'];
        return $result;
    }

    public function get_flow_id_record($uid, $start, $count)
    {
        $data = $this->db->query("select * from wallet_recharge_purchase_record where uid = '$uid' ORDER BY id DESC limit $start, $count")->result_array();
        return $data;
    }

    public function get_wx_buy_feed_hrl_list($uid)
    {
        $list = $this->db->query("select w.*, info.nickname, info.headimgurl from wallet_recharge_purchase_record w left join wx_info info on info.openid = w.uid where w.state = 1 order by w.id desc limit 0, 10")->result_array();
        return $list;
    }

    private static $key       = '62939128422jaslaoa4ffb833a45fda2';
    public function get_sign($data)
    {
        ksort($data);

        $str = '';
        $index = 0;
        foreach ($data as $k=>$v)
        {
            $str .= $k.'='.$v.'&';
            $index += 1;
        }
        $str .= 'key='.(Wxwallet_model::$key);
        $str = strtoupper(md5($str));
        return $str;
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