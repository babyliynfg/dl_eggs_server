<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2018/11/28
 * Time: 2:20 PM
 */
require APPPATH.'rongcloud/RongCloud.php';
class MY_Model extends CI_Model
{
    // 1 内网 0 外网
    private static $is_intranet = 0;

    // 内网
    private static $in_database_rr = '45.32.70.123';
    private static $in_database_rw = '45.32.70.123';

    // 外网
    private static $out_database_rr = '45.32.70.123';
    private static $out_database_rw = '45.32.70.123';

    public function __construct()
    {
        parent::__construct();

        $config['hostname'] = $this->database_rr();
        $config['username'] = 'root';
        $config['password'] = 'Lego@Mysql1qaz';
        $config['database'] = 'eggs';
        $config['dbdriver'] = 'mysqli';
        $config['dbprefix'] = '';
        $config['pconnect'] = FALSE;
        $config['db_debug'] = (ENVIRONMENT !== 'production');
        $config['cache_on'] = FALSE;
        $config['cachedir'] = '';
        $config['char_set'] = 'utf8';
        $config['dbcollat'] = 'utf8_general_ci';
        $this->load->database_r($config);

        $config['hostname'] = $this->database_rw();
        $config['username'] = 'root';
        $config['password'] = 'Lego@Mysql1qaz';
        $config['database'] = 'eggs';
        $config['dbdriver'] = 'mysqli';
        $config['dbprefix'] = '';
        $config['pconnect'] = FALSE;
        $config['db_debug'] = (ENVIRONMENT !== 'production');
        $config['cache_on'] = FALSE;
        $config['cachedir'] = '';
        $config['char_set'] = 'utf8';
        $config['dbcollat'] = 'utf8_general_ci';
        $this->load->database($config);

//        $this->load->database();
    }

    private function database_rr()
    {
        if (MY_Model::$is_intranet)
        {
            return MY_Model::$in_database_rr;
        }
        else
        {
            return MY_Model::$out_database_rr;
        }
    }

    private function database_rw()
    {
        if (MY_Model::$is_intranet)
        {
            return MY_Model::$in_database_rw;
        }
        else
        {
            return MY_Model::$out_database_rw;
        }
    }

    public function db_r()
    {
        return $this->db_r;
    }

    public function db_w()
    {
        return $this->db;
    }

    public function rong_push_content($fromUserId, $toUserId, $text, $business, $info, $extra_array = null, $pushContent = '', $pushData = '', $isPersisted = 0, $isCounted = 0)
    {
        $rongCloud = new RongCloud('25wehl3u2sblw', 'v3V8kGWFlk8W');

        $objectName = 'RC:TxtMsg';

        $extra = array(
            "business" => $business,
            "info" => $info
        );

        if (isset($extra_array))
        {
            $extra = array_merge($extra, $extra_array);
        }

        $content = json_encode(array(
            "content" => $text,
            "extra" => $extra
        ));

        return $rongCloud->Message()->PublishSystem($fromUserId, $toUserId,  $objectName, $content, $pushContent, $pushData, $isPersisted, $isCounted);
    }
}


