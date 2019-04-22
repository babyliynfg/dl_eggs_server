<?php
/**
 * Created by PhpStorm.
 * User: wps
 * Date: 2018/5/2
 * Time: 18:10
 */
class Message_model extends MY_Model{

    //获取留言列表
    function get_message_list(){
//        $now = time();
        $result = $this->db->query("select * from message where show_status = 1 order by edittime desc")->result_array();
        return $result;
    }
    //添加留言
    function add_message($data){
        return $this->db->insert('message', $data);
    }

    public function get_qds_count($uid)
    {
        $data = $this->db->query("SELECT qds_count FROM a_game_qds_info WHERE uid = '$uid'")->result_array();
        if (empty($data))
        {
            $this->db->insert('a_game_qds_info', array('uid' => $uid));
            return 30;
        }
        $qds_count = $data[0]['qds_count'];
        return $qds_count;
    }

    public function is_qds_progressive($uid, &$result)
    {
        $data = $this->db->query("SELECT state, page_id FROM a_game_qds_progressive WHERE uid = '$uid' and state != 2")->result_array();
        if (empty($data))
        {
            $result['state'] = 0;
            $result['page_id'] = -1;
        }
        else
        {
            $result['state'] = $data[0]['state'];
            $result['page_id'] = $data[0]['page_id'];
        }

        return $result;
    }

    public function get_qds_list($uid, $start, $count)
    {
        $data = $this->db->query("SELECT page_serial FROM a_game_qds_progressive WHERE uid = '$uid'")->result_array();
        if (empty($data))
            return array();
        $str = "(";
        $index = 0;
        foreach ($data as $k=>$v)
        {
            $page_serial = $v['page_serial'];
            $str .= "'".$page_serial."',";
            $index += 1;
        }
        $str = rtrim($str, ',');
        $str .= ")";

        $sql = "SELECT * FROM a_game_qds_list WHERE page_serial in ".$str." ORDER BY id DESC limit $start, $count";

        return $this->db->query($sql)->result_array();
    }

    public function get_qds_ranking_list($uid, $start, $count)
    {
        return $this->db->query("SELECT * FROM a_game_qds_ranking_list ORDER BY id limit $start, $count")->result_array();
    }

    public function qds_create($uid, $nickname, $headimgurl, $text)
    {
        $time = time();

        $data = $this->db->query("SELECT * FROM a_game_qds_info WHERE uid = '$uid'")->result_array();
        if (empty($data))
        {
            $this->db->insert('a_game_qds_info', array('uid' => $uid));
            $data = $this->db->query("SELECT * FROM a_game_qds_info WHERE uid = '$uid'")->result_array();
        }
        $data = $data[0];
        $qds_count = $data['qds_count'];
        if ($qds_count == 0)
            return array('code' => 2401, 'page_id' => 0);
        $last_qds_date = $data['last_qds_date'];
        if ($last_qds_date == date('Y-m-d', $time))
            return array('code' => 2402, 'page_id' => 0);

        $page_serial = md5($uid.$time);
        $array = array(
            'appkey' => 'chicken',
            'page_serial' => $page_serial,
            'page_name' => urldecode($nickname),
            'page_icon' => $headimgurl,
            'page_desc' => $text,
            'nonce' => '123'
        );
        $array['sign'] = $this->get_qds_sign($array);

        $url = 'http://xpot.lookme.henkuai.com';
        $url .= '/api/v1/crowd/create_page';
        $output = $this->send_post($url, $array);

        $output = json_decode($output, true);

        if ($output['error'] == 0)
        {
            $data = array(
                'uid' => $uid,
                'page_serial' => $page_serial,
                'page_id' => $output['page_id'],
                'date' => date('Y-m-d H:i:s', time())
            );
            $this->db->insert('a_game_qds_progressive', $data);
            $date = date('Y-m-d', $time);
            $this->db->query("UPDATE a_game_qds_info SET qds_count = $qds_count-1, last_qds_date = '$date' WHERE uid = '$uid'");

            return array(
                'code' => 1,
                'page_id' => $output['page_id']
            );
        }

        return array(
            'code' => 0,
            'page_id' => 0
        );
    }

    public function notice_qds($appkey, $notify_type, $page_serial, $comment_id, $user_name, $sex, $avatar, $content, $fee, $create_time, $sign)
    {
        if ($notify_type == 1)
        {
            $data = $this->db->query("SELECT * FROM a_game_qds_list WHERE comment_id = $comment_id")->result_array();
            if (!empty($data))
                return 0;

            $data = array(
                'comment_id' => $comment_id,
                'page_serial' => $page_serial,
                'comment_nickname' => $user_name,
                'comment_sex' => 0,
                'comment_headimgurl' => $avatar,
                'comment_text' => $content,
                'comment_fuli' => $fee,
                'date' => $create_time
            );
            $this->db->insert('a_game_qds_list', $data);

            $this->db->query("UPDATE a_game_qds_progressive SET feed = feed + $fee WHERE page_serial = '$page_serial'");

            // 补发
            $data = $this->db->query("SELECT uid, feed, state FROM a_game_qds_progressive WHERE page_serial = '$page_serial'")->result_array();
            if (empty($data))
                return 0;
            $data = $data[0];
            $uid = $data['uid'];
            $state = $data['state'];
            if ($state == 2)
            {
                $data = array(
                    'uid' => $uid,
                    'title' => '好友求打赏奖励结算补发',
                    'text' => '恭喜获得求打赏福利鸡补发奖励(有效期30天)，点击领取即可参与获取更多福利鸡。',
                    'feed' => $fee,
                    'send_date' => date('Y-m-d H:i:s', time())
                );
                $this->db->insert('compensate', $data);
            }
        }
        else if ($notify_type == 2)
        {
            $data = $this->db->query("SELECT uid, feed, state FROM a_game_qds_progressive WHERE page_serial = '$page_serial'")->result_array();
            if (empty($data))
                return 0;
            $data = $data[0];
            $uid = $data['uid'];
            $feed = $data['feed'];
            $state = $data['state'];
            if ($state != 2)
            {
                if ($feed > 0)
                {
                    $data = array(
                        'uid' => $uid,
                        'title' => '好友求打赏奖励结算',
                        'text' => '恭喜获得求打赏福利鸡奖励(有效期30天)，点击领取即可参与获取更多福利鸡。',
                        'feed' => $feed,
                        'send_date' => date('Y-m-d H:i:s', time())
                    );
                    $this->db->insert('compensate', $data);
                }

                $this->db->query("UPDATE a_game_qds_progressive SET state = 2 WHERE page_serial = '$page_serial'");
            }
        }
        return 0;
    }

    public function get_qds_sign($data)
    {
        ksort($data);

        $str = '';
        $index = 0;
        foreach ($data as $k=>$v)
        {
            $str .= $k.'='.$v.'&';
            $index += 1;
        }
        $str .= 'key='.'62939128422df8d799a4ffb833a45fda';
        $str = strtoupper(md5($str));
        return $str;
    }
    public function send_post($url, $array)
    {
        $post_data = json_encode($array);

        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // https请求 不验证证书和hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json; charset=utf-8",
                "Content-Length: " . strlen($post_data))
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
}