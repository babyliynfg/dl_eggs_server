<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/3/8
 * Time: 11:24 AM
 */

class Task_download extends CI_Controller
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
        $this->load->model('taskdownload_model');

    }

    // 默认
    public function index()
    {
        echo "hello";
    }

    // 获取app任务列表
    public function get_download_app_info_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $app_data = $this->taskdownload_model->get_a_app_download_info();
        $user_finish_downloa_info = $this->taskdownload_model->get_a_finish_download($uid);

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

    // 下载成功回调
    public function callback_app_is_install()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $app_name = $post['app_name'];
        $os = $post['os'];

        $this->taskdownload_model->callback_app_is_install($uid, $app_name, $os);
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

        $result['code'] = $this->taskdownload_model->callback_app_is_awaken($uid, $app_name, $os);
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