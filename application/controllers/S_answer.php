<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2018/12/6
 * Time: 4:25 PM
 */

class S_answer extends CI_Controller
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
    }

    // 默认
    public function index()
    {
        echo "hello";
    }

    // 获取我的答题信息
    public function get_my_answer_info()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $end_date = $this->ugame_model->get_config('answer_end_date');
        $date = date("Y-m-d H:i:s",time());

        $result['code'] = $date >= $end_date ? 2 : 1;
        $result['info'] = $this->m_answer_model->get_my_answer_info($uid);
        echo $this->encrypt(json_encode($result));
    }

    // 获取答题排行榜
    public function get_answer_ranking()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $result['code'] = 1;
        $result['info'] = $this->m_answer_model->get_answer_ranking();
        echo $this->encrypt(json_encode($result));
    }

    // 答对了
    public function set_answer_bingo()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $timestamp = $post['timestamp'];

        $result['code'] = 1;
        $this->m_answer_model->set_answer_bingo($uid, $timestamp);
        echo $this->encrypt(json_encode($result));
    }

    // 答错了
    public function set_answer_error()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $timestamp = $post['timestamp'];

        $result['code'] = 1;
        $result['surplus_times'] = $this->m_answer_model->set_answer_error($uid, $timestamp);
        echo $this->encrypt(json_encode($result));
    }

    // 每日分享换答题次数
    public function answer_share_times_change()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];


        $surplus_times = $this->m_answer_model->answer_share_times_change($uid);
        if ($surplus_times > 0)
        {
            $result['code'] = 1;
        }
        else
        {
            $result['code'] = 0;
        }
        $result['surplus_times'] = $surplus_times;

        echo $this->encrypt(json_encode($result));
    }
    // 鸡蛋换答题次数
    public function answer_eggs_times_change()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $surplus_times = $this->m_answer_model->answer_eggs_times_change($uid);
        if ($surplus_times > 0)
        {
            $result['code'] = 1;
        }
        else
        {
            $result['code'] = 0;
        }
        $result['surplus_times'] = $surplus_times;

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

    public function task_make_answer_ranking()
    {
        $this->m_answer_model->make_answer_ranking();
        echo 'ok';
    }
}