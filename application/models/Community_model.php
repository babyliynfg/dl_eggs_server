<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/5/10
 * Time: 11:21 AM
 */

class Community_model extends MY_Model
{
    private $message = 'message';
    private $praise = 'praise';
    private $reply = 'reply';

    public function insert_message($uid, $content, $red_id, $plate_id)
    {
        if ($this->is_in_user_blacklist($uid))
            return -1;

        $time = time();
        if (!$this->is_in_user_gm($uid))
        {
            $last_send_time = $this->db->select('posting_time')->where('uid', $uid)->get('user_action_record')->row_array();
            $last_send_time = $last_send_time['posting_time'];
            if ($last_send_time != null)
            {
                if ($red_id == 0 && $time - $last_send_time < 180) return 71;

                $this->db->where('uid', $uid);
                $this->db->set('posting_time',$time, FALSE);
                $this->db->update('user_action_record');
            }
            else
            {
                $data['uid'] = $uid;
                $data['posting_time'] = $time;
                $this->db->insert('user_action_record', $data);
            }
        }

        $date = strval(date("Y-m-d H:i:s", $time));
        $message_data['uid'] = $uid;
        $message_data['content'] = $content;
        $message_data['send_date'] = $date;
        $message_data['last_reply_date'] = $date;
        $message_data['red_id'] = $red_id;
        $message_data['plate_id'] = $plate_id;
        $this->db->insert('circle_message', $message_data);

        return 1;
    }

    public function delete_message($uid, $message_id)
    {
        if (!$this->is_in_user_gm($uid))
            return 0;

        try
        {
            $this->db->query("INSERT INTO circle_message_recycle(message_id, uid, content, image_urls, send_date, last_reply_date, praise_count, reply_count, top_pri, is_ess, hyperlink_title, hyperlink_image, hyperlink_url, plate_id, red_id) SELECT * FROM circle_message WHERE message_id = $message_id");
            $this->db->query("DELETE FROM circle_message WHERE message_id = $message_id");
            $this->db->query("DELETE FROM circle_praise WHERE message_id = $message_id");
            $this->db->query("DELETE FROM circle_reply WHERE message_id = $message_id");
        }
        catch (\Exception $e)
        {
            Log::write('delete_message error');
            return 0;
        }
        return 1;
    }

    public function get_all_message($uid, $start, $count, $plate_id)
    {
        $data = $this->db_r()->query("SELECT * FROM circle_message WHERE plate_id = '$plate_id' ORDER BY top_pri DESC, last_reply_date DESC limit $start, $count")->result_array();
        if (empty($data))
        {
            return $data;
        }

        foreach ($data as &$datum)
        {
            $message_id = $datum['message_id'];
            $datum['is_praise'] = $this->is_praise_with_message($uid, $message_id) ? 1 : 0;
            $datum['reply_list'] = array();
//            $datum['reply_list'] = $this->get_replay_message_private($message_id, 0 , 10);
        }
        unset($datum);

        $result = $this->get_message_from_array($data);
        unset($data);
        return $result;
    }

    public function get_uid_all_message($uid, $owner_uid, $start, $count, $plate_id)
    {
        $data = $this->db_r()->query("SELECT * FROM circle_message WHERE plate_id = '$plate_id' and uid = '$owner_uid' ORDER BY top_pri DESC, last_reply_date DESC limit $start, $count")->result_array();

        if (empty($data))
            return $data;

        foreach ($data as &$datum)
        {
            $message_id = $datum['message_id'];
            $datum['is_praise'] = $this->is_praise_with_message($uid, $message_id) ? 1 : 0;
            $datum['reply_list'] = $this->get_replay_message_private($message_id, 0 , 10);
        }
        unset($datum);

        $result = $this->get_message_from_array($data);
        unset($data);
        return $result;
    }

    public function get_id_all_message($uid, $owner_id, $start, $count, $plate_id)
    {
        $owner_uid = $this->get_uid_with_id($owner_id);
        return $this->get_uid_all_message($uid, $owner_uid, $start, $count, $plate_id);
    }

