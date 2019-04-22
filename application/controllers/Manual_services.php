<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2018/6/11
 * Time: 下午2:45
 */

class Manual_services extends CI_Controller
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
        $this->load->model('by_model');

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

    // 人工补偿
    function add_compensate()
    {
        $this->compensate_model->inset_compensate_xpot('ovBB91Vc1jYxZlMoMPyC83xpym2w', "标题", "内容", 10);
        echo 'OK';
    }

    /*******生成序列号***BEGIN*****************************************************/

    function add_exchange_code()
    {
        $number = 10000;
        $code_head = "DDZ";
        $code_list = array();

        $suanli = 2;
        $xpot = 0;

        for ($i=0; $i<$number; $i++)
        {
            array_push($code_list, $this->get_code(12, $code_head));
        }

        $sql = "INSERT INTO exchange(`batch`,`code`,`fuli`,`xpot`) VALUES ";
        foreach ($code_list as $k => $v)
        {
            echo "<pre>";
            echo $v;
            echo "</pre>";

            $sql .= '(' . "'".$code_head."'" . ",". "'".$v."'" . ',' . $suanli . ',' . $xpot . '),';
        }
        $sql = rtrim($sql, ',');
        $this->db->query($sql);
    }

    //生成随机序列号
    function get_code($code_length, $code_head)
    {
        mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
        $code = strtoupper(md5(uniqid(rand(), true)));
        $code = $code_head.substr($code, 0, $code_length);
        return $code;
    }
    /*******生成序列号***END*****************************************************/


    function msectime() {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }

    public function task_connect_redis()
    {
        $redis = new \Redis();
        $redis->connect('r-2zelh1bso8osjkebfa.redis.rds.aliyuncs.com', '6379');
        $redis->auth('2013XinNian#*');
        echo "Server is running: " . $redis->ping();
        $redis->set( "testKey" , "Hello Redis"); //设置测试key
        echo $redis->get("testKey");//输出value
    }

    // 同意IP大于一定数量的用户自动添加到黑名单
    public function check_ip_add_ip_backlist()
    {
        $data = $this->db->query("SELECT * FROM ip_user_list where `ip` in (SELECT `ip` FROM ip_user_list  GROUP BY `ip` HAVING COUNT(`ip`) > 40)")->result_array();

        $dict = array();
        $index = 0;
        foreach ($data as $k=>$v)
        {
            $uid = $v['uid'];
            $ip = $v['ip'];
            $date = $v['date'];

            if (isset($dict[$ip]))
            {
                $dict[$ip] += 1;
            }
            else
            {
                $dict[$ip] = 1;
            }

            $index += 1;
        }

        $index = 0;
        foreach ($data as $k=>$v)
        {
            $uid = $v['uid'];
            $ip = $v['ip'];
            $date = $v['date'];

            $data2 = $this->db->query("SELECT * FROM user_blacklist WHERE uid = '$uid'")->result_array();

            if (!empty($data2))
                continue;

            $info = "有".$dict[$ip]."用户"."在".$date."使用ip:".$ip."刷矿";
            $datas = array(
                'uid' => $uid,
                'info' => $info,
                'level' => 9);
            $this->db->insert('user_blacklist', $datas);

            $index += 1;
        }
        echo $index;
    }

    public function add_ip_backlist()
    {
        $data = $this->db->query("select * from wx_info  where length(nickname) = 13 and SUBSTRING(nickname, -1) = 9")->result_array();
        $index = 0;
        foreach ($data as $key=>$value)
        {
            $uid = $value['openid'];
            $nickname = $value['nickname'];


            $data2 = $this->db->query("SELECT * FROM user_blacklist WHERE uid = '$uid'")->result_array();

            if (!empty($data2))
                continue;

            $datas = array(
                'uid' => $uid,
                'info' => '【'.$nickname.'】',
                'level' => 9);
            $this->db->insert('user_blacklist', $datas);

            echo $uid.$nickname.'    ';
            $index += 1;
        }
    }


    public function task_suanli()
    {
        $dict["招商"] = "xyk_zhaoshang";
        $dict["交通"] = "xyk_jiaotong";
        $dict["民生"] = "xyk_minsheng";
        $dict["浦发"] = "xyk_pufa";
        $dict["兴业"] = "xyk_xingye";

        $index = 0;
        foreach ($dict as $key=>$value)
        {
            $title = $key;
            $sql_name = $value;

            $data = $this->db->query("SELECT * FROM ".$sql_name." WHERE state = 0")->result_array();

            foreach ($data as $k=>$v)
            {
                $uid = $v['uid'];
                $date = $v['date'];

                $text = '您在'.$date.'申请'.$title.'银行信用卡，特在此发放5只母鸡任务奖励，请查收！';
                $data = array(
                    'uid' => $uid,
                    'title' => $title.'银行信用卡获取母鸡任务',
                    'text' => $text,
                    'fuli' => 5,
                    'send_date' => date('Y-m-d H:i:s', time())
                );
                $this->db->insert('compensate', $data);
            }
            $this->db->query("UPDATE ".$sql_name." SET state = 1 WHERE state = 0");

            $index += 1;

            echo "<pre>";
            echo $title."开奖 \n";
            echo "</pre>";
        }
    }

    public function recovery_hen()
    {
        $array = array(
            11626
        );

        $index = 0;
        foreach ($array as $value)
        {
            $uid = $this->db->query("SELECT * FROM wx_info WHERE id = $value")->result_array();
            $uid = $uid[0]['openid'];
            $data = $this->db->query("UPDATE ugame SET fuli = fuli - 5 WHERE uid = '$uid'");

            $index += 1;

            echo "<pre>";
            echo $uid."回收".$data."\n";
            echo "</pre>";
        }
    }


    /**
     * @param 检验ugame与wx_info 用户数据是否一致
     */
    /*
    private function delByValue($arr, $value){
        $key = array_search($value,$arr);
        if(isset($key)){
            unset($arr[$key]);
        }
        return $arr;
    }

    public function get_xxxx()
    {
        $arr1 = $this->db->query("SELECT openid FROM wx_info")->result_array();
        $arr2 = $this->db->query("SELECT uid FROM ugame")->result_array();

        $vec = array();
        $index = 0;
        foreach ($arr1 as $k=>$v)
        {
            $vec[$index] = $v['openid'];
            $index++;
        }

        foreach ($arr2 as $k=>$value2)
        {
            foreach ($arr1 as $k2=>$value)
            {
                if ($value['openid'] == $value2['uid'])
                {
                    $vec = $this->delByValue($vec, $value['openid']);
                    break 1;
                }
            }
        }

        echo json_encode($vec);
    }
    */

    // api自测
    public function test_post()
    {
//        $url = "http://eggswallet.qiaochucn.com/index.php/index/index/open_the_wallet";
//        $url = "http://eggs.my.com/index.php/Background_services/insert_compensate";
//        $url = "http://eggs.my.com/index.php/Ad_system/make_ad_info";
        $url = "http://eggscommunity.qiaochucn.com/Index/egg/receive_red_packet";

//        $url = "http://eggswallet.qiaochucn.com/index.php/index/index/withdraw";

        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, 1);

        $post_data = array(
            'uid' => 'gm',
            "ver_code" => '3139361233'
        );
