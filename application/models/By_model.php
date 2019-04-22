<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/3/26
 * Time: 7:06 PM
 */

require APPPATH.'biyong/BiYongMerchantCipher.php';

class HttpClient {
    var $cipher;
    var $apiUrl;

    function __construct($appId, $yourPrivateKey, $biyongPublicKey, $apiUrl, $shaHashMode='SHA256', $aesMode=null) {
        $this->apiUrl = $apiUrl;
        $this->cipher = new BiYongMerchantCipher($appId, $yourPrivateKey, $biyongPublicKey, $shaHashMode, $aesMode);
    }

    function call($uri, $dataString) {
        $message = $this->cipher->clientEncrypt($dataString);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . $uri);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->cipher->httpHeaders);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message->data);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode == 200) {
            return ord($response[0]) == 0 ? $this->cipher->clientDecrypt($response, $message) : $response;
        } else {
            if ($httpCode == 0) {
                $httpCode = 10000;
            }
            return "{\"status\":{$httpCode},\"message\":\"请求失败\"}";;
        }
        curl_close($ch);
    }

    function serverDecrypt($data) {
        $this->cipher->serverDecrypt($data);
    }
}

class By_model extends MY_Model
{
    function __construct()
    {
        parent::__construct();
        $this->load->model('ugame_model');
    }

    public function auth($authToken)
    {
        $post_data = array(
            "authToken" => $authToken
        );

        $url = "biyong-user/auth";

        $output = $this->biyong_send($url, $post_data);

        $result = json_decode($output, true);
        if ($result['status'] != 0)
        {
            return null;
        }
        $result = $result['data'];
//        if (!isset($result['openId']) || !$result['openId'] || $result['openId'] = 'null')
//        {
//            $data['code'] = "3";
//            $data['msg'] = $output;
//            echo $this->encrypt(json_encode($data));
//            die;
//        }
        $uid = $result['openId'];
        $biy_userinfo = $result['userInfo'];
        $userinfo = array(
            "openid" => $uid,
            "unionid" => $uid,
            "nickname" => $biy_userinfo['firstName'].$biy_userinfo['lastName'],
            "sex" => 0,
            "headimgurl" => isset($biy_userinfo['selfieUrl']) ? $biy_userinfo['selfieUrl'] : "",
            "city" => "",
            "province" => "",
            "country" => ""
        );

        if ($biy_userinfo['phoneAuth'] == "true")
        {
            $userinfo["phone_number"] = $biy_userinfo['phone'];
        }

        return $userinfo;
    }


    public function get_token_info($pageNo)
    {
        $array = array(
            "pageNo" => $pageNo,                             // 必填 页码，从0计数
            "pageSize" => 200                            // 必填 每页条数，最大1000
        );

        $url = "common/token-info/page";
        $output = $this->biyong_send($url, $array);
        return $output;
    }


    public function create_buy_dy_hen_flow_id($uid, $dy_hen)
    {
        if ($dy_hen < 0)
        {
            $result['code'] = 0;
            return $result;
        }

        $ip = $this->getIp();

        $time_start = date('YmdHis', time());
        $flow_id = strtoupper(md5('by_pay'.$uid.$time_start));
        $foods_type = "兑换福利鸡";

        $usdt = $dy_hen * 0.015;
        $gramusdt = $this->ugame_model->get_config('gramusdt');
//        $btcusdt = $this->ugame_model->get_config('btcusdt');
        $ethusdt = $this->ugame_model->get_config('ethusdt');

        $gram = $usdt / floatval($gramusdt);
//        $btc = $usdt / floatval($btcusdt);
        $eth = $usdt / floatval($ethusdt);

        $multiPrice = array();
        array_push($multiPrice, array(
            "coinName" => "GRAM",
            "balance" => $gram
        ));
//        if ($eth >= 0.01)
//        {
//            array_push($multiPrice, array(
//                "coinName" => "ETH",
//                "balance" => $eth
//            ));
//        }

        $array = array(
            "outOrderCode" => $flow_id,
            "orderName" => $foods_type,
            "multiPrice" => $multiPrice,
            "remark" => "",
            "expireSec" => 120
        );


        $url = "b-pay/order/create";
        $output = $this->biyong_send($url, $array);

        $data = json_decode($output, true);
        if ($data['status'] == 0)
        {
            $result = $data['data'];
            $this->db->insert('wallet_by_pay_purchase_record', array(
                'flow_id' => $flow_id,
                'uid' => $uid,
                'foods_type' => $foods_type,
                'items' => $dy_hen,
                'date' => date('Y-m-d H:i:s', time()),
                'ip' => $ip,
                'orderCode' => $result['orderCode']
            ));


            $result['flow_id'] = $result['outOrderCode'];
            unset($result['outOrderCode']);
            $result['code'] = 1;
        }
        else
        {
            $result['code'] = 0;
            $result['msg'] = $data;
            $result['eth'] = $eth;
        }

        return $result;
    }

