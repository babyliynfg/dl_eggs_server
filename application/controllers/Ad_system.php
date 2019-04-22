<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/3/13
 * Time: 3:57 PM
 */
header('Access-Control-Allow-Origin:*');
class Ad_system extends CI_Controller
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
        $this->load->model('adsystem_model');

    }
//发起
//http://eggs.qiaochucn.com/index.php/Ad_system/make_ad_info
//uid  title  url   reward_coin_total  quota_total   info_title1  info_title2
    // 默认
    public function index()
    {
        echo "hello";
    }

    //报名
    public function sign_up()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $task_id = $post['task_id'];

        $data = $post;

        $result['code'] = $this->adsystem_model->insert_ad_finish_info($data);

        echo $this->encrypt(json_encode($result));
    }



    //做任务
    public function do_ad_info()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $task_id = $post['task_id'];
        $image_url1 = $post['image_url1'];
        $image_url2 = $post['image_url2'];
        $info1 = $post['info1'];
        $info2 = $post['info2'];

        $data = $post;

        $result['code'] = $this->adsystem_model->do_ad_info($data);

        echo $this->encrypt(json_encode($result));
    }

    //做任务
    public function do_ad_info_h5()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        $uid = $post['uid'];
        $task_id = $post['task_id'];
        $image_url1 = $post['image_url1'];
        $image_url2 = $post['image_url2'];
        $info1 = $post['info1'];
        $info2 = $post['info2'];

        $data = $post;

        $result['code'] = $this->adsystem_model->do_ad_info($data);

        echo (json_encode($result));
    }

    //发起广告
    public function make_ad_info()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $post['nickname'] = urldecode($post['nickname']);
        $uid = $post['uid'];
        $nickname = $post['nickname'];
        $headimgurl = $post['headimgurl'];
        $title = $post['title'];
        $content0 = $post['content0'];
        $open_url = $post['open_url'];
        $image_url1 = $post['image_url1'];
        $content1 = $post['content1'];
        $image_url1 = $post['image_url1'];
        $content2 = $post['content2'];
        $reward_coin_total = floatval($post['reward_coin_total']);
        $quota_total = max(intval($post['quota_total']), 1);
        $info_title1 = $post['info_title1'];
        $info_title2 = isset($post['info_title2']) ? $post['info_title2'] : '';

        $reward_coin_once = $reward_coin_total / $quota_total;
        $date = date("Y-m-d H:i:s", time());

        $data = $post;
        $data['reward_coin_once'] = $reward_coin_once;
        $data['date'] = $date;

        $xpot = $this->ugame_model->get_coins_num($uid, 'feed');
        if ($xpot < $reward_coin_total * 1.2)
        {
            $result['code'] = 0;
        }
        else
        {
            $res = $this->ugame_model->add_coins($uid, 'feed', -$reward_coin_total * 1.2);
            $result['code'] = $res ? $this->adsystem_model->insert_ad_info($data) : 0;
        }

        echo $this->encrypt(json_encode($result));
    }

    //广告任务列表
    public function get_ad_info_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['list'] = $this->adsystem_model->get_ad_info_list($start, $count);

        echo $this->encrypt(json_encode($result));
    }

    //广告任务列表
    public function get_my_ad_info_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['list'] = $this->adsystem_model->get_my_ad_info_list($uid, $start, $count);

        echo $this->encrypt(json_encode($result));
    }

    //获取用户某广告的完成状态
    public function get_ad_finish_info()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $task_id = $post['task_id'];

        $result['code'] = 1;
        $result['info'] = $this->adsystem_model->get_ad_finish_info($uid, $task_id);

        echo $this->encrypt(json_encode($result));
    }

    //获取某用户完成的已发奖励的任务
    public function get_ad_finish_info_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['list'] = $this->adsystem_model->get_ad_finish_info_list($uid, $start, $count);

        echo $this->encrypt(json_encode($result));
    }

    //获取某用户完成的审核中任务
    public function get_examine_ad_finish_info_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['list'] = $this->adsystem_model->get_examine_ad_finish_info_list($uid, $start, $count);

        echo $this->encrypt(json_encode($result));
    }

    //获取某用户完成的未提交任务
    public function get_uncommitted_ad_finish_info_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['list'] = $this->adsystem_model->get_uncommitted_ad_finish_info_list($uid, $start, $count);

        echo $this->encrypt(json_encode($result));
    }

    //获取某用户完成的被拒绝的任务
    public function get_refuse_ad_finish_info_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['list'] = $this->adsystem_model->get_refuse_ad_finish_info_list($uid, $start, $count);

        echo $this->encrypt(json_encode($result));
    }

    //我的审核列表
    public function get_owner_examine_ad_finish_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['list'] = $this->adsystem_model->get_owner_examine_ad_finish_list($uid, $start, $count);

        echo $this->encrypt(json_encode($result));
    }

    //我的审核过列表
    public function get_owner_examine_finish_ad_finish_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['list'] = $this->adsystem_model->get_owner_examine_finish_ad_finish_list($uid, $start, $count);

        echo $this->encrypt(json_encode($result));
    }

    //审核
    public function owner_examine_ad_info()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $task_id = $post['task_id'];
        $other_uid = $post['other_uid'];
        $state = $post['state'];
        $extra_message = $post['extra_message'];


        $result['code'] = $this->adsystem_model->owner_examine_ad_info($uid, $task_id, $other_uid, $state, $extra_message);

        echo $this->encrypt(json_encode($result));
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

class AES
{
// 创建静态私有的变量保存该类对象
    static private $instance;
// 防止直接创建对象
    private function __construct()
    {
//echo "我被实例化了";
    }
// 防止克隆对象
    private function __clone()
    {
    }

    static public function getInstance()
    {
// 没有则创建
        if (!self::$instance instanceof self)
        {
            self::$instance = new self();
        }
        return self::$instance;
    }
// 加密
    public function encrypt_pass($input, $key="xpot&&liyuanfeng", $iv = "1122334455667788")
    {
        $en_data = openssl_encrypt($input, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $en_data = base64_encode($en_data);
        $en_data = str_replace(array('+'),array('_'), $en_data);
        return $en_data;
    }

// 解密
    public function decrypt_pass($input, $key="xpot&&liyuanfeng", $iv = "1122334455667788")
    {
        $de_data = str_replace(array('_'),array('+'), $input);
        $de_data = base64_decode($de_data);
        $de_data = openssl_decrypt($de_data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $de_data;
    }
}