    public function get_message($uid, $message_id, $start, $count)
    {
        $data = $this->db_r()->query("SELECT * FROM circle_message WHERE message_id = $message_id")->result_array();

        if (empty($data))
            return 0;

        foreach ($data as &$datum)
        {
            $message_id = $datum['message_id'];
            $datum['is_praise'] = $this->is_praise_with_message($uid, $message_id) ? 1 : 0;
            $datum['reply_list'] = $this->get_replay_message_private($message_id, $start , $count);
            foreach ($datum['reply_list'] as &$reply)
            {
                $reply_id = $reply['reply_id'];
                $reply['is_praise'] = $this->is_praise_with_reply($uid, $reply_id) ? 1 : 0;
            }
            unset($reply);
        }
        unset($datum);

        $result = $this->get_message_from_array($data);
        $result = $result[0];
        unset($data);
        return $result;
    }

    private function get_message_from_array(&$data)
    {
        $uid_list = array();
        foreach ($data as $datum)
        {
            array_push($uid_list, $datum['uid']);
            foreach ($datum['reply_list'] as $replay_item)
            {
                array_push($uid_list, $replay_item['uid']);
                array_push($uid_list, $replay_item['reply_uid']);
            }
        }
        $uid_list = array_unique($uid_list);

        $user_info = $this->get_user_info_list_new($uid_list);

        foreach ($data as &$datum)
        {
            $info = $user_info[$datum['uid']];
            $datum['id'] = $info['id'];
            $datum['nickname'] = $info['nickname'];
            $datum['sex'] = $info['sex'];
            $datum['headimgurl'] = $info['headimgurl'];
            foreach ($datum['reply_list'] as &$replay_item)
            {
                $info2 = $user_info[$replay_item['uid']];
                $replay_item['id'] = $info2['id'];
                $replay_item['nickname'] = $info2['nickname'];
                $replay_item['sex'] = $info2['sex'];
                $replay_item['headimgurl'] = $info2['headimgurl'];
                if (!empty($replay_item['reply_uid'])/* && array_key_exists($replay_item['reply_uid'], $user_info)*/)
                {
                    $info3 = $user_info[$replay_item['reply_uid']];
                    $replay_item['reply_nickname'] = $info3['nickname'];
                    $replay_item['reply_sex'] = $info3['sex'];
                    $replay_item['reply_headimgurl'] = $info3['headimgurl'];
                }
            }
            unset($replay_item);
        }
        unset($datum);

        return $data;
    }

    public function get_red_id_from_message($message_id)
    {
        $result = $this->db->select('red_id')->where('message_id', $message_id)->get('circle_message')->row_array();
        $result = $result['red_id'];
        return $result;
    }

    public function set_message_top($message_id, $top_pri)
    {
        try
        {
            $this->db->query("UPDATE circle_message SET top_pri = $top_pri WHERE message_id = $message_id");
        }
        catch (\Exception $e)
        {
            Log::write('set_message_top error');
            return 0;
        }
        return 1;
    }

    public function set_message_ess($message_id, $is_ess)
    {
        try
        {
            $this->db->query("UPDATE circle_message SET is_ess = $is_ess WHERE message_id = $message_id");
        }
        catch (\Exception $e)
        {
            Log::write('set_message_ess error');
            return 0;
        }
        return 1;
    }

    public function insert_reply_message($uid, $message_id, $content, $reply_uid)
    {
        if ($this->is_in_user_blacklist($uid))
            return -1;

        $time = time();
        if (!$this->is_in_user_gm($uid))
        {
            $last_send_time = $this->db->select('posting_time')->where('uid', $uid)->get('user_action_record')->row_array();
            $last_send_time = $last_send_time['posting_time'];
            if ($last_send_time != null)
            {
                if ($time - $last_send_time < 180) return 71;

                $this->db->where('uid', $uid);
                $this->db->set('posting_time',$time, FALSE);
                $this->db->update('user_action_record');
            }
            else
            {
                $data['uid'] = $uid;
                $data['posting_time'] = $time;
                $this->db->insert('user_action_record', $data);
            }
        }

        $send_date = strval(date("Y-m-d H:i:s", $time));
        try
        {
            if (empty($reply_uid))
            {
                $this->db->query("INSERT INTO circle_reply(uid, message_id, content, send_date) VALUE('$uid', $message_id, '$content', '$send_date')");
            }
            else
            {
                $this->db->query("INSERT INTO circle_reply(uid, message_id, reply_uid, content, send_date) VALUE('$uid', $message_id, '$reply_uid', '$content', '$send_date')");
            }
            $reply_id = $this->db->query("SELECT @@IDENTITY")->row_array();
            $reply_id = $reply_id['@@IDENTITY'];

            $reply_date = strval(date("Y-m-d H:i:s",intval(time())));
            $this->db->query("UPDATE circle_message SET reply_count = reply_count + 1, last_reply_date = '$reply_date' WHERE message_id = $message_id");


            $author_uid = ($this->db->query("SELECT uid FROM circle_message WHERE message_id = $message_id")->row_array());
            $author_uid = $author_uid['uid'];
            $this->push_content($uid, $author_uid, $reply_id, "reply");
            if (!empty($reply_uid))
            {
                $this->push_content($uid, $reply_uid, $reply_id, "reply");
            }
        }
        catch (\Exception $e)
        {
            Log::write('reply_message error');
            return 0;
        }

        return 1;
    }

