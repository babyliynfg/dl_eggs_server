<?php
/**
 * Created by PhpStorm.
 * User: wps
 * Date: 2018/5/2
 * Time: 16:42
 */
header('Access-Control-Allow-Origin:*');
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

class Main extends CI_Controller
{

 /**
 *  code
 *  0 其他异常
 *  1 登陆成功
 *  2 服务器维护
 *  3 从微信服务器拉取数据异常
 *  4 关联微信失败，此微信已关联其他账号
 *  5 注册失败，该手机号已绑定过其他账号
 *  10 验证码错误
 *  11 验证码超时
 *  12 该账号已绑定手机号
 *  13 该手机号已绑定过其他账号
 *  14 该手机号与该账号不匹配
 *  15 该账号需要绑定手机号
 *  16 该身份证已经绑定过其他账号
 *  17 该账号需要实名认证
 *  20 手机号注册，需完善数据
 *  51 密码错误
 *  61 兑换码无效或已经被领取
 *  62 您已经领过同类型的兑换码
 *  71 2次发帖时间间隔不得小于3分钟
 *  41 新人保护中，禁止偷蛋
 *  101 员工在休息，一会再来
 *  201 你们已经是好友了
 *  997 安全验证失效
 *  998 黑名单限制交易
 *  999 黑名单
  * 2004 余额不足
 *  2012 超出xpot每日提现限额
  * 3001 拒绝次数太多，请联系客服
 **/

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
        $this->load->model('redis_model');
    }

    // 默认
    public function index()
    {
        echo "hello";
    }

    //微信登录
    function wx_login()
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

        $is_pc = $post['is_pc'];
        $data['code'] = "0";
        if ($is_pc == 0)
        {
            $token = $post['openid'];
            $app_channel = isset($post['app_channel']) ? $post['app_channel'] : 0;
            $app_bundle_id = isset($post['app_bundle_id']) ? $post['app_bundle_id'] : 'com.cross.eggs';
            $os = isset($post['os']) ? $post['os'] : '';

            $data['code'] = "0";
            $userinfo = $this->get_wechat_from_token($token, $app_bundle_id);
            $userinfo = json_decode(json_encode($userinfo), true);

            if (empty($userinfo))
            {
                $data['code'] = "3";
                $this->output->set_header('Content-Type: application/json; charset=utf-8');
                echo $this->encrypt(json_encode($data));
                die;
            }

            $unionid = $userinfo['unionid'];
            $uid = $this->wxinfo_model->check_register_uinfo($unionid);

            if ($uid == null)
            {
                $uid = $userinfo['openid'];
                $id = $this->wxinfo_model->register_wx_applet_uinfo($userinfo);
                if ($id != 0)
                {
                    $this->ugame_model->register_ugame($id, $uid, $os, $app_bundle_id, $app_channel);
                }
                else
                {
                    echo $this->encrypt(json_encode($data));
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
                $this->ugame_model->update_ugame($uid, $os, $app_bundle_id);
            }
        }
        else
        {
            $uid = "gm";
        }

        echo $this->encrypt(json_encode($this->login($uid, false)));
    }

    // 微信小程序登录
    function wx_applet_login()
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

        $data['code'] = "0";
        $userinfo = $post['userinfo'];
        $os = isset($post['os']) ? $post['os'] : 'WEIXIN';

        $userinfo = stripslashes($userinfo);
        $userinfo = json_decode($userinfo, true);
        $unionid = $userinfo['unionid'];
        $uid = $this->wxinfo_model->check_register_uinfo($unionid);

        if ($uid == null)
        {
            $uid = $userinfo['openid'];
            $id = $this->wxinfo_model->register_wx_applet_uinfo($userinfo);
            if ($id != 0)
            {
                $this->ugame_model->register_ugame($id, $uid, $os, '', 0);
            }
            else
            {
                echo $this->encrypt(json_encode($data));
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
        }

        echo $this->encrypt(json_encode($this->login($uid, false)));
    }

    //facebook登录
    function facebook_login()
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
        $uid = $post['uid'];
        $is_pc = $post['is_pc'];
        $os = isset($post['os']) ? $post['os'] : 'WEIXIN';

        $data['code'] = "0";
        if ($is_pc == 0)
        {
            $userinfo = $post['userinfo'];
            $userinfo = stripslashes($userinfo);
            $userinfo = json_decode($userinfo, true);

            if($this->is_in_user_blacklist($uid))
            {
                $data['code'] = "999";
                echo $this->encrypt(json_encode($data));
                die;
            }

            $result_data = $this->wxinfo_model->check_register_uinfo($uid);
            if (empty($result_data))
            {
                $id = $this->wxinfo_model->register_wx_applet_uinfo($userinfo);
                if ($id != 0)
                {
                    $this->ugame_model->register_ugame($id, $uid, $os, '', 0);
                }
                else
                {
                    echo $this->encrypt(json_encode($data));
                    die;
                }
            }
            else
            {
                $this->wxinfo_model->update_facebook_info($uid, $userinfo);
            }
        }
        else
        {
            $uid = "gm";
        }

        echo $this->encrypt(json_encode($this->login($uid, false)));
    }

    //微信自动登录
    function uid_login()
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
        $uid = $post['uid'];
        $sign = $post['sign'];

        if($this->is_in_user_blacklist($uid))
        {
            $data['code'] = "999";
            echo $this->encrypt(json_encode($data));
            die;
        }

        if (!$this->wxinfo_model->check_user_sign($uid, $sign))
        {
            $data['code'] = "0";
            echo $this->encrypt(json_encode($data));
            die;
        }

        echo $this->encrypt(json_encode($this->login($uid, true)));
    }

    //矿工编号登录
    function id_login()
    {
        if ($this->getIp() != '1.119.14.42')
            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $id = $post['id'];
        $uid = $this->wxinfo_model->get_user_uid_with_id($id);

        if($this->is_in_user_blacklist($uid))
        {
            $data['code'] = "999";
            echo $this->encrypt(json_encode($data));
            die;
        }

        echo $this->encrypt(json_encode($this->login_no_signin($uid)));
    }

    //手机号登录
    function phone_number_code_login()
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
        $phone_number = $post['phone_number'];
        $code = $post['code'];
        $app_bundle_id = isset($post['app_bundle_id']) ? $post['app_bundle_id'] : 'com.cross.eggs';
        $os = isset($post['os']) ? $post['os'] : '';

        $code_info = $this->vc_model->get_phone_verification_code($phone_number);
        if ($code != $code_info['code'])
        {
            //验证码错误
            $data['code'] = "10";
            echo $this->encrypt(json_encode($data));
            die;
        }
        if (time() - strtotime($code_info['code_date']) > 180)
        {
            //验证码超时
            $data['code'] = "11";
            echo $this->encrypt(json_encode($data));
            die;
        }
        $uid = $this->wxinfo_model->get_user_uid_with_phone_number($phone_number);
        if ($uid == null)
        {
            $data['code'] = "20";
            echo $this->encrypt(json_encode($data));
            die;
        }
        else
        {
            if($this->is_in_user_blacklist($uid))
            {
                $data['code'] = "999";
                echo $this->encrypt(json_encode($data));
                die;
            }
        }

        $this->ugame_model->update_ugame($uid, $os, $app_bundle_id);

        echo $this->encrypt(json_encode($this->login($uid, false)));
    }

    // 手机号注册
    function phone_number_evpi()
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
        $phone_number = $post['phone_number'];
        $nickname = $post['nickname'];
        $sex = $post['sex'];
        $app_channel = isset($post['app_channel']) ? $post['app_channel'] : 0;
        $app_bundle_id = isset($post['app_bundle_id']) ? $post['app_bundle_id'] : 'com.cross.eggs';
        $os = isset($post['os']) ? $post['os'] : '';

        $headimgurl = isset($post['headimgurl']) ? $post['headimgurl'] : '';
        $uid = "phone_".$phone_number;

        $array['openid'] = $uid;
        $array['unionid'] = $uid;
        $array['nickname'] = $nickname;
        $array['sex'] = $sex;
        $array['phone_number'] = $phone_number;
        $array['headimgurl'] = $headimgurl;


        $is_registerd = $this->wxinfo_model->get_user_uid_with_phone_number($phone_number);
        if ($is_registerd)
        {
            $data['code'] = "5";
            $this->output->set_header('Content-Type: application/json; charset=utf-8');
            echo $this->encrypt(json_encode($data));
            die;
        }
        else
        {
            $id = $this->wxinfo_model->register_wx_applet_uinfo($array);
            if ($id != 0)
            {
                $this->ugame_model->register_ugame($id, $uid, $os, $app_bundle_id, $app_channel);
            }
            else
            {
                $data['code'] = "4";
                echo $this->encrypt(json_encode($data));
                die;
            }

            echo $this->encrypt(json_encode($this->login($uid, false)));
        }
    }

    // 手机号注册并关联微信
    function binding_wx()
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

        $token = $post['openid'];
        $phone_number = $post['phone_number'];
        $app_channel = isset($post['app_channel']) ? $post['app_channel'] : 0;
        $app_bundle_id = isset($post['app_bundle_id']) ? $post['app_bundle_id'] : 'com.cross.eggs';
        $os = isset($post['os']) ? $post['os'] : '';

        $data['code'] = "0";
        $user_info_obj = $this->get_wechat_from_token($token, $app_bundle_id);
        $array = json_decode(json_encode($user_info_obj), TRUE);
        $uid = $array['openid'];
        if (empty($uid))
        {
            $data['code'] = "3";
            $this->output->set_header('Content-Type: application/json; charset=utf-8');
            echo $this->encrypt(json_encode($data));
            die;
        }
        unset($array['privilege']);
        $array['last_openid'] = $token;
        $array['phone_number'] = $phone_number;


        $is_registerd = $this->wxinfo_model->get_user_uid_with_phone_number($phone_number);
        $result_data = $this->wxinfo_model->check_register_uinfo($uid);
        if ($is_registerd)
        {
            $data['code'] = "5";
            $this->output->set_header('Content-Type: application/json; charset=utf-8');
            echo $this->encrypt(json_encode($data));
            die;
        }
        else if (!empty($result_data))
        {
            $data['code'] = "4";
            $this->output->set_header('Content-Type: application/json; charset=utf-8');
            echo $this->encrypt(json_encode($data));
            die;
        }
        else
        {
            $id = $this->wxinfo_model->register_wx_applet_uinfo($array);
            if ($id != 0)
            {
                $this->ugame_model->register_ugame($id, $uid, $os, $app_bundle_id, $app_channel);
            }
            else
            {
                $data['code'] = "4";
                echo $this->encrypt(json_encode($data));
                die;
            }

            echo $this->encrypt(json_encode($this->login($uid, false)));
        }
    }

