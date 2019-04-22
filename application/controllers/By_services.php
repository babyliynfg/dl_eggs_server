<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/3/21
 * Time: 3:42 PM
 */
header('Access-Control-Allow-Origin:*');
class By_services extends CI_Controller
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
        $this->load->model('by_model');
    }

    private static $api_url = "https://open.biyong.sg/dev-api";
    private static $app_id = "e3c5b910b08023f008cdb8e07c4235a5";

    public function index()
    {
        echo "hello";
    }

    //报名
    public function auth()
    {
        if (config_item('s_maintain'))
        {
            $data['code'] = "2";
            echo $this->encrypt(json_encode($data));
            die;
        }

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $authToken = $post['authToken'];

        $userinfo = $this->by_model->auth($authToken);
        $uid = $userinfo['openid'];

        $app_channel = 200;
        $app_bundle_id = '';
        $os = '币用H5';

        $result['code'] = "0";

        $nickname = $this->wxinfo_model->get_nickname_info($uid);
        if ($nickname == null)
        {
            $id = $this->wxinfo_model->register_wx_applet_uinfo($userinfo);
            if ($id != 0)
            {
                $this->ugame_model->register_ugame($id, $uid, $os, $app_bundle_id, $app_channel);
            }
            else
            {
                echo $this->encrypt(json_encode($result));
                die;
            }
        }
        else
        {
            if($this->is_in_user_blacklist($uid))
            {
                $data['code'] = "999";
                echo $this->encrypt(json_encode($data));
                die;
            }

            $this->wxinfo_model->update_info($uid, $userinfo);
//            $this->ugame_model->update_ugame($uid, $os, $app_bundle_id);
        }


        //签到
        $this->ugame_model->everyday_login_wx($uid);

        $data['code'] = "1";
        $data['uid'] = $uid;
        $data['token'] = $this->ugame_model->get_user_token($uid);
        $gonggao = $this->ugame_model->get_config('gonggao');
        $data['gonggao'] = $gonggao;

        $data['user_sign'] = "";
        $data['user_sign'] = $this->wxinfo_model->record_user_sign($uid);

        echo $this->encrypt(json_encode($data));
    }

    // 创建支付订单 兑换福利鸡
    public function create_buy_dy_hen_flow_id()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $dy_hen = $post['dy_hen'];
        $sign = $post['sign'];

        if (!$this->wxinfo_model->check_user_sign($uid, $sign))
        {
            $data['code'] = "0";
            echo $this->encrypt(json_encode($data));
            die;
        }

        $data = $this->by_model->create_buy_dy_hen_flow_id($uid, $dy_hen);
        echo $this->encrypt(json_encode($data));
    }

    public function query_buy_dy_hen_flow_id()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $flow_id = $post['flow_id'];
        $sign = $post['sign'];

        if (!$this->wxinfo_model->check_user_sign($uid, $sign))
        {
            $data['code'] = "0";
            echo $this->encrypt(json_encode($data));
            die;
        }

        $data = $this->by_model->query_buy_dy_hen_flow_id($uid, $flow_id);
        echo $this->encrypt(json_encode($data));
    }

    // 创建支付订单 兑换饲料
    public function create_buy_feed_flow_id()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $feed = $post['feed'];
        $sign = $post['sign'];

        if (!$this->wxinfo_model->check_user_sign($uid, $sign))
        {
            $data['code'] = "0";
            echo $this->encrypt(json_encode($data));
            die;
        }

        $data = $this->by_model->create_buy_feed_flow_id($uid, $feed);
        echo $this->encrypt(json_encode($data));
    }

    public function query_buy_feed_flow_id()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $flow_id = $post['flow_id'];
        $sign = $post['sign'];

        if (!$this->wxinfo_model->check_user_sign($uid, $sign))
        {
            $data['code'] = "0";
            echo $this->encrypt(json_encode($data));
            die;
        }

        $data = $this->by_model->query_buy_feed_flow_id($uid, $flow_id);
        echo $this->encrypt(json_encode($data));
    }

    public function is_in_user_blacklist($convict_uid)
    {
        $sel = $this->db->query("SELECT `level` FROM user_blacklist WHERE uid = '$convict_uid'")->result_array();
        if (empty($sel))
        {
            return 0;
        }
        $level = $sel[0]['level'];

        return ($level == 9) ? 1 : 0;
    }


