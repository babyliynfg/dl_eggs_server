<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2018/12/19
 * Time: 3:54 PM
 */

class Friend_model extends MY_Model
{
    public function get_recommend_user_list($uid)
    {
        $max = $this->db_r()->query("select max(id) as `max` from everyday_user_record")->row_array();
        if (!isset($max) || empty($max['max']) || $max['max'] < 120)
        {
            $max = $this->db_r()->query("select max(id) as `max` from wx_info")->row_array();
            $max = $max['max'];
            $position = rand(0, $max - 100);

            $data = $this->db_r()->query("select openid as uid, id, nickname, sex, headimgurl from wx_info where id >= $position ORDER BY id LIMIT 100")->result_array();
        }
        else
        {
            $max = $max['max'];
            $position = rand(0, $max - 100);

            $data = $this->db_r()->query("select record.uid, w.id, w.nickname, w.sex, w.headimgurl from everyday_user_record record left join wx_info w on w.openid = record.uid where record.id >= $position ORDER BY record.id LIMIT 100")->result_array();
        }
        return $data;
    }

    public function user_search_id($uid, $search)
    {
        $id = intval($search);
        return $this->db_r()->query("select openid as uid, id, nickname, sex, headimgurl from wx_info where id = $id")->row_array();
    }

    public function user_search_phone_number($uid, $search)
    {
        return $this->db_r()->query("select openid as uid, id, nickname, sex, headimgurl from wx_info where phone_number = '$search'")->row_array();
    }

    public function send_friend_add_request($uid, $other_uid)
    {
        if ($uid == $other_uid)
            return 0;

        if ($this->verifying_friends($uid, $other_uid))
            return 201;

        $this->db_w()->query("delete from friend_ask_list where ask_uid = '$uid' and be_ask_uid = '$other_uid'");

        $user_info = $this->db_r()->query("select nickname, sex, headimgurl from wx_info where openid = '$uid'")->row_array();
        $other_user_info = $this->db_r()->query("select nickname, sex, headimgurl from wx_info where openid = '$other_uid'")->row_array();

        $data = array(
            'ask_uid' => $uid,
            'ask_nickname' => $user_info['nickname'],
            'ask_sex' => $user_info['sex'],
            'ask_headimgurl' => $user_info['headimgurl'],
            'be_ask_uid' => $other_uid,
            'be_ask_nickname' => $other_user_info['nickname'],
            'be_ask_sex' => $other_user_info['sex'],
            'be_ask_headimgurl' => $other_user_info['headimgurl'],
        );
        $res = $this->db_w()->insert('friend_ask_list', $data);
        if (!$res) return 0;


        // 通知
        $text = $data['ask_nickname'].'请求添加你为好友';
        $this->rong_push_content($uid, $other_uid, $text, '社交','好友申请');

        return 1;
    }

    public function get_friend_ask_news($uid, $start, $count)
    {
        return $this->db_r()->query("select * from friend_ask_list where (be_ask_uid = '$uid' and be_ask_so = 1) or (ask_uid = '$uid' and ask_so = 1) ORDER BY ask_id DESC LIMIT $start, $count")->result_array();
    }

    public function handle_friend_ask_news($uid, $ask_id, $is_aggree)
    {
        if ($is_aggree)
        {
            $ask_info = $this->db_r()->query("select * from friend_ask_list where be_ask_uid = '$uid' and ask_id = $ask_id")->row_array();
            if (!isset($ask_info)) return 0;

            $this->db_w()->query("update friend_ask_list set state = 1 where be_ask_uid = '$uid' and ask_id = $ask_id");
            $row = $this->db_w()->affected_rows();

            if ($this->verifying_friends($uid, $ask_info['ask_uid']))
                return 201;

            if ($row == 0) return 0;

            $date = date("Y-m-d H:i:s",time());

            $res = $this->db_w()->insert('friend_pair', array(
                'uid' => $uid,
                'friend_uid' => $ask_info['ask_uid'],
                'begin_date' => $date,
            ));
            if (!$res) return 0;

            $res = $this->db_w()->insert('friend_pair', array(
                'uid' => $ask_info['ask_uid'],
                'friend_uid' => $uid,
                'begin_date' => $date,
            ));
            if (!$res) return 0;

        }
        else
        {
            $this->db_w()->query("update friend_ask_list set state = 2 where be_ask_uid = '$uid' and ask_id = $ask_id");
            $row = $this->db_w()->affected_rows();
            if ($row == 0) return 0;
        }

        // 通知

        return 1;
    }

