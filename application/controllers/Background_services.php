<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2018/5/19
 * Time: 上午10:48
 */

class Background_services extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('wxinfo_model');
        $this->load->model('ugame_model');
        $this->load->model('wakuang_model');
        $this->load->model('message_model');
        $this->load->model('vc_model');
        $this->load->model('compensate_model');
        $this->load->model('exchange_model');
        $this->load->model('activity_model');
        $this->load->model('wallet_model');
    }

    public function coin_config_set_sum_day()
    {
        $get = $this->input->get();
        $sum_day = $get['sum_day'];
        $this->db->query("UPDATE coin_config SET sum_day = $sum_day where name = 'xpot'");
        $is_update_sum_day = $this->db->affected_rows();
        if (!$is_update_sum_day)
        {
            echo "更新出错!\n";
            die;
        }

        $coin_config = $this->db->query("SELECT * FROM coin_config")->result_array();
        echo json_encode($coin_config);
    }

    public function get_users_count()
    {
        echo "<pre>";
        echo "".date("Y-m-d H:i:s", time())." 实时数据：";
        echo "</pre>";

        $now_time = time();
        $now_date = date("Y-m-d", $now_time);
        $today_0_time = strtotime($now_date);

        echo "<pre>";
        $count = $this->db->query("SELECT COUNT(*) FROM ugame")->result_array();
        $count = $count[0]['COUNT(*)'];
        echo "用户总计：".strval($count)."人，";

        $count = $this->db->query("SELECT COUNT(*) FROM ugame WHERE logintime >= $today_0_time")->result_array();
        $daily_life_today = $count[0]['COUNT(*)'];
        echo "今日活跃用户：".strval($daily_life_today)."人，";
        echo "</pre>";

        /******************************************/

        echo "<pre>";
        echo "其中:";
        echo "</pre>";


        echo "<pre>";
        $count = $this->db->query("SELECT COUNT(*) FROM ugame WHERE channel_id = 0")->result_array();
        $count = $count[0]['COUNT(*)'];
        echo "　　官方渠道：".strval($count)."人，";

        $count2 = $this->db->query("SELECT COUNT(*) FROM ugame WHERE channel_id = 0 and logintime >= $today_0_time")->result_array();
        $count2 = $count2[0]['COUNT(*)'];
        echo "其中，今日活跃用户：".strval($count2)."人。";
        echo "</pre>";
        /******************************************/

        echo "<pre>";
        $count = $this->db->query("SELECT COUNT(*) FROM ugame WHERE channel_id = 1")->result_array();
        $count = $count[0]['COUNT(*)'];
        echo "　　动漫之家渠道：".strval($count)."人，";

        $count2 = $this->db->query("SELECT COUNT(*) FROM ugame WHERE channel_id = 1 and logintime >= $today_0_time")->result_array();
        $count2 = $count2[0]['COUNT(*)'];
        echo "其中，今日活跃用户：".strval($count2)."人。";
        echo "</pre>";
        /******************************************/

        $count = $this->db->query("SELECT COUNT(*) FROM ugame WHERE channel_id = 2")->result_array();
        $count = $count[0]['COUNT(*)'];
        echo "<pre>";
        echo "　　信息流渠道：".strval($count)."人，";

        $count2 = $this->db->query("SELECT COUNT(*) FROM ugame WHERE channel_id = 2 and logintime >= $today_0_time")->result_array();
        $count2 = $count2[0]['COUNT(*)'];
        echo "其中，今日活跃用户：".strval($count2)."人。";
        echo "</pre>";
    }

    public function by_pay_purchase_record()
    {
        $data = $this->db->query("select w.nickname, by.foods_type, by.items, by.coin_name, by.coin, by.date from wallet_by_pay_purchase_record `by` left join wx_info w on w.openid = by.uid where by.state = 1 order by by.id desc")->result_array();

        $index = 0;
        foreach ($data as $k=>$v)
        {
            $index += 1;
            $nickname = $v['nickname'];
            $foods_type = $v['foods_type'];
            $items = $v['items'];
            $coin_name = $v['coin_name'];
            $coin = $v['coin'];
            $date = $v['date'];

            echo "<pre>";
            echo '<'.$index.'> '.$date.' '.$nickname.' '.$foods_type.' 数量：'.$items.' '.round($coin, 5).$coin_name;
            echo "</pre>";
        }
    }

    public function user_statistics_record()
    {
        $last_date = date("Y-m-d", time() - 86400);
        $date = date("Y-m-d", time());
        $datetime = date("Y-m-d H:i:s", time());

        $count_yesterday= $this->db->query("SELECT user_count FROM user_statistics WHERE date = '$last_date'")->row_array();
        $user_count_yesterday = $count_yesterday['user_count'];

        $count = $this->db->query("SELECT COUNT(*) FROM wx_info")->row_array();
        $user_count = $count['COUNT(*)'];

        $user_new = $user_count - $user_count_yesterday;

        $now_time = time();
        $now_date = date("Y-m-d", $now_time);
        $today_0_time = strtotime($now_date);
        $count = $this->db->query("SELECT COUNT(*) FROM ugame WHERE logintime >= $today_0_time")->result_array();
        $daily_life = $count[0]['COUNT(*)'];

        $time_2_days = $today_0_time - 172800;
        $count = $this->db->query("SELECT COUNT(*) FROM ugame WHERE logintime >= $time_2_days")->result_array();
        $daily_life_2_days = $count[0]['COUNT(*)'];

        $time_3_days = $today_0_time - 259200;
        $count = $this->db->query("SELECT COUNT(*) FROM ugame WHERE logintime >= $time_3_days")->result_array();
        $daily_life_3_days = $count[0]['COUNT(*)'];

        $time_7_days = $today_0_time - 604800;
        $count = $this->db->query("SELECT COUNT(*) FROM ugame WHERE logintime >= $time_7_days")->result_array();
        $daily_life_7_days = $count[0]['COUNT(*)'];

        $data['date'] = $date;
        $data['user_count'] = $user_count;
        $data['user_new'] = $user_new;
        $data['daily_life'] = $daily_life;
        $data['daily_life_2_days'] = $daily_life_2_days;
        $data['daily_life_3_days'] = $daily_life_3_days;
        $data['daily_life_7_days'] = $daily_life_7_days;
        $data['datetime'] = $datetime;
        $this->db->insert('user_statistics', $data);
    }

    public function register_info()
    {
        $hour = intval(date("H", time()));

        if ($hour < 7) die;

        for ($i=0; $i<4; $i+=1)
        {
            $array['openid'] = 'oKIlh1'.substr(strtolower(base64_encode(md5(time() + rand(99999999, 9999999999)))), 0, 22);
            $array['nickname'] = '';
            $array['sex'] = rand(1, 2);

            if ($this->wxinfo_model->register_wx_applet_uinfo($array))
            {
                $this->ugame_model->register_ugame($array['openid']);
            }
            echo $array['openid'].'  OK';
        }
    }

    public function insert_hrl_message_info()
    {
//        if ($this->getIp() != '39.107.241.47')
//            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['content']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $content = $post['content'];
        $open_type = $post['open_type'];
        $open_url = $post['open_url'];
        $state = $post['state'];

        $array = array(
            'content' => $content,
            'open_type' => $open_type,
            'open_url' => $open_url,
            'state' => $state
        );
        $this->db->insert('hrl_message', $array);

        $result['code'] = 1;
        echo json_encode($result);
    }

    public function delete_hrl_message_info()
    {
//        if ($this->getIp() != '39.107.241.47')
//            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['id']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $id = $post['id'];

        $this->db->query("DELETE FROM hrl_message WHERE id = $id");

        $result['code'] = 1;
        echo json_encode($result);
    }

    public function reset_notice()
    {
//        if ($this->getIp() != '39.107.241.47')
//            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['notice']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $notice = $post['notice'];

        $sql = "UPDATE config SET key_value = '$notice' WHERE key_name = 'gonggao'";
        $this->db->query($sql);

        $record = array(
            "type" => "公告",
            "date" => strval(date("Y-m-d H:i:s", time()))
        );

        $result['code'] = 1;
        echo json_encode($result);
    }

    public function reset_total_eggs_output_day()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['number']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $number = $post['number'];

        $sql = "UPDATE coin_config SET total_output_day = $number WHERE title = 'XPOT'";
        $this->db->query($sql);

        $record = array(
            "type" => "鸡蛋产出",
            "date" => strval(date("Y-m-d H:i:s", time()))
        );


        $result['code'] = 1;
        echo json_encode($result);
    }

    public function reset_eggs_cost()
    {
//        if ($this->getIp() != '39.107.241.47')
//            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['number']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $number = $post['number'];

        $sql = "UPDATE config SET key_value = '$number' WHERE key_name = 'xpot_cost'";
        $this->db->query($sql);

        $record = array(
            "type" => "鸡蛋价格",
            "date" => strval(date("Y-m-d H:i:s", time()))
        );


        $result['code'] = 1;
        echo json_encode($result);
    }

    public function reset_phone_number()
    {
//        if ($this->getIp() != '39.107.241.47')
//            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['phone_number']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $uid = $post['uid'];
        $phone_number = $post['phone_number'];

        $this->db->query("UPDATE wx_info SET phone_number = '$phone_number' WHERE openid = '$uid'");

        $result['code'] = 1;
        echo json_encode($result);
    }

    public function reset_id_card()
    {
//        if ($this->getIp() != '39.107.241.47')
//            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['fullname']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $uid = $post['uid'];
        $fullname = $post['fullname'];
        $id_card = $post['id_card'];

        $tmp = $this->db->query("select * from wx_info where id_card = '$id_card'")->result_array();
        if (!empty($tmp))
        {
            $tmp = $tmp[0];
            if ($tmp['openid'] != $uid)
            {
                $result['code'] = 16;
                echo json_encode($result);
                die;
            }
        }

        $sql = "UPDATE wx_info SET fullname = '$fullname', id_card = '$id_card' WHERE openid = '$uid'";
        $this->db->query($sql);

        $record = array(
            "type" => "实名认证",
            "date" => strval(date("Y-m-d H:i:s", time()))
        );


        $result['code'] = 1;
        echo json_encode($result);
    }

    public function insert_compensate()
    {
//        if ($this->getIp() != '39.107.241.47')
//            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['uid_array']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $uid_array = $post['uid_array'];
        $title = $post['title'];
        $text = $post['text'];
        $cny = $post['cny'];
        $xpot = $post['eggs'];
        $fuli = $post['hen'];
        $dy_fuli = isset($post['dy_hen']) ? $post['dy_hen'] : 0;
        $dy_fuli_tov = isset($post['dy_hen_tov']) ? $post['dy_hen_tov'] : 0;
        $send_date = date('Y-m-d H:i:s', time());
        if ($xpot > 50 || $fuli > 10)
        {
            die;
        }
        $sql = "INSERT INTO compensate(`uid`,`title`,`text`,`cny`,`xpot`,`fuli`,`dy_fuli`,`dy_fuli_tov`,`send_date`) VALUES ";

        $uid_array = json_decode($uid_array, true);
        $index = 0;
        foreach ($uid_array as $k=>$v)
        {
            $sql .= '(' . "'" . $v . "'" . ',' . "'" . $title . "'" . ',' . "'" . $text . "'" . ',' . $cny . ',' . $xpot . ',' . $fuli . ',' . $dy_fuli . ',' . $dy_fuli_tov . ',' . "'" . $send_date . "'" . '),';
            $index += 1;
        }
        $sql = rtrim($sql, ',');
        $this->db->query($sql);
//        echo $sql;
        $record = array(
            "type" => "邮件",
            "date" => strval(date("Y-m-d H:i:s", time()))
        );


        $result['code'] = 1;
        echo json_encode($result);
    }

    public function insert_compensate_feed()
    {
//        if ($this->getIp() != '39.107.241.47')
//            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['uid']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $uid = $post['uid'];
        $title = $post['title'];
        $text = $post['text'];
        $feed = $post['feed'];
        $send_date = date('Y-m-d H:i:s', time());

        $this->db->insert('compensate', array(
            'uid' => $uid,
            'title' => $title,
            'text' => $text,
            'feed' => $feed,
            'send_date' => $send_date
        ));
//        echo $sql;
        $record = array(
            "type" => "邮件",
            "date" => strval(date("Y-m-d H:i:s", time()))
        );


        $result['code'] = 1;
        echo json_encode($result);
    }

    public function transfer_accounts_feed()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        $uid = $post['uid'];
        $other_uid = $post['other_uid'];
        $feed = $post['feed'];

        $send_date = date('Y-m-d H:i:s', time());

        $ugame = $this->db->query("select feed from ugame where uid = '$uid'")->row_array();
        if ($ugame['feed'] < $feed)
        {
            return 0;
        }
        $this->db->query("update ugame set feed = feed - $feed where uid = '$uid'");

        $info = $this->db->query("select nickname from wx_info where openid = '$uid'")->row_array();
        $nickname = $info['nickname'];

        $this->db->insert('compensate', array(
            'uid' => $other_uid,
            'title' => '饲料赠送',
            'text' => '用户【'.$nickname.'】赠送了'.$feed.'袋饲料给您，请查收！',
            'feed' => $feed,
            'send_date' => $send_date
        ));

        $result['code'] = 1;
        echo json_encode($result);
    }

    public function insert_circle_message()
    {
//        if ($this->getIp() != '39.107.241.47')
//            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['content']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $content = $post['content'];
        $image_urls = $post['image_urls'];
        $hyperlink_title = $post['hyperlink_title'];
        $hyperlink_image = $post['hyperlink_image'];
        $hyperlink_url = $post['hyperlink_url'];
        $top_pri = $post['top_pri'];
        $is_ess = $post['is_ess'];
        $post['send_date'] = strval(date("Y-m-d H:i:s", time()));
        $post['uid'] = "ovBB91enGNa4-cdqXZIJm7vuJTDo";
        $this->db->insert('circle_message', $post);

        $result['code'] = 1;
        echo json_encode($result);
    }

    // 删帖
    public function delete_circle_message()
    {
//        if ($this->getIp() != '39.107.241.47')
//            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['message_id']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $message_id = $post['message_id'];

        $this->db->query("INSERT INTO circle_message_recycle(message_id, uid, content, image_urls, send_date, last_reply_date, praise_count, reply_count, top_pri, is_ess, hyperlink_title, hyperlink_image, hyperlink_url, plate_id) SELECT * FROM circle_message WHERE message_id = $message_id");
        $this->db->query("DELETE FROM circle_message WHERE message_id = $message_id");
        $this->db->query("DELETE FROM circle_praise WHERE message_id = $message_id");
        $this->db->query("DELETE FROM circle_reply WHERE message_id = $message_id");

        $result['code'] = 1;
        echo json_encode($result);
    }

    // 删回复
    public function delete_reply_circle_message()
    {
//        if ($this->getIp() != '39.107.241.47')
//            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['reply_id']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $reply_id = $post['reply_id'];
        $this->db->query("INSERT INTO circle_reply_recycle(reply_id, uid, message_id, reply_uid, content, send_date, praise_count, plate_id) SELECT * FROM circle_reply WHERE reply_id = $reply_id");
        $this->db->query("DELETE FROM circle_reply WHERE reply_id = $reply_id");
        $result['code'] = 1;
        echo json_encode($result);
    }

    // 设置指定等级
    public function set_circle_message_top()
    {
//        if ($this->getIp() != '39.107.241.47')
//            die;

        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['message_id']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $message_id = $post['message_id'];
        $top_pri = $post['top_pri'];

        $this->db->query("UPDATE circle_message SET top_pri = $top_pri WHERE message_id = $message_id");

        $result['code'] = 1;
        echo json_encode($result);
    }

    // 加精
    public function set_circle_message_ess()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['message_id']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $message_id = $post['message_id'];
        $is_ess = $post['is_ess'];

        $this->db->query("UPDATE circle_message SET is_ess = $is_ess WHERE message_id = $message_id");

        $result['code'] = 1;
        echo json_encode($result);
    }

    // 禁言 解除禁言
    public function user_forbidden_words()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['uid']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $uid = $post['uid'];
        $is_forbidden = $post['is_forbidden'];

        if ($is_forbidden)
        {
            $this->db->query("insert ignore into user_blacklist(`uid`) value('$uid')");
        }
        else
        {
            $this->db->query("delete from user_blacklist where uid = '$uid'");
        }

        $result['code'] = 1;
        echo json_encode($result);
    }

    // 插入推广app信息
    public function insert_app_info()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['app_name']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $id = $post['id'];
        $app_name = $post['app_name'];
        $icon_url = $post['icon_url'];
        $ios_download_url = $post['ios_download_url'];
        $android_download_url = $post['android_download_url'];
        $awaken_url = $post['awaken_url'];
        $ios_bundle_id = $post['ios_bundle_id'];
        $android_bundle_id = $post['android_bundle_id'];
        $details_text = $post['details_text'];
        $open = $post['open'];
        $type = $post['type'];
        $value = $post['value'];
        $coin_type = $post['coin_type'];
        $show_os = $post['show_os'];
        $is_test = $post['is_test'];

        $this->db->insert('a_app_download_info', $post);

        $result['code'] = 1;
        echo json_encode($result);
    }

    // 删除推广app信息
    public function delete_app_info()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['app_name']))
        {
            $result['code'] = 0;
            echo json_encode($result);
            die;
        }
        $app_name = $post['app_name'];
        $id = $post['id'];

        $this->db->query("delete from a_app_download_info where id = $id and app_name = '$app_name'");

        $result['code'] = 1;
        echo json_encode($result);
    }



    public function consume_eggs()
    {
        $post = $this->input->post();
        $post = $post['data'];
        $post = json_decode(AES::getInstance()->decrypt_pass($post, "ddz1234567890ddz"), true);
        if (!isset($post['eggs']))
        {
            $result['code'] = 0;
            $result['msg'] = '参数异常';
            echo json_encode($result);
            die;
        }
        $uid = $post['uid'];
        $eggs = $post['eggs'];
        $type = isset($post['type']) ? $post['type'] : '购买商品抵用';
        $timestamp = $post['timestamp'];

        if ($eggs <= 0)
        {
            $result['code'] = 0;
            $result['msg'] = 'eggs不允许负数';
            echo json_encode($result);
            die;
        }

        if ($timestamp < time() - 10)
        {
            $result['code'] = 0;
            $result['msg'] = '请求已失效';
            echo json_encode($result);
            die;
        }

        $eggs_balance = $this->ugame_model->get_coins_num($uid, "xpot");
        if ($eggs_balance < $eggs)
        {
            $result['code'] = 0;
            $result['msg'] = '鸡蛋余额不足';
            echo json_encode($result);
            die;
        }

        $this->ugame_model->add_coins($uid, "xpot", -$eggs);

        $record = array(
            "uid" => $uid,
            "xpot" => $eggs,
            "date" => strval(date("Y-m-d H:i:s", time())),
            "type" => $type
        );
        $this->db->insert('xpot_consume', $record);

        $result['code'] = 1;
        $result['eggs_balance'] = $this->ugame_model->get_coins_num($uid, "xpot");
        echo json_encode($result);
    }

    // 获取用户鸡蛋
    public function get_user_eggs()
    {
        $post = $this->input->get();
        $uid = $post['uid'];
        $timestamp = $post['timestamp'];

        $result['code'] = 1;
        $result['eggs'] = $this->ugame_model->get_coins_num($uid, "xpot");
        echo json_encode($result);
    }

    // 下载成功回调
    public function callback_app_is_install_not_hb()
    {
        $post = $this->input->get();
        $uid = $post['uid'];
        $app_name = $post['app_name'];
        $os = $post['os'];

        $this->activity_model->callback_app_is_install($uid, $app_name, $os);
        $result['code'] = 1;
        echo json_encode($result);
    }


    // 下载成功回调
    public function get_user_count_total()
    {
        $post = $this->input->post();
        $timestamp = $post['timestamp'];
        $sign = $post['sign'];

        $count1 = $this->ugame_model->get_config('user_count');
        $count2 = $this->ugame_model->get_config('user_count_xpot');
        $result['code'] = 1;
        $result['user_count'] = intval($count1) + intval($count2);
        echo json_encode($result);
    }

    public function test()
    {
        echo 'OK';
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