    public function query_buy_dy_hen_flow_id($uid, $flow_id)
    {
        $data = $this->db->query("select * from wallet_by_pay_purchase_record where flow_id = '$flow_id' and uid = '$uid' and foods_type = '兑换福利鸡'")->row_array();
        if (!isset($data))
        {
            $result['code'] = 0;
            return $result;
        }
        $result['code'] = $data['state'];
        return $result;
    }

    public function create_buy_feed_flow_id($uid, $feed)
    {
        if ($feed < 0)
        {
            $result['code'] = 0;
            return $result;
        }

        $ip = $this->getIp();

        $time_start = date('YmdHis', time());
        $flow_id = strtoupper(md5('by_pay_feed'.$uid.$time_start));
        $foods_type = "兑换饲料";

        $usdt = $feed * 0.2;
        $gramusdt = $this->ugame_model->get_config('gramusdt');
//        $btcusdt = $this->ugame_model->get_config('btcusdt');
        $ethusdt = $this->ugame_model->get_config('ethusdt');

        $gram = $usdt / floatval($gramusdt);
//        $btc = $usdt / floatval($btcusdt);
        $eth = $usdt / floatval($ethusdt);

        $multiPrice = array();
        array_push($multiPrice, array(
            "coinName" => "GRAM",
            "balance" => $gram
        ));
//        if ($eth >= 0.01)
//        {
//            array_push($multiPrice, array(
//                "coinName" => "ETH",
//                "balance" => $eth
//            ));
//        }

        $array = array(
            "outOrderCode" => $flow_id,
            "orderName" => $foods_type,
            "multiPrice" => $multiPrice,
            "remark" => "",
            "expireSec" => 120
        );


        $url = "b-pay/order/create";
        $output = $this->biyong_send($url, $array);

        $data = json_decode($output, true);
        if ($data['status'] == 0)
        {
            $result = $data['data'];
            $this->db->insert('wallet_by_pay_purchase_record', array(
                'flow_id' => $flow_id,
                'uid' => $uid,
                'foods_type' => $foods_type,
                'items' => $feed,
                'date' => date('Y-m-d H:i:s', time()),
                'ip' => $ip,
                'orderCode' => $result['orderCode']
            ));


            $result['flow_id'] = $result['outOrderCode'];
            unset($result['outOrderCode']);
            $result['code'] = 1;
        }
        else
        {
            $result['code'] = 0;
            $result['msg'] = $data;
        }

        return $result;
    }

    public function query_buy_feed_flow_id($uid, $flow_id)
    {
        $data = $this->db->query("select * from wallet_by_pay_purchase_record where flow_id = '$flow_id' and uid = '$uid' and foods_type = '兑换饲料'")->row_array();
        if (!isset($data))
        {
            $result['code'] = 0;
            return $result;
        }
        $result['code'] = $data['state'];
        return $result;
    }