    public function ignore_friend_ask_news($uid, $ask_id)
    {
        $this->db_w()->query("update friend_ask_list set be_ask_so = 0 where be_ask_uid = '$uid' and ask_id = $ask_id");
        $row = $this->db_w()->affected_rows();
        if ($row == 0)
        {
            $this->db_w()->query("update friend_ask_list set ask_so = 0 where ask_uid = '$uid' and ask_id = $ask_id");
        }
        return 1;
    }

    public function ignore_no_need_operate_friend_ask_news($uid)
    {
        $this->db_w()->query("update friend_ask_list set be_ask_so = 0 where be_ask_uid = '$uid' and state != 0");
        $this->db_w()->query("update friend_ask_list set ask_so = 0 where ask_uid = '$uid' and state != 0");
        return 1;
    }

    public function get_friend_list($uid, $start, $count)
    {
        $superior_info = array();
        if ($start == 0)
        {
            $superior_info = $this->db_r()->query("select c.owner_uid as friend_uid, 0 as `group`, w.id, w.nickname, w.sex, w.headimgurl from c_h_staff c left join wx_info w on w.openid = c.owner_uid where c.uid = '$uid'")->result_array();
        }

        $data = $this->db_r()->query("select c.uid as friend_uid, 1 as `group`, w.id, w.nickname, w.sex, w.headimgurl from c_h_staff c left join wx_info w on w.openid = c.uid where c.owner_uid = '$uid' ORDER BY c.id LIMIT $start, $count")->result_array();
        if (empty($data))
        {
            $row = $this->db_r()->query("select share_cnt from ugame where uid = '$uid'")->row_array();
            $row = $row['share_cnt'];
            $start -= $row;
            $start = max($start, 0);
            $data2 = $this->db_r()->query("select f.friend_uid, 2 as `group`, w.id, w.nickname, w.sex, w.headimgurl from friend_pair f left join wx_info w on w.openid = f.friend_uid where f.uid = '$uid' ORDER BY f.id LIMIT $start, $count")->result_array();
            $data = array_merge($data, $data2);
        }
        else if (count($data) < $count)
        {
            $start = 0;
            $count -= count($data);
            $count = max($count, 0);
            $data2 = $this->db_r()->query("select f.friend_uid, 2 as `group`, w.id, w.nickname, w.sex, w.headimgurl from friend_pair f left join wx_info w on w.openid = f.friend_uid where f.uid = '$uid' ORDER BY f.id LIMIT $start, $count")->result_array();
            $data = array_merge($data, $data2);
        }
        if (!empty($superior_info))
        {
            $data = array_merge($superior_info, $data);
        }
        return $data;
    }

    public function delete_friend($uid, $other_uid)
    {
        $this->db_w()->query("delete from friend_pair where uid = '$uid' and friend_uid = '$other_uid'");
        $this->db_w()->query("delete from friend_pair where uid = '$other_uid' and friend_uid = '$uid'");
        return 1;
    }

    public function get_other_user_info($uid, $other_uid)
    {
        $result = $this->db_r()->query("select u.uid, w.id, w.nickname, w.sex, w.city, w.province, w.country, w.headimgurl, u.share_cnt, u.fuli, u.dy_fuli, u.logintime from ugame u left join wx_info w on w.openid = u.uid where u.uid = '$other_uid'")->row_array();
        if (!isset($result))
            return array();
        $result['in_blacklist'] = $this->get_user_in_blacklist($other_uid);
        $result['is_friends'] = $this->verifying_friends($uid, $other_uid);
        return $result;
    }

    public function verifying_friends($uid, $other_uid)
    {
        $result = $this->db_r()->query("select * from c_h_staff where uid = '$uid' and owner_uid = '$other_uid'")->row_array();
        if (isset($result))
            return 2;
        $result = $this->db_r()->query("select * from c_h_staff where owner_uid = '$uid' and uid = '$other_uid'")->row_array();
        if (isset($result))
            return 2;
        $result = $this->db_r()->query("select * from friend_pair where uid = '$uid' and friend_uid = '$other_uid'")->row_array();
        return intval(boolval(isset($result)));
    }

    public function get_user_in_blacklist($uid)
    {
        $result = $this->db_r()->query("select * from user_blacklist where uid = '$uid'")->result_array();
        if (empty($result))
            return 0;
        return 1;
    }
}