    public function delete_reply_message($uid, $message_id, $reply_id)
    {
        if (!$this->is_in_user_gm($uid))
            return 0;

        try
        {
            $this->db->query("INSERT INTO circle_reply_recycle(reply_id, uid, message_id, reply_uid, content, send_date, praise_count, plate_id) SELECT * FROM circle_reply WHERE reply_id = $reply_id");
            $this->db->query("DELETE FROM circle_reply WHERE reply_id = $reply_id");
        }
        catch (\Exception $e)
        {
            Log::write('delete_reply_message error');
            return 0;
        }
        return 1;
    }

    public function get_replay_message($message_id, $start, $count)
    {
        $reply_list = $this->get_replay_message_private($message_id, $start, $count);

        $uid_list = array();
        foreach ($reply_list as $dreply_ietm)
        {
            array_push($uid_list, $dreply_ietm['uid']);
            array_push($uid_list, $dreply_ietm['reply_uid']);
        }
        $uid_list = array_unique($uid_list);

        $user_info = $this->get_user_info_list_new($uid_list);

        foreach ($reply_list as &$reply_ietm)
        {
            $info = $user_info[$reply_ietm['uid']];
            $reply_ietm['id'] = $info['id'];
            $reply_ietm['nickname'] = $info['nickname'];
            $reply_ietm['sex'] = $info['sex'];
            $reply_ietm['headimgurl'] = $info['headimgurl'];
            if (!empty($replay_item['reply_uid'])/* && array_key_exists($replay_item['reply_uid'], $user_info)*/)
            {
                $info2 = $user_info[$replay_item['reply_uid']];
                $replay_item['reply_nickname'] = $info2['nickname'];
                $replay_item['reply_sex'] = $info2['sex'];
                $replay_item['reply_headimgurl'] = $info2['headimgurl'];
            }
        }
        unset($reply_ietm);

        return $reply_list;
    }

    private function get_replay_message_private($message_id, $start, $count)
    {
        return $this->db->query("SELECT * FROM circle_reply WHERE message_id = $message_id ORDER BY reply_id DESC limit $start, $count")->result_array();
    }

    public function insert_praise_message($uid, $message_id)
    {
        if ($this->is_praise_with_message($uid, $message_id))
            return 0;

        try
        {
            $this->db->query("INSERT INTO circle_praise(uid, message_id) VALUE('$uid', $message_id)");
            $reply_date = strval(date("Y-m-d H:i:s",intval(time())));
            $this->db->query("UPDATE circle_message SET praise_count = praise_count + 1, last_reply_date = '$reply_date' WHERE message_id = $message_id");

            $author_uid = ($this->db->query("SELECT uid FROM circle_message WHERE message_id = $message_id")->row_array());
            $author_uid = $author_uid['uid'];
            $this->push_content($uid, $author_uid, $uid, "praise");
        }
        catch (\Exception $e)
        {
            Log::write('insert_praise_message error');
            return 0;
        }
        return 1;
    }

    public function delete_praise_message($uid, $message_id)
    {
        if (!$this->is_praise_with_message($uid, $message_id))
            return 0;
        try
        {
            $this->db->query("DELETE FROM circle_praise WHERE message_id = $message_id and uid = '$uid'");
            $this->db->query("UPDATE circle_message SET praise_count = praise_count - 1 WHERE message_id = $message_id");
        }
        catch (\Exception $e)
        {
            Log::write('delete_praise_message error');
            return 0;
        }
        return 1;
    }

