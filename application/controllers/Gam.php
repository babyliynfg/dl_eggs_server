<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2018/12/19
 * Time: 3:54 PM
 */
header('Access-Control-Allow-Origin:*');
class Gam extends CI_Controller
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
    }

    // 默认
    public function index()
    {
        echo "hello";
    }

    public function get_recommend_user_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $result['code'] = 1;
        $result['list'] = $this->friend_model->get_recommend_user_list($uid);

        echo $this->encrypt(json_encode($result));
    }

    public function user_search_id()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $search = $post['search'];

        $result['code'] = 1;
        $result['info'] = $this->friend_model->user_search_id($uid, $search);

        echo $this->encrypt(json_encode($result));
    }

    public function user_search_share_code()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $search = $post['search'];

        $result['code'] = 1;
        $result['info'] = $this->friend_model->user_search_id($uid, $search);

        echo $this->encrypt(json_encode($result));
    }

    public function user_search_phone_number()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $search = $post['search'];

        $result['code'] = 1;
        $result['info'] = $this->friend_model->user_search_phone_number($uid, $search);

        echo $this->encrypt(json_encode($result));
    }

    public function send_friend_add_request()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $other_uid = $post['other_uid'];

        $result['code'] = $this->friend_model->send_friend_add_request($uid, $other_uid);

        echo $this->encrypt(json_encode($result));
    }

    public function get_friend_ask_news()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['info'] = $this->friend_model->get_friend_ask_news($uid, $start, $count);

        echo $this->encrypt(json_encode($result));
    }

    public function handle_friend_ask_news()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $ask_id = $post['ask_id'];
        $is_aggree = $post['is_aggree'];

        $result['code'] = 1;
        $result['info'] = $this->friend_model->handle_friend_ask_news($uid, $ask_id, $is_aggree);

        echo $this->encrypt(json_encode($result));
    }

    public function ignore_friend_ask_news()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $ask_id = $post['ask_id'];

        $result['code'] = 1;
        $result['info'] = $this->friend_model->ignore_friend_ask_news($uid, $ask_id);

        echo $this->encrypt(json_encode($result));
    }

    public function ignore_no_need_operate_friend_ask_news()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];

        $result['code'] = 1;
        $result['info'] = $this->friend_model->ignore_no_need_operate_friend_ask_news($uid);

        echo $this->encrypt(json_encode($result));
    }

    public function delete_friend()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $other_uid = $post['other_uid'];

        $result['code'] = $this->friend_model->delete_friend($uid, $other_uid);

        echo $this->encrypt(json_encode($result));
    }

    public function get_friend_list()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $result['code'] = 1;
        $result['list'] = $this->friend_model->get_friend_list($uid, $start, $count);

        echo $this->encrypt(json_encode($result));
    }

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