//    // 关联facebook
//    function binding_facebook()
//    {
//        if (config_item('s_maintain'))
//        {
//            $data['code'] = "2";
//            echo $this->encrypt(json_encode($data));
//            die;
//        }
//
//        $post = $this->input->post();
//        $post = $post['data'];
//        $post = json_decode($this->decrypt($post), true);
//
//        $phone_number = $post['phone_number'];
//        $userinfo = $post['userinfo'];
//        $userinfo = stripslashes($userinfo);
//        $userinfo = json_decode($userinfo, true);
//        $userinfo['phone_number'] = $phone_number;
//
//        $uid = $userinfo['userID'];
//
//        $data['code'] = "0";
//
//
//        $is_registerd = $this->wxinfo_model->get_user_uid_with_phone_number($phone_number);
//        $result_data = $this->wxinfo_model->check_register_uinfo($uid);
//        if ($is_registerd)
//        {
//            $data['code'] = "5";
//            $this->output->set_header('Content-Type: application/json; charset=utf-8');
//            echo $this->encrypt(json_encode($data));
//            die;
//        }
//        else if (!empty($result_data))
//        {
//            $data['code'] = "4";
//            $this->output->set_header('Content-Type: application/json; charset=utf-8');
//            echo $this->encrypt(json_encode($data));
//            die;
//        }
//        else
//        {
//            if ($this->wxinfo_model->register_facebook_uinfo($userinfo))
//            {
//                $this->ugame_model->register_ugame($uid, 0);
//            }
//            else
//            {
//                $data['code'] = "4";
//                echo $this->encrypt(json_encode($data));
//                die;
//            }
//
//            echo $this->encrypt(json_encode($this->login($uid, false)));
//        }
//    }

    //发送手机验证码
    public function send_phone_verification_code()
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
        $phone_number = $post['phone_number'];
        $nationcode = isset($post['nationcode']) ? $post['nationcode'] : '86';

        if ($phone_number == '16888888888')
        {
            $data_res['code'] = 1;
            $this->vc_model->insert_phone_verification_code('16888888888', '666666');
            echo $this->encrypt(json_encode($data_res));
        }
        else if ($phone_number == '18510254646')
        {
            $data_res['code'] = 1;
            $this->vc_model->insert_phone_verification_code('18510254646', '111111');
            echo $this->encrypt(json_encode($data_res));
        }
        else if ($phone_number == '18868686868')
        {
            $data_res['code'] = 1;
            $this->vc_model->insert_phone_verification_code('18868686868', '000000');
            echo $this->encrypt(json_encode($data_res));
        }
        else
        {
            $verification_code = strval(rand(100000, 999999));

            $mobile = $phone_number;
            srand((double)microtime()*100000);
            $random = (int)rand();
            $time = time();

            $appkey = '3abb9c4a38cdc3ab1adb843bbb9c1fb3';
            $appid = '1400155604';

            $array = array(
                "appkey" => $appkey,
                "random" => $random,
                "time" => $time,
                "mobile" => $mobile
            );

            $sig = $this->get_short_message_sign($array);

            if ($nationcode == '86')
            {
                $message = $verification_code.'为您的登录验证码，请于1分钟内填写。如非本人操作，请忽略本短信。';
            }
            else
            {
                $message = 'your verification code is '.$verification_code.'(valid for 1 minutes). For account safety, don\'t forward the code to others.';
            }

            $url = 'https://yun.tim.qq.com/v5/tlssmssvr/sendsms?sdkappid='.$appid.'&random='.$random;
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
            curl_setopt($ch, CURLOPT_POST, 1);

            $post_data = array(
                "ext" => '',
                "extend" => '',
                "msg" => $message,
                "sig" => $sig,
                "tel" => array(
                    "mobile" => $mobile,
                    "nationcode" => $nationcode
                ),
                "time" => $time,
                "type" => 0
            );

            $post_data = json_encode($post_data);

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    "Content-Type: application/json; charset=utf-8",
                    "Content-Length: " . strlen($post_data))
            );
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            $output = curl_exec($ch);
            curl_close($ch);

            $output = json_decode($output, true);
            $data_res['code'] = 0;
            if ($output['result'] == 0)
            {
                $data_res['code'] = 1;
                $this->vc_model->insert_phone_verification_code($phone_number, $verification_code);
            }

            echo $this->encrypt(json_encode($data_res));
        }
    }

    private function login_no_signin($uid)
    {
        $data['code'] = "1";
        $data['uid'] = $uid;
        $data['token'] = '';//$this->ugame_model->get_user_token($uid);
        $gonggao = $this->ugame_model->get_config('gonggao');
        $data['gonggao'] = $gonggao;
        $data['user_sign'] = $this->wxinfo_model->get_user_sign($uid);
        return $data;
    }

    private function login($uid, $auto)
    {
        //签到
        $this->ugame_model->everyday_login_wx($uid);

        $data['code'] = "1";
        $data['uid'] = $uid;
        $data['token'] = '';//$this->ugame_model->get_user_token($uid);
        $gonggao = $this->ugame_model->get_config('gonggao');
        $data['gonggao'] = $gonggao;

        $data['user_sign'] = "";
        if (!$auto)
        {
            $data['user_sign'] = $this->wxinfo_model->record_user_sign($uid);
        }

//        $this->output->set_header('Content-Type: application/json; charset=utf-8');

        return $data;
    }

    private function get_wechat_app_info($app_bundle_id)
    {
        if ($app_bundle_id == 'com.cross.kanlianla')
        {
            return array(
                'wechat_appid' => 'wxd51a402de51748d3',
                'wechat_sercert' => '08fb4b6836515df1452bc5ec3b25d03d'
            );
        }
        else
        {
            return array(
                'wechat_appid' => 'wxba4936183822ef02',
                'wechat_sercert' => '7d081619af3d2e1547d8b28c5a8c346e'
            );
        }
    }

    //获取token
    private function get_wechat_from_token($token, $app_bundle_id)
    {
        $app_info = $this->get_wechat_app_info($app_bundle_id);

        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $app_info['wechat_appid'] .
            '&secret=' . $app_info['wechat_sercert'] . '&code=' . $token . '&grant_type=authorization_code';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $output = curl_exec($ch);
        if(curl_error($ch)) {
            curl_close($ch);
            return null;
        }
        curl_close($ch);
        $this->log->write_log($level = 'error', $output);
        $json_obj = json_decode($output);
        if (!is_object($json_obj)) {
            return null;
        }
        $openid_exists = property_exists($json_obj, 'openid');
        if (!$openid_exists) {
            return null;
        }
        $wx_open_id = $json_obj->openid;
        $access_token = $json_obj->access_token;
        $user_info = $this->get_user_info_from_openid($access_token, $wx_open_id);
        return $user_info;
    }

    //获取用户数据
    public function get_user_info()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $sign = $post['sign'];

        if (!$this->wxinfo_model->check_user_sign($uid, $sign))
        {
            $data['code'] = 0;
            echo $this->encrypt(json_encode($data));
            die;
        }

        $data = $this->wxinfo_model->get_user_info($uid);
        echo $this->encrypt(json_encode($data));
    }

    //获取用户资产数据
    public function get_ugame_data()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $data = $this->ugame_model->get_ugame_data($uid);
        echo $this->encrypt(json_encode($data));
    }

    private function get_user_info_from_openid($access_token, $wx_open_id)
    {
        $url = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $access_token .
            '&openid=' . $wx_open_id . '&lang=zh_CN';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $output = curl_exec($ch);
        if(curl_error($ch)) {
            curl_close($ch);
            return null;
        }
        curl_close($ch);
        $json_obj = json_decode($output);
        return $json_obj;
    }

    //获取挖矿数据
    public function get_kw_list()
    {
        if (config_item('s_maintain'))
        {
            $data['error'] = "2";
            echo $this->encrypt(json_encode($data));
            die;
        }

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        if($this->is_in_user_blacklist($uid))
        {
            $data['code'] = "999";
            $data['error'] = "999";
            echo $this->encrypt(json_encode($data));
            die;
        }

        $data_res['error'] = 1;
        $producing = $this->db->query("select key_value from config where key_name = 'producing'")->result_array();
        $producing = $producing[0]['key_value'];
        if ($producing == '1')
        {
            $data_res['error'] = -1;
            echo $this->encrypt(json_encode($data_res));
            die;
        }

        $ip = $this->getIp();
        $datetime = date("Y-m-d H:i:s", time());

        $is_have = $this->db->query("select * from ip_user_list where uid = '$uid'")->result_array();
        if (empty($is_have))
        {
            $ip_user_data = array(
                'uid' => $uid,
                'ip' => $ip,
                'date'=> $datetime

            );
            $this->db->insert('ip_user_list', $ip_user_data);
        }
        else
        {
            $this->db->query("update ip_user_list set ip = '$ip', `date` = '$datetime' where uid = '$uid' ");
        }

        $miner_count = $this->db->query("select key_value from config where key_name = 'user_count'")->result_array();
        $miner_count = $miner_count[0]['key_value'];
        $miner_count2 = $this->db->query("select key_value from config where key_name = 'user_count_xpot'")->result_array();
        $miner_count2 = $miner_count2[0]['key_value'];
        $miner_count += $miner_count2;

        $next_output_time = $this->db->query("select key_value from config where key_name = 'next_output_time'")->result_array();
        $next_output_time = $next_output_time[0]['key_value'];

        $list = $this->wakuang_model->get_kw_list($uid);
        $data_res['error'] = 0;
        $data_res['miner_count'] = $miner_count;
        $data_res['next_output_time'] = $next_output_time;
        $data_res['list'] = $list;
        echo $this->encrypt(json_encode($data_res));
    }

    //领取挖到的矿
    public function get_wk()
    {
        if (config_item('s_maintain'))
        {
            $data['error'] = 2;
            $data['code'] = 2;
            echo $this->encrypt(json_encode($data));
            die;
        }

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $id = $post['id'];

        $producing = $this->db->query("select key_value from config where key_name = 'producing'")->result_array();
        $producing = $producing[0]['key_value'];

        if ($producing == '1')
        {
            $data['error'] = -1;
            $data['code'] = -1;
            echo $this->encrypt(json_encode($data));
            die;
        }

        $data['xpot'] = $this->ugame_model->get_coins_num($uid, "xpot");

        $number = $this->wakuang_model->receive_kw($uid, $id);

        $data['code'] = $number > 0 ? 1 : 0;
        $data['error'] = intval(!$data['code']);
        if ($number)
        {
            $data['xpot'] += $number;
        }

        echo $this->encrypt(json_encode($data));
    }

    //领取挖到的矿
    public function get_wk_red()
    {
        if (config_item('s_maintain'))
        {
            $data['error'] = 2;
            $data['code'] = 2;
            echo $this->encrypt(json_encode($data));
            die;
        }

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $id = $post['id'];
        $is_double = $post['is_double'];

        $producing = $this->db->query("select key_value from config where key_name = 'producing'")->result_array();
        $producing = $producing[0]['key_value'];

        if ($producing == '1')
        {
            $data['error'] = -1;
            $data['code'] = -1;
            echo $this->encrypt(json_encode($data));
            die;
        }

        $data['xpot'] = $this->ugame_model->get_coins_num($uid, "xpot");

        $number = $this->wakuang_model->receive_kw_red($uid, $id, $is_double);

        $data['code'] = $number > 0 ? 1 : 0;
        $data['error'] = intval(!$data['code']);
        if ($number)
        {
            $data['xpot'] += $number;
        }

        echo $this->encrypt(json_encode($data));
    }

    //领取人民币
    public function get_wk_cny()
    {
        if (config_item('s_maintain'))
        {
            $data['error'] = 2;
            $data['code'] = 2;
            echo $this->encrypt(json_encode($data));
            die;
        }

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $id = $post['id'];

        $producing = $this->db->query("select key_value from config where key_name = 'producing'")->result_array();
        $producing = $producing[0]['key_value'];

        if ($producing == '1')
        {
            $data['error'] = -1;
            $data['code'] = -1;
            echo $this->encrypt(json_encode($data));
            die;
        }

        $data['cny'] = $this->ugame_model->get_coins_num($uid, "cny");

        $number = $this->wakuang_model->receive_kw_cny($uid, $id);

        $data['code'] = $number > 0 ? 1 : 0;
        $data['error'] = intval(!$data['code']);
        if ($number)
        {
            $data['cny'] += $number;
        }

        echo $this->encrypt(json_encode($data));
    }


    //--------------------------- ---------------------------- ------------------------ -----------------------
    //一键领取所有矿工的贡献
    public function get_all_tax()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $data_res['code'] = 0;
        $number = $this->ugame_model->get_all_miner_tax($uid);
        if ($number > 0)
        {
            $data_res['code'] = 1;
            $data_res['number'] = $number;
            $data_res['xpot'] = $this->ugame_model->get_coins_num($uid, "xpot");
        }

        echo $this->encrypt(json_encode($data_res));
    }

    public function get_suanli()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $suanli = $this->ugame_model->get_ugame_fuli($uid);
        echo $this->encrypt(json_encode($suanli));
    }

    public function get_xpot()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $xpot_value['xpot'] = $this->ugame_model->get_coins_num($uid, "xpot");
        echo $this->encrypt(json_encode($xpot_value));
    }

    public function get_frozen_xpot()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $xpot_value['xpot'] = $this->ugame_model->get_frozen_coins_num($uid, "xpot");
        echo $this->encrypt(json_encode($xpot_value));
    }

    public function get_cny()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $cny_value['cny'] = $this->ugame_model->get_coins_num($uid, "cny");
        echo $this->encrypt(json_encode($cny_value));
    }

    //签到
    public function everyday_signin()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $data_res['code'] = 0;
        if ($this->ugame_model->signin($uid))
        {
            $data_res['code'] = 1;
        }
        echo $this->encrypt(json_encode($data_res));
    }

    //每日冒泡
    public function everyday_bubbling()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $data_res['code'] = 0;
        if ($this->ugame_model->everyday_bubbling($uid))
        {
            $data_res['code'] = 1;
        }
        echo $this->encrypt(json_encode($data_res));
    }

    //实名认证
    public function certification()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $fullname = $post['fullname'];
        $id_card = strval($post['id_card_xpot']);

        $result['error'] = 1;
        $result['code'] = $this->wxinfo_model->certification_wx($uid, $fullname, $id_card);
        if($result['code'] == 1)
        {
            $result['error'] = 0;
        }
        echo $this->encrypt(json_encode($result));
    }

    //绑定手机号
    public function binding_phone_number()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $phone_number = $post['phone_number'];
        $verification_code = strval($post['verification_code']);

        $code_info = $this->vc_model->get_phone_verification_code($phone_number);
        if ($verification_code != $code_info['code'])
        {
            // 验证码错误
            $data['code'] = "10";
            echo $this->encrypt(json_encode($data));
            die;
        }
        if (time() - strtotime($code_info['code_date']) > 180)
        {
            // 验证码超时
            $data['code'] = "11";
            echo $this->encrypt(json_encode($data));
            die;
        }

        $data['code'] = $this->wxinfo_model->binding_phone_number_wx($uid, $phone_number);
        echo $this->encrypt(json_encode($data));
    }

    //检测手机号验证码
    public function check_phone_number_verification_code()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $phone_number = $post['phone_number'];
        $verification_code = strval($post['verification_code']);


        $uid2 = $this->wxinfo_model->get_user_uid_with_phone_number($phone_number);
        if ($uid != $uid2)
        {
            // 手机号不匹配
            $data['code'] = "14";
            echo $this->encrypt(json_encode($data));
            die;
        }

        $code_info = $this->vc_model->get_phone_verification_code($phone_number);
        if ($verification_code != $code_info['code'])
        {
            // 验证码错误
            $data['code'] = "10";
            echo $this->encrypt(json_encode($data));
            die;
        }
        if (time() - strtotime($code_info['code_date']) > 180)
        {
            // 验证码超时
            $data['code'] = "11";
            echo $this->encrypt(json_encode($data));
            die;
        }

        $data['code'] = 1;
        echo $this->encrypt(json_encode($data));
    }
    //任务界面数据
    public function mission()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $result = $this->db->query("select * from task where classify = 'COMMON' or classify = 'ALL'")->result_array();

        $user_info = $this->wxinfo_model->get_user_info($uid);
        $my_share_cnt = $user_info['share_cnt'];
        $is_certification = $user_info['id_card'] ? 1 : 0;
        $is_binding_phone_number = $user_info['phone_number'] ? 1 : 0;

        $is_6_finish = 0;
        $is_7_finish = 0;
        $a_finish_newbie_task_data = $this->db->query("select * from a_finish_newbie_task where uid = '$uid'")->result_array();
        foreach ($a_finish_newbie_task_data as $k=>$v)
        {
            $task_id = $v['task_id'];
            if ($task_id == 1) $is_6_finish = 1;
            if ($task_id == 2) $is_7_finish = 1;
        }

        $array = array();
        foreach ($result as &$value)
        {
            switch ($value['type_id'])
            {
                case 1:
                    $value['status'] = 1;
                    break;
                case 2:
                    $value['share_cnt'] = $my_share_cnt;
                    if ($value['need_cnt'] <= $my_share_cnt)
                    {
                        $value['status'] = 1;
                    }
                    else
                    {
                        $value['status'] = 0;
                    }
                    break;
                case 3:
                    $value['status'] = $is_binding_phone_number;
                    break;
                case 4:
                    $value['status'] = $is_certification;
                    break;
                case 6://微信公众号
                    $value['status'] = $is_6_finish;
                    break;
                case 7://加入官方社群
                    $value['status'] = $is_7_finish;
                    break;
                default:
                    $value['status'] = 0;
                    break;
            }

            array_push($array, $value);
        }
        unset($value);
        $data['list'] = $array;
        echo $this->encrypt(json_encode($data));
    }
    //提交邀请码
    public function sub_share_code()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $share_code = $post['share_code'];

        $data['code'] = $this->ugame_model->sub_share_code($uid, $share_code);
        echo $this->encrypt(json_encode($data));
    }
    //提交礼包兑换码
    public function sub_exchange_code()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $exchange_code = $post['exchange_code'];
        $exchange_code = strtoupper($exchange_code);
        $data = $this->exchange_model->sub_exchange_code($uid, $exchange_code);
        echo $this->encrypt(json_encode($data));
    }
    //导出提现记录 excel表
    public function withdraw_excel()
    {
        $title=array(
            array('A','uid','微信openid'),
            array('B','uname','昵称'),
            array('C','address','提现地址'),
            array('D','sfz','身份证'),
            array('E','account','账号'),
            array('F','coin_name','币名'),
            array('F','nums','数量'),
            array('G','apply_time','提现时间')
        );
        //获取数据
        $data = array();
        $name='提现申请_'.date('Ymd-his',time());
        $this->outExcel($title,$data,$name);
    }
    //获取xpot收支记录
    public function get_xpot_record()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $data['list']=$this->wakuang_model->get_lingqu_record($uid);
        echo $this->encrypt(json_encode($data));
    }

    //获取用户补偿信息
    public function get_compensate()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $data['error'] = 1;
        $data['code'] = 0;
        if ($info = $this->compensate_model->get_compensate($uid))
        {
            $data['error'] = 0;
            $data['code'] = 1;
            $data['info'] = $info;
        }
        echo $this->encrypt(json_encode($data));
    }

    //领取补偿
    public function rec_compensate()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $id = $post['id'];

        $data['error'] = 1;
        $data['code'] = 0;
        if ($info = $this->compensate_model->rec_compensate($uid, $id))
        {
            $data['error'] = 0;
            $data['code'] = 1;
            $data['info'] = $info;
        }
        echo $this->encrypt(json_encode($data));
    }

    public function clear_none_compensate()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $this->compensate_model->clear_none_compensate($uid);

        $data['error'] = 0;
        $data['code'] = 1;
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

    public function banli_xinyongka()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $id = $post['id'];
        $nickname = $post['nickname'];
        $yinhang = $post['yinhang'];
        $date = strval(date("Y-m-d", time()));

        $result['code'] = 0;
        $sel = $this->db->query("SELECT * FROM $yinhang WHERE uid = '$uid'")->result_array();;
        if (!empty($sel))
        {
            echo $this->encrypt(json_encode($result));
            die;
        }

        $array = array(
            'id' => $id,
            'uid' => $uid,
            'nickname' => $nickname,
            'date' => $date
        );
        $this->db->insert($yinhang, $array);