//        $post_data = AES::getInstance()->encrypt_pass(json_encode($post_data), "ddz1234567890ddz");
        $post_data = $this->encrypt(json_encode($post_data));
//
//        $post_data = 'YWwQU5UI0qaCry98gnAPYsHoHSxIGpOIydIX9nIqVv2C8uMJzDBjuT3DaIBbEEbQpBrUeUo0l4tz%0D%0ACP8CUbu31ogTcGKMX8oT9V42G%2FxwFZrbMBu8Xo5dbk2UhA97QnWGptf%2FP3J9RQlG5ac8cW1qeg%3D%3D';

//        $post_data = $this->encrypt('{"uid":"ovBB91VpyZ2eFqEBKD7fULLjNE04","nickname":"🎈💘 小幸运💘 🎈","headimgurl":"http://thirdwx.qlogo.cn/mmopen/vi_32/DYAIOgq83epIjbteOspRzrlVR6VKDd2DAe8OZFr6bj0cqzKDVFbyBo1s6DTCDoOs3Frxv4bGjic651Ziay1rztHA/132","title":"养鸡大亨","content0":"养鸡大亨","open_url":"http://baidu.com","content1":"","image_url1":"","content2":"","image_url2":"","reward_coin_total":200,"quota_total":"1000000","info_title1":"","info_title2":""}');
        $data = array(
            "data" => $post_data
        );