    private function handle_flow_id($value)
    {
        $flow_id = $value['flow_id'];
        $uid = $value['uid'];
        $foods_type = $value['foods_type'];
        $coin_name = $value['coin_name'];
        $coin = $value['coin'];
        $items= $value['items'];

        $time = strtotime($value['date']);
        if (time() - $time > 300)
        {
            $this->db->query("update wallet_by_pay_purchase_record set state = -1 where flow_id = '$flow_id' and uid = '$uid'");
            return;
        }

        $array = array(
            "outOrderCode" => $flow_id
        );

        $url = "b-pay/order/query";
        $output = $this->biyong_send($url, $array);
        $output = json_decode($output, true);

        // 支付完成
        if ($output['status'] == 'USER_PAY_SUCCESS' || $output['status'] == 'USER_PAY_SUCCESS')
        {
            $output = $output['data'];
            $coin_name = $output['coinName'];
            $coin = $output['balance'];
            $this->db->query("update wallet_by_pay_purchase_record set state = 1, coin = $coin, coin_name = '$coin_name' where flow_id = '$flow_id' and uid = '$uid'");
            $rows = $this->db->affected_rows();
            if ($rows == 1)
            {
                if ($foods_type == '兑换饲料')
                {
                    $feed = $items;
                    $this->db->query("update ugame set feed = feed + $feed where uid = '$uid'");
                    $this->db->insert('compensate', array(
                        'uid' => $uid,
                        'title' => '兑换饲料',
                        'text' => '您兑换的'.$feed.'袋饲料已经到账啦。',
                        'send_date' => date('Y-m-d H:i:s', time())
                    ));
                }
                else if ($foods_type == '兑换福利鸡')
                {
                    $this->db->insert('compensate', array(
                        'uid' => $uid,
                        'title' => '兑换福利鸡',
                        'text' => '您兑换福利鸡到账啦(有效期30天)，请点击领取。',
                        'dy_fuli' => $items,
                        'dy_fuli_tov' => 30,
                        'send_date' => date('Y-m-d H:i:s', time())
                    ));
                }

            }
            return;
        }
        // 支付中
        if ($output['status'] == 'USER_PAYING' || $output['status'] == 'DEFAULT' || $output['status'] == 'SETTLING')
        {
            $this->db->insert('test', array(
                'data' => json_encode($output)
            ));
            $this->handle_flow_id($value);
        }


        // 已成功支付   结算中    结算完毕
        if ($output['status'] == 'SETTLED')
        {
            $this->db->insert('test', array(
                'data' => json_encode($output)
            ));
            return;
        }

        // 支付失败
        else
        {
            $this->db->query("update wallet_by_pay_purchase_record set state = -1 where flow_id = '$flow_id' and uid = '$uid'");
        }
    }


    public function callback_by_wallet_recharge_purchase($input)
    {
//        $this->db->insert('test', array(
//            'data' => 'length > '.strlen($input)
//        ));

        $data = $this->db->query("select * from wallet_by_pay_purchase_record where state = 0")->result_array();
        $index = 0;
        foreach ($data as $key=>$value)
        {
            $this->handle_flow_id($value);
            $index += 1;
        }
        return 'status: 200';
    }