//        $this->ugame_model->add_fuli($uid, 20);

        $result['code'] = 1;
        echo $this->encrypt(json_encode($result));
    }

    public function get_id_sign()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $id_sign = AES::getInstance()->encrypt_pass($uid);
        $result['code'] = 1;
        $result['id_sign'] = $id_sign;
        echo $this->encrypt(json_encode($result));
    }

    public function modify_user_nickname()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $nickname = $post['nickname'];
        $result['code'] = $this->wxinfo_model->modify_user_nickname($uid, $nickname);
        echo $this->encrypt(json_encode($result));
    }

    public function modify_user_headimgurl()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $headimgurl = $post['headimgurl'];
        $result['code'] = $this->wxinfo_model->modify_user_headimgurl($uid, $headimgurl);
        echo $this->encrypt(json_encode($result));
    }

    public function get_xpot_cost()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $result['code'] = 1;
        $result['xpot_cost'] = floatval($this->ugame_model->get_config('xpot_cost'));
        echo $this->encrypt(json_encode($result));
    }

    // 获取当前价格
    public function get_today_withdraw_xpot()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $result['code'] = 1;
        $result['today_withdraw_xpot'] = $this->wallet_model->get_today_withdraw_xpot($uid);
        echo $this->encrypt(json_encode($result));
    }

    // 获取价格记录
    public function get_xpot_cost_record()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start_date = $post['start_date'];
        $end_date = $post['end_date'];
        $result['code'] = 1;
        $result['list'] = $this->wallet_model->get_xpot_cost_record($start_date, $end_date);
        $xpot_cost_record_display_interval = $this->ugame_model->get_config("xpot_cost_record_display_interval");
        $xpot_cost_record_display_interval = json_decode($xpot_cost_record_display_interval, true);
        $result['xpot_cost_record_min'] = $xpot_cost_record_display_interval[0];
        $result['xpot_cost_record_max'] = $xpot_cost_record_display_interval[1];
        $result['xpot_cost_record_interval'] = $xpot_cost_record_display_interval[2];

        echo $this->encrypt(json_encode($result));
    }

    // 获取价格记录
    public function get_tjs_xpot_cost_record()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $result['code'] = 1;
        $result['list'] = json_decode($this->ugame_model->get_config('tjs_segg_cost'), true);

        echo $this->encrypt(json_encode($result));
    }

    public function make_withdraw_cny_flow_id()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $cny = $post['cny'];
        $sign = $post['sign'];
        $fullname = $post['fullname'];
        $id_card = $post['id_card'];
        if (!$this->wxinfo_model->check_id_card($uid, $fullname, $id_card))
        {
            $data['code'] = 16;
            echo $this->encrypt(json_encode($data));
            die;
        }
        $result = $this->wallet_model->make_withdraw_cny_flow_id($uid, $cny, $sign);
        echo $this->encrypt(json_encode($result));
    }

    public function make_withdraw_xpot_flow_id()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $xpot = $post['xpot'];
        $sign = $post['sign'];
        $fullname = $post['fullname'];
        $id_card = $post['id_card'];
        if (!$this->wxinfo_model->check_id_card($uid, $fullname, $id_card))
        {
            $data['code'] = 16;
            echo $this->encrypt(json_encode($data));
            die;
        }

        $result = $this->wallet_model->make_withdraw_xpot_flow_id($uid, $xpot, $sign);
        echo $this->encrypt(json_encode($result));
    }

    //提现列表
    public function get_cny_withdraw_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['cny_withdraw_list'] = $this->wallet_model->get_cny_withdraw_list($uid, $start, $count);
        echo $this->encrypt(json_encode($result));
    }

    //提现列表
    public function get_xpot_withdraw_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['xpot_withdraw_list'] = $this->wallet_model->get_xpot_withdraw_list($uid, $start, $count);
        echo $this->encrypt(json_encode($result));
    }

    //提现公告信息
    public function get_withdraw_hrl_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $result['code'] = 1;
        $list = $this->wallet_model->get_withdraw_hrl_list($uid);
        $result = array_merge($result, $list);
        echo $this->encrypt(json_encode($result));
    }

    // 获取订单详情
    public function get_withdraw_flow_id()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $flow_id = $post['flow_id'];

        $result['code'] = 1;
        $data = $this->wallet_model->get_withdraw_flow_id($flow_id);
        if (empty($data))
        {
            $result['code'] = 0;
        }
        else
        {
            $result['info'] = $data;
        }
    }

    public function task_q_cny()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $code = $this->activity_model->task_q_cny($uid);
        $result['code'] = $code ? 1 : 0;
        echo $this->encrypt(json_encode($result));
    }

    public function get_ddz_download_url()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $url = AES::getInstance()->encrypt_pass("key=".$uid, "ddz1234567890ddz");
        $url = "http://wangzha.e3ev.cn/m/xpot.html?data=".$url;
        $result['url'] = $url;
        echo $this->encrypt(json_encode($result));
    }

    // 下载成功回调
    public function callback_app_is_install()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $app_name = $post['app_name'];
        $os = $post['os'];

        $this->activity_model->callback_app_is_install($uid, $app_name, $os);
        $result['code'] = 1;
        echo $this->encrypt(json_encode($result));
    }

    // 唤醒回调
    public function callback_app_is_awaken()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $app_name = $post['app_name'];
        $os = $post['os'];

        $result['code'] = $this->activity_model->callback_app_is_awaken($uid, $app_name, $os);
        echo $this->encrypt(json_encode($result));
    }

    // 下载成功回调 // 即将删除
    public function callback_app_is_install_not_hb()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $app_name = $post['app_name'];
        $os = $post['os'];

        $this->activity_model->callback_app_is_install($uid, $app_name, $os);
        $result['code'] = 1;
        echo $this->encrypt(json_encode($result));
    }

    // 唤醒回调 // 即将删除
    public function callback_app_is_awaken_not_hb()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $app_name = $post['app_name'];
        $os = $post['os'];

        $result['code'] = $this->activity_model->callback_app_is_awaken($uid, $app_name, $os);
        echo $this->encrypt(json_encode($result));
    }

    // iOS对接方式，下载app订单生成
    public function make_download_app_third_party()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $app_name = $post['app_name'];
        $sign = $post['sign'];
        $result['code'] = $this->activity_model->make_download_app_third_party($uid, $app_name, $sign);
        echo $this->encrypt(json_encode($result));
    }

    // iOS对接方式，回调api，提供给三方的
    public function callback_app_is_install_third_party()
    {
        $get = $this->input->get();
        $uid = $get['uid'];
        $app_name = $get['app_name'];

        $this->activity_model->callback_app_is_install_third_party($uid, $app_name);
        $result['code'] = 1;
        echo json_encode($result);
    }

    //领取红包
    public function get_wk_hb()
    {
        if (config_item('s_maintain'))
        {
            $data['error'] = "2";
            $data['code'] = 2;
            echo $this->encrypt(json_encode($data));
            die;
        }

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $id = $post['id'];

        $producing = $this->db->query("select key_value from config where key_name = 'producing'")->result_array();
        $producing = $producing[0]['key_value'];

        if ($producing == '1')
        {
            $data['code'] = -1;
            echo $this->encrypt(json_encode($data));
            die;
        }

        $data['cny'] = $this->ugame_model->get_coins_num($uid, "cny");

        $number = $this->wakuang_model->receive_kw_hb($uid, $id);

        $data['code'] = $number > 0 ? 1 : 0;
        if ($number)
        {
            $data['cny'] += $number;
        }

        echo $this->encrypt(json_encode($data));
    }

    // 获取app任务列表
    public function get_butt_app_info_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $app_data = $this->activity_model->get_a_app_download_info();
        $user_finish_downloa_info = $this->activity_model->get_a_finish_download($uid);

        foreach ($app_data as $k=>&$v)
        {
            $v['is_install'] = 0;
            $v['awaken_count'] = 0;
            $v['is_today_awaken'] = 0;
        }
        unset($v);

        $index = 0;
        foreach ($app_data as $k=>&$v)
        {
            $app_name = $v['app_name'];
            foreach ($user_finish_downloa_info as $k2=>$v2)
            {
                if ($app_name == $v2['app_name'])
                {
                    $v['is_install'] = 1;
                    if (isset($v2['awaken_count'])) $v['awaken_count'] = $v2['awaken_count'];
                    if (isset($v2['is_today_awaken'])) $v['is_today_awaken'] = $v2['is_today_awaken'];
                }
            }
            $index += 1;
        }
        unset($v);

        $result['code'] = 1;
        $result['list'] = $app_data;
        echo $this->encrypt(json_encode($result));
    }

    // 获取跑马灯数据
    public function get_hrl_message()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $result['code'] = 1;
        $result['list'] = $this->activity_model->db_r()->query("select * from hrl_message where state = 1 order by id desc")->result_array();
        echo $this->encrypt(json_encode($result));
    }

    // 每日分享
    public function everyday_share()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $result['code'] = $this->ugame_model->everyday_share($uid);
        echo $this->encrypt(json_encode($result));
    }

    // APPSTROE专用
    public function exchange_chicken()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $eggs = $post['eggs'];

        if ($uid != 'ovBB91WVgWHGN_QrrFVt9g0rBN18')
        {
            $result['code'] = 0;
            echo $this->encrypt(json_encode($result));
            die;
        }

        $result['code'] = $this->ugame_model->exchange_chicken($uid, $eggs);
        echo $this->encrypt(json_encode($result));
    }

    public function appstore_buy_hen()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $hen = $post['hen'];

        if ($uid != 'ovBB91WVgWHGN_QrrFVt9g0rBN18')
        {
            $result['code'] = 0;
            echo $this->encrypt(json_encode($result));
            die;
        }

        $result['code'] = $this->ugame_model->appstore_buy_hen($uid, $hen);
        echo $this->encrypt(json_encode($result));

    }

    public function appstore_hen_ranging_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $result['code'] = 1;
        $result['list'] = $this->ugame_model->appstore_hen_ranging_list();
        echo $this->encrypt(json_encode($result));
    }

    public function get_qz_data()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $info = $this->wxinfo_model->get_wx_info($uid);

        $array = array(
            'uid' => $info['openid'],
            'nickname' => $info['nickname'],
            'sex' => $info['sex'],
            'headimgurl' => $info['headimgurl'],
            'sign' => md5(time()),
        );

        $data = json_encode($array);
        $data = AES::getInstance()->encrypt_pass($data);

        $result['code'] = 1;
        $result['url'] = 'http://www.kl.rumenglin.com?data='.$data;
        echo $this->encrypt(json_encode($result));
    }

    public function get_market_data()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $array['uid'] = $uid;
        $array['timestamp'] = time() + 10;
        $array['sign'] = $this->get_sign($array);

        $str = '';
        foreach ($array as $k=>$v)
        {
            $str .= $k.'='.$v.'&';
        }
        $str = rtrim($str, '&');

        $result['code'] = 1;
        $result['url'] = 'http://a.newad.henkuai.com/mobile/chicken_auth?'.$str;
        echo $this->encrypt(json_encode($result));
    }

    public function get_shop_data()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $array['uid'] = $uid;
        $array['eggs_balance'] = $this->ugame_model->get_coins_num($uid, "xpot");
        $array['timestamp'] = time();
        $array['sign'] = $this->get_sign($array);

        $str = '';
        foreach ($array as $k=>$v)
        {
            $str .= $k.'='.$v.'&';
        }
        $str = rtrim($str, '&');

        $result['code'] = 1;
        $result['url'] = 'http://eggs.jutouwang.cn?'.$str;
        echo $this->encrypt(json_encode($result));