//    function sha256($data, $rawOutput=false){
//        if(!is_scalar($data)){
//            return false;
//        }
//        $data = (string)$data;
//        $rawOutput = !!$rawOutput;
//        return hash('sha256', $data, $rawOutput);
//    }


//    private static $priKey = '';

//    function encrypt_rsa($signString)
//    {
//        return $this->get_sign($signString. By_services::$priKey);
//    }

    /**
     * 生成签名
     * @param    string     $signString 待签名字符串
     * @param    [type]     $priKey     私钥
     * @return   string     base64结果值
     */
//    function get_sign($signString, $priKey){
//        $privKeyId = openssl_pkey_get_private($priKey);
//        $signature = '';
//        openssl_sign($signString, $signature, $privKeyId);
//        openssl_free_key($privKeyId);
//        return base64_encode($signature);
//    }

    /**
     * 校验签名
     * @param    string     $pubKey 公钥
     * @param    string     $sign   签名
     * @param    string     $toSign 待签名字符串
     * @return   bool
     */
//    function check_sign($pubKey,$sign,$toSign){
//        $publicKeyId = openssl_pkey_get_public($pubKey);
//        $result = openssl_verify($toSign, base64_decode($sign), $publicKeyId);
//        openssl_free_key($publicKeyId);
//        return $result === 1 ? true : false;
//    }

    /**
     * 获取待签名字符串
     * @param    array     $params 参数数组
     * @return   string
     */
//    function get_sign_string($params){
//        unset($params['sign']);
//        ksort($params);
//        reset($params);
//
//        $pairs = array();
//        foreach ($params as $k => $v) {
//            if(!empty($v)){
//                $pairs[] = "$k=$v";
//            }
//        }
//
//        return implode('&', $pairs);
//    }


    /**
     * 加密字符串
     * @param string $str 字符串
     * @param string $key 加密key
     * @param integer $expire 有效期（秒）
     * @return string
     */
    private function encrypt($data, $expire = 0)
    {
        $expire = sprintf('%010d', $expire ? $expire + time() : 0);
        $data = $expire.$data;
        $data = base64_encode($data);

        $str  = $data;

        $left = '';
        $right = '';
        for ($i = 0; $i < strlen($str); $i++)
        {
            $s = substr($str, $i, 1);
            if ($i % 2 == 0)
            {
                $left .= $s;
            }
            else
            {
                $right .= $s;
            }
        }

        $result = $left."_".$right;
        $result = base64_encode($result);
        $result = str_replace(array('+'),array('-'), $result);
        $result = str_replace(array('/'),array('+'), $result);
        $result = str_replace(array('-'),array('/'), $result);
        $result = str_replace(array('='),array('_'), $result);
        return $result;
    }

    /**
     * 解密字符串
     * @param string $str 字符串
     * @param string $key 加密key
     * @return string
     */
    private function decrypt($data)
    {
        $data = str_replace(array('_'),array('='), $data);


        $data = str_replace(array('+'),array('-'), $data);
        $data = str_replace(array('/'),array('+'), $data);
        $data = str_replace(array('-'),array('/'), $data);
        $data   = base64_decode($data);

        $array = explode('_',$data);

        if (count($array) != 2)
            return '';

        $left = $array[0];
        $right = $array[1];
        $str = '';
        for($i = 0; $i < strlen($left); $i++)
        {
            $str .= $left[$i];
            if ($i == strlen($right))
                break;
            $str .= $right[$i];
        }

        $result = $str;

        $result = base64_decode($result);

        $expire = substr($result, 0, 10);
        $expire = intval($expire);
        $time = time();
        if($expire > 0 && $expire < $time)
            return '';

        $result = substr($result, 10, strlen($result) - 10);
        return $result;
    }
}