//        $data = 'TUFNQU1BTXNkbElvYlpRa1ZCV0pSRlF0TlpUeFRVTklJNVl0WTFJbzdDNzY3QzdLSVdqV3VpazJ2Mm1Eb3p2Z0xKWkZhMWRKSW9hUmNvTFJhSmRnY3haOFk0YjFjVkxaWE1MUlFsWkVNVlNwZFZjQmVKVklWdFpKUVVUcGNaYUJjcFJaWWxiRk5SUVJUTVJKZFJScFlZTXBZa2NwU0VNTUl3ZGxiVU9MaHZ1SHBmdWdMSmI1WjVNSUlXdW1vV3BTcUlJOVo1ZEpJb2FSY29MSUlOYlJiUUlvSXdhMVpWZEpNSUlJSU5iUmJRSW9Jd2ExWlZkSk1JSUlJSmRGWjlibFhSZEZJb01BSUZiUlhSZEZJb01BTUFNSUlsWjlkbGJVSW9Jd2E1YjlhUlpJT0lmPV9Ed0R3RHdIaVdramkzQ2p4bjVqbG5Ga0UyVkVxa3dDc21wMnVXbGppYThiSWE5YllPdys1Ty9PZ2V5Q3RMdG9pQ29Xa1duWHNqaUgwRHYzb1hrM3VXdjJ1MnZXdkd1M3B6eTBaVVAzNDJ3V2lHUDNTbnNsMmtFREVXNDFHamlqalhMRkduQ3p6a1UwdjN6bjRqaTJwejFWcFh4bjBFdlR5aWlHMEdpaWxicEtsS2txaUNqMjBXMEM2dUYrNGVrKzZDc213V2ZYc2ppSDBEdnlzbXZubG54amlpaVdoMmZYc1M2aXNtdm5sbnlqaWlpV2gyZlhzaTZpc25sMnlGajJ1M3ZHc2p5RHNuMTNoM3ZHc2ppVHdEd0NzbXVtZkcwR3hqaWlpV20xMFhzVGlpaVE9';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $output = curl_exec($ch);
        curl_close($ch);
        echo $output;
        echo $this->decrypt($output);
    }

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
        $str .= 'key='.'IwOyofAfmRXHMkYj0PCZqCKPZKS0KLrF';
        $str = strtoupper(md5($str));
        return $str;
    }

    // 对接钱包测试
    public function test_post2()
    {
        $url = "http://eggs.my.com/index.php/Background_services/insert_compensate";
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, 1);

//        $post_data = array(
//            "app_id" => 'app_wallet',
//            "tx_hash" => 'cb9e184a7cc77428b7534dbc0b3cbdf248d02af34c07dc206337c137fe038f3b[0]',
//            "token_symbol" => 'xpot',
//            "balance" => '999.00000',
//            "address" => 'ZCBUwP2V6CDsfZUeBbqEyypSrKjMaGWqnbrK',
//            "notify_type" => 4
//        );
//        echo $this->get_sign($post_data);
//        die;
//        $post_data = array(
//            'app_id' => 'app_wallet',
//            'token_symbol' => 'xpot',
//            'address' => 'ZCBNt6F522zxQxA9cLbq5k41xvjsMii9795H',
//            'balance' => 1,
//            'out_serial' => "123456789021",
//            'nonce' => 'ibuaiVcKdpRxkhJA'
//        );
//        $post_data['sign'] = $this->get_sign($post_data);
//        $post_data = json_encode($post_data);
//        $post_data = $this->encrypt(json_encode($post_data));
//        $data = array(
//            "data" => $post_data
//        );
        $post_data = 'YWwQU5UI0qaCry98gnAPYsHoHSxIGpOIydIX9nIqVv2C8uMJzDBjuT3DaIBbEEbQpBrUeUo0l4tz%0D%0ACP8CUbu31ogTcGKMX8oT9V42G%2FxwFZrbMBu8Xo5dbk2UhA97QnWGptf%2FP3J9RQlG5ac8cW1qeg%3D%3D';
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json; charset=utf-8",
                "Content-Length: " . strlen($post_data))
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        curl_close($ch);
        echo ($output);
    }

    public function test_post3()
    {
        $url = "https://www.tokeneco.co/fe-ex-api/common/public_info_v4";
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, 1);

        $post_data = '';
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json; charset=utf-8",
                "Content-Length: " . strlen($post_data))
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($output, true);
        $usd_cny = $json['data']['market']['rate']['zh_CN']['USDT'];

        $result['usd_cny'] = json_encode($usd_cny);
        echo $result['usd_cny'];
    }


    // 融云推送测试
    public function test_push()
    {
        $data = array(
            'userId' => 'gm'
        );

        $post_data = '{"platform":["ios","android"],"audience":{"is_to_all":true},"notification":{"alert":"养鸡大亨周末活动火爆来袭，下载任务和跳蚤市场现金多多，快来领钱！"}}';
//            $index = 0;
//            foreach ($data as $k=>$v)
//            {
//                $post_data = $post_data.$k.'='.$v.'&';
//                $index += 1;
//            }

        // 重置随机数种子。
        srand((double)microtime()*1000000);

        $appKey = '4z3hlwrv4o8wt';
        $appSecret = '9SjU6awlMM';
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
            'Content-Type: application/json'
        );

        $url = "https://api.cn.ronghub.com/push.json";
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

        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        // 运行curl
        $output = curl_exec($ch);
        // 关闭URL请求
        curl_close($ch);

        echo $output;