//        echo json_encode($result);
    }

    public function get_jfcz_data()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $array = array(
            'uid' => $uid,
            'timestamp' => time(),
        );

        $data = json_encode($array);
        $data = AES::getInstance()->encrypt_pass($data, "ddz1234567890ddz");

        $result['code'] = 1;
        $result['url'] = 'http://miningzcx.qiaochucn.com/mobile.php/index/index?data='.$data;
        echo $this->encrypt(json_encode($result));
    }

//    public function notice_add_cny()
//    {
//        $json_data = $this->input->post();
//        $uid = $json_data['uid'];
//        $cny = $json_data['cny'];
//        $timestamp = $json_data['timestamp'];
//        $sign = $json_data['sign'];
//
//        if ($timestamp < time())
//        {
//            $data['error'] = 1;
//            echo json_encode($data);
//            die;
//        }
//
//        $array = array(
//            'uid' => $uid,
//            'cny' => $cny,
//            'timestamp' => $timestamp
//        );
//        $array['sign'] = $this->get_sign($array);
//        if ($sign != $array['sign'])
//        {
//            $data['error'] = 1;
//            echo json_encode($data);
//            die;
//        }
//
//
//        // 加钱
//        $this->ugame_model->add_coins($uid, "cny", $cny);
//
//        $data['error'] = 0;
//        echo json_encode($data);
//        die;
//    }

    public function notice_get_cny()
    {
        $json_data = $this->input->post();
        $uid = $json_data['uid'];
        $sign = $json_data['sign'];

        $array = array(
            'uid' => $uid
        );
        $array['sign'] = $this->get_sign($array);
        if ($sign != $array['sign'])
        {
            $data['error'] = 1;
            echo json_encode($data);
            die;
        }

        $result['error'] = 0;
        $result['cny'] = $this->ugame_model->get_coins_num($uid, "cny");
        echo json_encode($result);
        die;
    }

    // 获取可偷鸡蛋的好友列表
    public function get_can_be_stolen_kw_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $other_uid = $post['other_uid'];

