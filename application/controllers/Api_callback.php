<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/3/26
 * Time: 5:45 PM
 */

class Api_callback extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('wxinfo_model');
        $this->load->model('ugame_model');
        $this->load->model('wakuang_model');
        $this->load->model('message_model');
        $this->load->model('vc_model');
        $this->load->model('compensate_model');
        $this->load->model('exchange_model');
        $this->load->model('activity_model');
        $this->load->model('wallet_model');
        $this->load->model('ns_model');
        $this->load->model('wxwallet_model');
        $this->load->model('by_model');
    }

    // 微信充值回调
    public function callback_wallet_recharge_purchase()
    {
        if($this->getIp() != '106.75.97.24')
        {
            echo "FAILD";
            die;
        }

        $json_data = json_decode(file_get_contents('php://input'), true);

        if ($json_data['error'] == 0)
        {
            $array = $json_data;
            $original_sign = $array['sign'];
            unset($array['sign']);
            $validation_sign = $this->get_sign($array);
            if ($original_sign != $validation_sign)
            {
                echo "FAILD";
                die;
            }

            $this->db->insert('test', array(
                'data' => json_encode($json_data)
            ));
            $flow_id = $json_data['out_tradeno'];
            $this->db->query("update wallet_recharge_purchase_record set state = 1 where flow_id = '$flow_id'");
            $rows = $this->db->affected_rows();
            if ($rows == 1)
            {
                $data = $this->db->query("select * from wallet_recharge_purchase_record where flow_id = '$flow_id'")->row_array();
                $uid = $data['uid'];
                $feed = $data['items'];

//                $feed = $json_data['fee'] / 100;
                $this->db->query("update ugame set feed = feed + $feed where uid = '$uid'");
                $this->db->insert('compensate', array(
                    'uid' => $uid,
                    'title' => '购买饲料到账通知',
                    'text' => '您购买了'.$feed.'袋饲料，已经到账啦。',
                    'send_date' => date('Y-m-d H:i:s', time())
                ));
            }
            echo "SUCCESS";
        }
        else
        {
            echo "FAILD";

        }

    }

    // 币用支付回调
    public function callback_by_wallet_recharge_purchase()
    {
        header('Content-Type: application/json\r\n"."AppId:8e7684b8227c7497c8068bc6b445ada6\r\n');
        $input = file_get_contents('php://input', 'r');
        echo $this->by_model->callback_by_wallet_recharge_purchase($input);
    }

    public function get_sign($data)
    {
        $key = '62939128422jaslaoa4ffb833a45fda2';

        ksort($data);

        $str = '';
        $index = 0;
        foreach ($data as $k=>$v)
        {
            $str .= $k.'='.$v.'&';
            $index += 1;
        }
        $str .= 'key='.$key;
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