<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/5/10
 * Time: 11:20 AM
 */

class Community extends CI_Controller
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
        $this->load->model('community_model');
    }

    public function index()
    {
        echo "开始";
    }

    // 插入文章
    public function insert_message()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $content = urldecode($post['content']);
        $plate_id = 0;

        $data['code'] = $this->community_model->insert_message($uid, $content, 0, $plate_id);

        echo $this->encrypt(json_encode($data));
    }

    // 删除文章
    public function delete_message()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $message_id = $post['message_id'];

        $data['code'] = $this->community_model->delete_message($uid, $message_id);

        echo $this->encrypt(json_encode($data));
    }

    // 获取文章
    public function get_all_message()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $start = $post['start'];
        $count = $post['count'];

        $plate_id = 0;


        $data['code'] = 1;
        $data['list'] = $this->community_model->get_all_message($uid, $start, $count, $plate_id);

        echo $this->encrypt(json_encode($data));
    }

    // 获取某个用户的文章
    public function get_uid_all_message()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $owner_uid = $post['owner_uid'];
        $start = $post['start'];
        $count = $post['count'];

        $plate_id = 0;


        $data['code'] = 1;
        $data['list'] = $this->community_model->get_uid_all_message($uid, $owner_uid, $start, $count, $plate_id);

        if (empty($data['list']))
        {
            $data['code'] = 0;
        }

        echo $this->encrypt(json_encode($data));
    }

    // 获取某个用户的文章
    public function get_id_all_message()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $owner_id = $post['owner_id'];
        $start = $post['start'];
        $count = $post['count'];

        $plate_id = 0;


        $data['code'] = 1;
        $data['list'] = $this->community_model->get_id_all_message($uid, $owner_id, $start, $count, $plate_id);

        if (empty($data['list']))
        {
            $data['code'] = 0;
        }

        echo $this->encrypt(json_encode($data));
    }

    // 获取某个文章信息
    public function get_message()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $message_id = $post['message_id'];
        $start = $post['start'];
        $count = $post['count'];

        $data['code'] = 0;
        $message_data = $this->community_model->get_message($uid, $message_id, $start, $count);
        if (!empty($message_data))
        {
            $data['code'] = 1;
            $data['message_data'] = $message_data;
        }
        echo $this->encrypt(json_encode($data));
    }

    // 置顶
    public function set_message_top()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $message_id = $post['message_id'];
        $top_pri = $post['top_pri'];

        $data['code'] = $this->community_model->set_message_top($message_id, $top_pri);

        echo $this->encrypt(json_encode($data));
    }

    // 加精
    public function set_message_ess()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $message_id = $post['message_id'];
        $is_ess = $post['is_ess'];

        $data['code'] = $this->community_model->set_message_ess($message_id, $is_ess);

        echo $this->encrypt(json_encode($data));
    }

    // 评论
    public function insert_reply_message()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $message_id = $post['message_id'];
        $content = urldecode($post['content']);
        $reply_uid = $post['reply_uid'];

        $red_id = $this->community_model->get_red_id_from_message($message_id);
        $data['code'] = $this->community_model->insert_reply_message($uid, $message_id, $content, $reply_uid);
        if ($red_id > 0 && $data['code'] == 1)
        {
        }
        echo $this->encrypt(json_encode($data));
    }

    // 删除评论
    public function delete_reply_message()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $message_id = $post['message_id'];
        $reply_id = $post['reply_id'];

        $data['code'] = $this->community_model->delete_reply_message($uid, $message_id, $reply_id);

        echo $this->encrypt(json_encode($data));
    }

    // 获取评论数据
    public function get_reply_message()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $message_id = $post['message_id'];
        $start = $post['start'];
        $count = $post['count'];


        $data['code'] = 1;
        $data['list'] = $this->community_model->get_replay_message($message_id, $start, $count);

        echo $this->encrypt(json_encode($data));
    }

    // 点赞
    public function insert_praise_message()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $message_id = $post['message_id'];

        $data['code'] = $this->community_model->insert_praise_message($uid, $message_id);

        echo $this->encrypt(json_encode($data));
    }

    // 撤销点赞
    public function delete_praise_message()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $message_id = $post['message_id'];

        $data['code'] = $this->community_model->delete_praise_message($uid, $message_id);

        echo $this->encrypt(json_encode($data));
    }

    // 点赞评论
    public function insert_praise_reply()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $reply_id = $post['reply_id'];

        $data['code'] = $this->community_model->insert_praise_reply($uid, $reply_id);

        echo $this->encrypt(json_encode($data));
    }

    // 撤销点赞评论
    public function delete_praise_reply()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $reply_id = $post['reply_id'];

        $data['code'] = $this->community_model->delete_praise_reply($uid, $reply_id);

        echo $this->encrypt(json_encode($data));
    }

    // 关小黑屋
    public function insert_small_hut()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $detain_uid = $post['detain_uid'];

        $data['code'] = $this->community_model->insert_user_blacklist($uid, $detain_uid);

        echo $this->encrypt(json_encode($data));
    }

    // 从小黑屋释放囚犯
    public function delete_small_hut()
    {
        $post = $_POST['data'];
        $post = json_decode($this->decrypt($post), true);
        $uid = $post['uid'];
        $detain_uid = $post['detain_uid'];

        $data['code'] = $this->community_model->delete_user_blacklist($uid, $detain_uid);

        echo $this->encrypt(json_encode($data));
    }

    public function get_users()
    {
        $post = file_get_contents('php://input');
        $json_data = json_decode($post, true);
        $start = $json_data['start'];
        $end = $json_data['end'];

        $data = $this->community_model->get_users($start, $end);

        echo json_encode($data);
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