//        $result['code'] = $this->ugame_model->verifying_friends($uid, $other_uid);
        $result['code'] = 1;
        if ($result['code'] == 1)
        {
            $result['list'] = $this->wakuang_model->get_can_be_stolen_kw_list($other_uid);
        }
        echo $this->encrypt(json_encode($result));
    }

    // 偷鸡蛋
    public function steal_kw()
    {
        if (config_item('s_maintain'))
        {
            $data['error'] = 2;
            $data['code'] = 2;
            echo $this->encrypt(json_encode($data));
            die;
        }

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $other_uid = $post['other_uid'];
        $id = $post['id'];
        $friend_count = isset($post['friend_count']) ? $post['friend_count'] : 1;


        $producing = $this->db->query("select key_value from config where key_name = 'producing'")->result_array();
        $producing = $producing[0]['key_value'];

        if ($producing == '1')
        {
            $result['code'] = -1;
            echo $this->encrypt(json_encode($result));
            die;
        }

//        $result['code'] = $this->ugame_model->verifying_friends($uid, $other_uid);
//        if ($result['code'] == 0)
//        {
//            echo $this->encrypt(json_encode($result));
//            die;
//        }

        $result['code'] = 1;
        $stole = $this->wakuang_model->steal_kw($uid, $other_uid, $id, $friend_count);
        if ($stole == 0)
        {
            $result['code'] = 0;
            echo $this->encrypt(json_encode($result));
            die;
        }
        $result['stole'] = $stole;

        echo $this->encrypt(json_encode($result));
    }

    // 获取用户员工以及鸡场信息
    public function get_user_staff()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];


        $producing = $this->db->query("select key_value from config where key_name = 'ereryday_data_processing'")->result_array();
        $producing = $producing[0]['key_value'];
        if ($producing == '1')
        {
            $data_res['code'] = 101;
            echo $this->encrypt(json_encode($data_res));
            die;
        }


        $result['code'] = 1;
        $result['list'] = $this->ugame_model->get_user_staff($uid);
        $result['active_staff_count'] = $this->ugame_model->get_user_active_staff_count($uid);
        echo $this->encrypt(json_encode($result));
    }


    // 即将删除…
    //获取其他用户数据
    public function get_other_user_info()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $other_uid = $post['other_uid'];
        $data = $this->friend_model->get_other_user_info($uid, $other_uid);
        echo $this->encrypt(json_encode($data));
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

    public function test_encrypt()
    {
        $data = $this->encrypt("{\"uid\":\"oKIlh0d3KSw7CfmWSDaIO7bWeKiw\",\"content\":\"牛啊🐂\"}");
        echo "<pre>";
        echo $data;
        echo "</pre>";
        $data = $this->decrypt($data);
        echo "<pre>";
        echo $data;
        echo "</pre>";
    }

    // 当前打赏信息
    public function get_qds_info()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $result['code'] = 1;
        $result = $this->message_model->is_qds_progressive($uid, $result);
        $result['qds_count'] = $this->message_model->get_qds_count($uid);
        echo $this->encrypt(json_encode($result));
    }

    // 打赏列表
    public function get_qds_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['list'] = $this->message_model->get_qds_list($uid, $start, $count);
        echo $this->encrypt(json_encode($result));
    }

    // 创建求打赏主页
    public function qds_create()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $nickname = $post['nickname'];
        $headimgurl = $post['headimgurl'];
        $text = $post['text'];

        $result = $this->message_model->qds_create($uid, $nickname, $headimgurl, $text);
        echo $this->encrypt(json_encode($result));
    }

    // 打赏排行榜
    public function get_qds_ranking_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['list'] = $this->message_model->get_qds_ranking_list($uid, $start, $count);
        echo $this->encrypt(json_encode($result));
    }

    // 求打赏回调
    public function notice_qds()
    {
        $json_data = json_decode(file_get_contents('php://input'), true);
        $appkey = $json_data['appkey'];
        $notify_type = $json_data['notify_type'];
        $page_serial = $json_data['page_serial'];
        if ($notify_type == 2)
        {
            $comment_id = 0;
            $user_name = '';
            $sex = 0;
            $avatar = '';
            $content = '';
            $fee = 0;
            $create_time = '';
        }
        else
        {
            $comment_id = $json_data['comment_id'];
            $user_name = $json_data['user_name'];
            $sex = $json_data['sex'];
            $avatar = $json_data['avatar'];
            $content = $json_data['content'];
            $fee = $json_data['fee'];
            $create_time = $json_data['create_time'];
        }
        $sign = $json_data['sign'];
        $result['error'] = $this->message_model->notice_qds($appkey, $notify_type, $page_serial, $comment_id, $user_name, $sex, $avatar, $content, $fee, $create_time, $sign);
        echo json_encode($result);
    }

    //提现
    public function wallet_segg_withdraw()
    {
        $post = $this->input->post();
        $url = "http://eggswallet.qiaochucn.com/index.php/index/index/withdraw";

        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

        $output = curl_exec($ch);
        curl_close($ch);
        echo $output;
    }

    //发消息
    public function send_compensate()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $other_uid = $post['other_uid'];
        $text = $post['text'];

        $nickname = $this->wxinfo_model->get_nickname_info($uid);

        $result['code'] = $this->compensate_model->inset_compensate_none($other_uid, "【".$nickname."】的消息", $text, $uid);
        echo $this->encrypt(json_encode($result));
    }

    // 排行榜
    public function get_zhandou_ranking()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $redis = $this->redis_model->get_redis();

        echo $this->encrypt($redis->get("zhandou_ranking"));
    }

    // 排行榜
    public function get_gujv_ranking()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $redis = $this->redis_model->get_redis();

        echo $this->encrypt($redis->get("gujv_ranking"));
    }

    // 排行榜
    public function get_luhua_ranking()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $redis = $this->redis_model->get_redis();

        echo $this->encrypt($redis->get("luhua_ranking"));
    }

    // 排行榜
    public function get_da_ranking()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $redis = $this->redis_model->get_redis();

        echo $this->encrypt($redis->get("da_ranking"));
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

    public function get_short_message_sign($data)
    {
        $str = '';
        $index = 0;
        foreach ($data as $k=>$v)
        {
            $str .= $k.'='.$v.'&';
            $index += 1;
        }
        $str = substr($str, 0, -1);
        return bin2hex(hash('sha256', $str, true));
    }

    private static $key       = 'IwOyofAfmRXHMkYj0PCZqCKPZKS0KLrF';
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
        $str .= 'key='.(Main::$key);
        $str = strtoupper(md5($str));
        return $str;
    }
}