    public function set_callback()
    {
        $array = array(
            'callbackUrl' => 'https://eggs.qiaochucn.com/index.php/Api_callback/callback_by_wallet_recharge_purchase',
            "rsaSignHashMode" => "SHA256",
            "aesMode" => "CBC/PKCS5Padding"
        );

        $url = "b-pay/callback/set";
        $this->biyong_send($url, $array);
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

    public function biyong_send($uri, $array)
    {
        $client = $this->get_client();
        $resp = $client->call($uri, json_encode($array));
        return $resp;
    }

    public function get_client()
    {
        // BiYong分配给你的AppId
//        $appId = "e3c5b910b08023f008cdb8e07c4235a5";

        // 正式环境
        $appId = "8e7684b8227c7497c8068bc6b445ada6";


        // 你的私钥(Base64-RFC4648)，在开放平台创建应用时，应填写此私钥对应公钥
        $yourPrivateKey = "MIIEowIBAAKCAQEA3qq53JXyf9LFsPmirph5VfnJcteTGLfnKiTkLNkdsDI1EQ+KsIz52o+dsBkvw8O5WeB2MLeII9I0BvPtX7a0U73NPqVQrgUYDh3ngLqtdWULhk5d0WtRMOEJPgOHs5nnrrG38dLRmrGMuWhHnB+PFTrHehvtmVdSucwgdnIo3/EpPm2aVB5qjDxj6kbIxWOTby9SVV81D4eJWnMQ56QhCBDYXwBl55Rw++vLRI5ZW51vQTNY0uA1nMQsCcmOYtIHyvcwhv9OsrblboR0l4Ed9XejYHOfCtNQ1XH4Vj0k90hy/j5qh109FsAopepQMmfQS9LIJO9+HVHPsdBOsLiLOwIDAQABAoIBAQDGwTgkWUhbpsVGAp6fIIT2JIAX3at0nfte2A1ApxDkDPznXKscisofuKA151WGdffF7SEyvTBtYR2Fs0iIbiqqsTo6mA/bNbrSJwlVE8zvhCF0YhFGdHfFnKnGTBpeS5vNiN87oUXtwlxtx1JcXqM2fQA/1BwTCypRpI65i49GzHAXShZR6qDAMi34RKRc4EfIKvcuIytw6+SIWPOimWPdEXApcRR0qwUxicxjvk+X+IIrHY2/DO4kCWtFP51rknPlBJQ/QOlHu0Ram3P43OuRFKybIktMgKsisIzrcFCW2Xg/NRcm20gVzNTHNWnSJFfNFBBkCOUfBeOix1tLW6fhAoGBAPDvaHTMzrFSWP92l7PLYTIRqjBXCtz1lSdGX/7pbS3expiIDKY6pAgTnoE9jAa1n5fn6cGCg1sp+ELpd7mku9u+ZT5wPZ74I3C27x6VF4s9cLU6k7KHwOS5PIhPjBCDCPVq9GXD1lKkI47Rh9PqKLxeaZHrP7eTQCW63Y2Wzz+LAoGBAOyW5t/IFTvEalWcRRxiFZR46nKefGw2tbFZ6ChsN7fQfO7Pv3nlCOh192l8L51Mv5lFPLiwKGwCAfseNxk+8DYbmH/9jb5gmIFwajuZF5xGvsPAsb/RwDNWQDsbATc4ygIXO5Wi3+1D/RjBTupM5uK7uq4wKJQLGpn8AvD7WlkRAoGAEdKj93/vCk79JmivcC1rUzjbThgiRZYlInKiR0kdOndwWXg291T/LVnGL+lNonFtDN+q+xWcgfHo24pJwQHeo7LB6oyDAm94r63YonqE81foLn/WzS4dps3NHIhF5DLNRtpSmfSStipONxJo6dQ4jzasuI6eeAX8iM359a9iRWECgYAkBU2dYJ8q/FqcMjCrg5t5gXdggBS9fQ7os6GPFfrdt5Zt3Z9vdZmHmv5SRyAQuPCq60m1bMyatSuMCiulYlm8QzNSuU3tYFOX0X+7FzHrDzJYJ2xoogy7RDR/SztCJxlKfDMMM0IYo7NTI4taTN8SQjbH8nkbIR2puB7ShdJZoQKBgD8Ut7pdTyFU8h8dH0An+42IBXEOeJWpBJPrGMcjc0O2eYx1NDNmiyzgdySy7S9T0JNM4Q9+Cn6M6lZEE1aM9nyGAnVBOjc1q2G6daO0xbWJaK+Ucuc0oLeEdURFw0wbKNUmhQq5nQDL3fm/RefMLVTcwVULg/AK6LodYzCT/710";

        // BiYong分配给你的RSA公钥(Base64-RFC4648)
//        $biyongPublicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA8K4jYFClSfAJRHiGuAPI4YtoOogsKNY37j8at5jOyQ2fakS2YSaKhGC4imIMbcht+bWUxNp35FwKzXdyUpk5B3UQfFfwE1r7PajhlObdN9b/muAI2LJi4w+2Y67rrLUx+v80jc4rg1Vbcn0Zb9L50RBMCJl8yR/y9miJwD2xhfOO2m/8mO1TRWmHHQpNXtI4bJw1Zq73DrK1nHJYABapHzjTervXgHwMgnnKzak/qbf+ntiBvDzyY1VCEiUJ38xpwNMbrHu/zCm4MzYfVB1XqvL/K3htaTQqyv7Oi8zA+NyaE5Z6DDrqNayiVR7CvJSYbBFtK6y6oypMo3bMBqSapwIDAQAB";

        // 正式环境
        $biyongPublicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAqLoJGAISLI1KCnmgt8306xc2zaWRGCb8QTlNII2+vrk90rkxu9Gz/iUFGMmIdmH84PuhRRVZSEGxjpci8eoQvzECTZ32jD6BLmWfJup8edz2ZbFTSyzSLqwJ+99VbT7KcFpj7ekwMxiW8ZiAewi/qPa1sf9D8xG/O0o0vEWZJ/Pr2S48jY1c2CLJo6XBzmrVUdjQQ2U7+UmS/0eHZvEFxy24rlNc8FhxyDauJ9ryc+0qcJK8Mrrfzt5ffyxhX2lkerKQQT8U1hjBzSj8FXDHhiPO36Qv5Seu6WVzaAm+AWKXgFn4EhPN4YYxtnVyVRgB9BJMtPCFufnJeTrIQdSomwIDAQAB";


        // BiYong 开放平台API 已填写开发环境API
//        $apiUrl = "https://open.biyong.sg/dev-api/";

        // 正式环境
        $apiUrl = "https://open.biyong.sg/api/";


        // RSA签名散列算法
        $rsaSignHashMode = "SHA256";

        // AES加密模式(设置为null不使用AES加密。正式环境采用https通信，非隐私数据接口建议关闭AES加密)
        $aesMode = null;

        $client = new HttpClient($appId, $yourPrivateKey, $biyongPublicKey, $apiUrl, $rsaSignHashMode, $aesMode);
        return $client;
    }
}