//        $output = json_decode($output, true);
//
//        if ($output['code'] == 200)
//        {
//
//        }
    }

    // 获取融云token
    public function test_get_token()
    {
        $data = array(
            'userId' => 'oKIlh0RPkUUcclgD6NH6A5Unho4Y',
            'name' => '',
            'portraitUri' => ''
        );

        $uid = $data['userId'];

        $info = $this->db->query("select * from rongyun_info where uid = '$uid'")->result_array();
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

            $appKey = '4z3hlwrv4o8wt';
            $appSecret = '9SjU6awlMM';
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

            echo $output;

            $output = json_decode($output, true);

            if ($output['code'] == 200 && $uid == $output['userId'])
            {
                $token = $output['token'];
                $insert_data = array(
                    'uid' => $uid,
                    'token' => $token
                );
                $this->db->insert('rongyun_info', $insert_data);
            }
        }
    }

    public function set_xxx()
    {
        $min = 720000;
        $max = 800000;
        for ($i=0; $i<724; $i += 1)
        {
            $all_infos = $this->db->query("select b.* from c_h_staff a left join c_h_staff b on b.uid = a.owner_uid where a.id >= $min and a.id < $max")->result_array();
            $index = 0;
            foreach ($all_infos as $k=>$v)
            {
                $id = $v['id'];
                $owner_uid = $v['uid'];
                $owner2_uid = $v['owner_uid'];
                $owner3_uid = $v['owner2_uid'];
                $this->db->query("UPDATE c_h_staff SET owner3_uid = '$owner3_uid', owner2_uid = '$owner2_uid' WHERE owner_uid = '$owner_uid'");
                $index += 1;
            }
            $min += 1000;
            $max += 1000;
            echo $i.'          ok';
        }


    }

    public function check_tixian()
    {
        $all_miner = $this->db->query("SELECT * FROM wallet_segg_recharge_withdraw WHERE number < 10")->result_array();

        $sql = "INSERT ignore INTO user_blacklist(`uid`,`info`,`level`) VALUES ";
        foreach ($all_miner as $k => $item) {

            $uid = $item['uid'];

            $sql .= '(' . "'" . $uid . "'" . ',' . "'" . "大羊毛党" . "'" . ',' . 9 . '),';

            echo $uid."     ";
        }
        $sql = rtrim($sql, ',');
        $this->db->query($sql);

        echo '          ok';
    }

    /******发送测试短信******************************************************/

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

    public function test_send_message()
    {
        $code = '测试';

        $nationcode = '886';
        $mobile = '920252393';
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

        $message = 'your verification code is '.$code.'(valid for 1 minutes). For account safety, don\'t forward the code to others.';

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
        echo ($output);
    }
    /******发送测试短信******************************************************/

    public function task_total_sell_xpot()
    {
        $data = $this->db->query("SELECT uid, SUM(xpot) AS total_sell_xpot FROM withdraw_xpot GROUP BY uid")->result_array();
        $dict = array();
        $index = 0;
        foreach ($data as $k=>$v)
        {
            $uid = $v['uid'];
            $total_sell_xpot = $v['total_sell_xpot'];
            $this->db->query("UPDATE ugame SET total_sell_xpot = $total_sell_xpot WHERE uid = '$uid'");
            echo "<pre>";
            echo $uid.' => '.$total_sell_xpot;
            echo "</pre>";
            $index += 1;
        }
        echo "<pre>";
        echo 'task_total_sell_xpot FINISH!!!';
        echo "</pre>";
    }

    public function test_delete_list_3()
    {
        $list = array();

        for ($i=1; $i<=500; $i+=1)
        {
            $list[$i] = $i;
        }

        while (1)
        {
            if (count($list) == 1)
                break;
            $this->delete_3($list);
        }
        echo $list[0];
    }

    public function delete_3(&$list)
    {
        $index = 0;
        foreach ($list as $k=>$v)
        {
            $index += 1;
            if ($index == 3)
            {
                $list[$k] = 0;
                $index = 0;
            }
        }
        while (1)
        {
            $key = array_search(0 ,$list, 1);
//            echo "<pre>";
//            echo $key;
//            echo "</pre>";
            if ($key == null)
                break;
            unset($list[$key]);
        }

    }

    public function get_by_token_info()
    {
        $get = $this->input->get();
        $pageNo = isset($get['pageNo']) ? $get['pageNo'] : 0;
        echo $this->by_model->get_token_info($pageNo);
    }

    public function test_xxxxxx()
    {
        $c_fowner_dict = array();
//        $all_miner = $this->db->query("SELECT * FROM ip_user_list WHERE ip = '139.170.131.132'")->result_array();
        $all_miner = $this->db->query("SELECT * FROM ugame WHERE total_sell_xpot > 2000")->result_array();

        foreach ($all_miner as $k=>$v)
        {
            $uid = $v['uid'];
            $c_fowner_dict[$uid] = 0;

            $this->db->query("insert ignore user_blacklist(`uid`) value('$uid')");

//            insert('user_blacklist', array(
//                'uid' => $uid
//            ));
            echo $uid.'    ';
        }

    }

    public function sssssssssssssss()
    {
        $data = $this->db->query("select a.uid, a.dy_fuli as fuli_1, u.dy_fuli, a.date as fuli_2 from a_game_qds_progressive a left join ugame u on u.uid = a.uid where a.dy_fuli > 0 and u.dy_fuli = 0")->result_array();

        $dict = array();
        $index = 0;
        foreach ($data as $k=>$v)
        {
            $uid = $v['uid'];
            $fuli_1 = $v['fuli_1'];

            $array = array(
                'uid' => $uid,
                'dy_fuli' => $fuli_1,
                'expiration_date' => '2019-04-16'
            );

            $this->db->insert('term_of_validity_dy_fuli', $array);
            $this->db->query("update ugame set dy_fuli = $fuli_1 where uid = '$uid'");
            echo "<pre>";
            echo $uid.': '.$fuli_1;
            echo "</pre>";
            $index += 1;
        }
    }

    public function eeeeeeeeee()
    {
        $uid = 'phone_13543276114';
        $title = '注册 下载APP  实名认证送500豆豆';
        $feed = 4;
//        $this->compensate_model->inset_compensate_feed($uid, "广告系统", "由于您在完成广告【.$title.】，特发放".$feed."饲料给您，请查收…", $feed);
        echo 'PK';
    }

    public function test_aes()
    {
        $text = 'TUFNQU1BTXNkbElvYlpRa1ZrT2xUcFdVYTFiVU1GVklJNWJKY0lJRUxKYmxYNWJVT0pjOUl3WVJjVmNJSUFlWk1KTVlOTU5RWU1ZSU1NTUlZSllJTWtZZ01NTEphZElvTUVNSVlGTkVNUVpVWmNaTVlRT1pZRWY9X0R3RHdEd0hpV2tqaTNDanh6elVwV0dtNEVUejJXalNzbTFXbGk2amlDajJ1MmhXaWk0RzBpaVdrbXp5Nml3RGxXbXowRHlEMzI1ejVteHp4amh6eEQwVDBtaUN6V3VqaUQ1RzF6bGoyRzNENFR3VDUyeEdoV2lRPQ__';
        echo $this->decrypt($text);
    }

    public function eeee()
    {
        echo json_encode($this->ugame_model->get_user_staff("ovBB91co5zRnNBhdKbdjzSTvgMzM"));
        echo 'ok';
    }

    /**
     * @param $title 表头数组 例如：array(array('A','AgentCode','代理编码'),array('B','AgentName','代理名称'),array('C','AgentLastLoginTime','上次登录时间'));
     * @param $data 数据数组
     * @param $name 导出名字
     */
    function outExcel($title, $data, $name)
    {
        $this->load->library('PHPExcel');
        $this->load->library('PHPExcel/IOFactory');
        $resultPHPExcel = new PHPExcel();
        foreach ($title as $t) {
            $resultPHPExcel->getActiveSheet()->setCellValue($t[0] . '1', $t[2]);
        }
        $i = 2;
        foreach ($data as $key) {
            foreach ($title as $t) {
                $resultPHPExcel->getActiveSheet()->setCellValue($t[0] . $i, $key[$t[1]]);
            }
            $i++;
        }
        $outputFileName = $name . '.xlsx';
        $xlsWriter = new PHPExcel_Writer_Excel5($resultPHPExcel);
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header('Content-Disposition:inline;filename="' . $outputFileName . '"');
        header("Content-Transfer-Encoding: binary");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . date("D, d M Y H:i:s", time()) . " GMT");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Pragma: no-cache");
        $xlsWriter->save("php://output");
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