    public function is_praise_with_message($uid, $message_id)
    {
        $sel = $this->db->query("SELECT * FROM circle_praise WHERE uid = '$uid' and message_id = $message_id")->result_array();
        return !empty($sel);
    }

    public function insert_praise_reply($uid, $reply_id)
    {
        if ($this->is_praise_with_message($uid, $reply_id))
            return 0;

        try
        {
            $this->db->query("INSERT INTO circle_praise_reply(uid, reply_id) VALUE('$uid', $reply_id)");
            $this->db->query("UPDATE circle_reply SET praise_count = praise_count + 1 WHERE reply_id = $reply_id");

        }
        catch (\Exception $e)
        {
            Log::write('insert_praise_message error');
            return 0;
        }
        return 1;
    }

    public function delete_praise_reply($uid, $reply_id)
    {
        if (!$this->is_praise_with_reply($uid, $reply_id))
            return 0;
        try
        {
            $this->db->query("DELETE FROM circle_praise_reply WHERE reply_id = $reply_id and uid = '$uid'");
            $this->db->query("UPDATE circle_reply SET praise_count = praise_count - 1 WHERE reply_id = $reply_id");
        }
        catch (\Exception $e)
        {
            Log::write('delete_praise_message error');
            return 0;
        }
        return 1;
    }

    public function is_praise_with_reply($uid, $reply_id)
    {
        $sel = $this->db->query("SELECT * FROM circle_praise_reply WHERE uid = '$uid' and reply_id = $reply_id")->result_array();
        return !empty($sel);
    }

    public function is_in_user_blacklist($convict_uid)
    {
        $sel = $this->db->query("SELECT * FROM user_blacklist WHERE uid = '$convict_uid'")->result_array();
        return !empty($sel);
    }

    public function insert_user_blacklist($uid, $convict_uid)
    {
        if (!$this->is_in_user_gm($uid))
            return 0;

        try
        {
            $this->db->query("INSERT INTO user_blacklist(`uid`) VALUE('$convict_uid')");
        }
        catch (\Exception $e)
        {
            Log::write('insert_user_blacklist error');
            return 0;
        }
        return 1;
    }

    public function delete_user_blacklist($uid, $convict_uid)
    {
        if (!$this->is_in_user_gm($uid))
            return 0;

        try
        {
            $this->db->query("DELETE FROM user_blacklist WHERE uid = '$convict_uid'");
        }
        catch (\Exception $e)
        {
            Log::write('delete_user_blacklist error');
            return 0;
        }
        return 1;
    }

    public function is_in_user_gm($uid)
    {
        $sel = $this->db->query("SELECT * FROM user_gm WHERE uid = '$uid'")->result_array();
        return !empty($sel);
    }

    public function get_uid_with_id($id)
    {
        $uid = $this->db->query("SELECT openid FROM wx_info WHERE id = $id")->result_array();
        $uid = $uid[0]['openid'];
        return $uid;
    }

    public function get_user_info_list_new($uid_list)
    {
        $sql = 'SELECT id, openid, nickname, sex, headimgurl FROM wx_info WHERE';
        foreach ($uid_list as $openid)
        {
            $sql = $sql." openid = '$openid' or";
        }
        $sql = rtrim($sql, ' or');

        $info = array();
        $data = $this->db->query($sql)->result_array();
        foreach ($data as $v)
        {
            $info[$v['openid']] = $v;
        }

        return $info;
    }


    public function get_users($start, $end)
    {
        $count = $end - $start + 1;
        return $this->db->query("SELECT * FROM user_statistics ORDER BY id DESC limit $start, $count")->result_array();
    }

    public function repair()
    {
        $data = $this->db->query("SELECT message_id, send_date FROM circle_message")->result_array();
        foreach ($data as &$datum)
        {
            $message_id = $datum['message_id'];
            $send_date = $datum['send_date'];
            $this->db->query("UPDATE circle_message SET last_reply_date = '$send_date' WHERE message_id = $message_id");
        }
        return 1;
    }


    private function push_content($fromUserId, $toUserId, $text, $info, $pushContent = '', $pushData = '', $isPersisted = 0, $isCounted = 0)
    {

    }
}