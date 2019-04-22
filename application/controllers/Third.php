<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2018/12/19
 * Time: 3:54 PM
 */

class Third extends CI_Controller
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
        $this->load->model('m_answer_model');
        $this->load->model('friend_model');
        $this->load->model('wxwallet_model');

    }

    // 默认
    public function index()
    {
        echo "hello";
    }

    // 微信
    public function wx_buy_feed()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $cny = $post['cny'];
        $feed = $post['feed'];

        $result = $this->wxwallet_model->wx_buy_feed($uid, $cny, $feed);
        echo $this->encrypt(json_encode($result));
    }

    // 查询
    public function get_flow_id_state()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $flow_id = $post['flow_id'];
        $result = $this->wxwallet_model->get_flow_id_state($uid, $flow_id);
        echo $this->encrypt(json_encode($result));
    }

    // 记录
    public function get_flow_id_record()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['list'] = $this->wxwallet_model->get_flow_id_record($uid, $start, $count);
        echo $this->encrypt(json_encode($result));
    }

    //饲料购买公告信息
    public function get_wx_buy_feed_hrl_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $result['code'] = 1;
        $result['list'] = $this->wxwallet_model->get_wx_buy_feed_hrl_list($uid);
        echo $this->encrypt(json_encode($result));
    }


    // 闲玩
    private static $xianawn_appid     = '3140';
    private static $xianawn_key       = '55xqn6gierhiouis';

    public function get_xianawn_ios_url()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $deviceid = $post['deviceid'];
        $ptype = '1';
        $keycode = md5(Third::$xianawn_appid.$deviceid.$ptype.$uid.Third::$xianawn_key);

        $result['code'] = 1;
        $result['url'] = 'https://h5.51xianwan.com/try/iOS/try_list_ios.aspx?'.'ptype='.$ptype.'&deviceid='.$deviceid.'&appid='.Third::$xianawn_appid.'&appsign='.$uid.'&keycode='.$keycode;

        echo $this->encrypt(json_encode($result));
    }

    public function callback_make_order()
    {
        if ($this->getIp() != '139.224.165.158' && $this->getIp() != '112.17.103.129')
        {
            echo '{"success": 0,"message": "没有权限"}';
            die;
        }

        $get = $this->input->get();
        $adid = $get['adid'];
        $adname = $get['adname'];
        $appid = $get['appid'];
        $ordernum = $get['ordernum'];
        $dlevel = $get['dlevel'];
        $pagename = $get['pagename'];
        $deviceid = $get['deviceid'];
        $simid = $get['simid'];
        $appsign = $get['appsign'];
        $merid = $get['merid'];
        $event = $get['event'];
        $price = $get['price'];
        $money = $get['money'];
        $itime = $get['itime'];
        $keycode = $get['keycode'];



        $data = $this->db->query("SELECT * FROM third_xianwan WHERE ordernum = '$ordernum'")->result_array();
        if (!empty($data))
        {
            echo '{"success": 1,"message": "订单已接收"}';
            die;
        }
        $data = array(
            'adid' => $adid,
            'adname' => $adname,
            'appid' => $appid,
            'ordernum' => $ordernum,
            'dlevel' => $dlevel,
            'pagename' => $pagename,
            'deviceid' => $deviceid,
            'simid' => $simid,
            'appsign' => $appsign,
            'merid' => $merid,
            'event' => $event,
            'price' => $price,
            'money' => $money,
            'itime' => $itime
        );
        $this->db->insert("third_xianwan", $data);


        $xpot_cost = $this->ugame_model->get_config('xpot_cost');
        $eggs = round($money/$xpot_cost,5);
        $this->compensate_model->inset_compensate_xpot($appsign, "游戏充值返利", "由于您在【".$adname."】中充值".$price."元人民币，特发放".$eggs."鸡蛋给您，请查收…", $eggs);



        echo '{"success": 1,"message": "接收成功"}';

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