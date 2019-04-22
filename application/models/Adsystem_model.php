<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2019/3/13
 * Time: 4:32 PM
 */

class Adsystem_model extends MY_Model
{
    function __construct()
    {
        parent::__construct();
        $this->load->model('compensate_model');
    }

    public function insert_ad_info($data)
    {
        $data['refuse_times'] = $data['quota_total'] * 2;
        return intval($this->db->insert('ad_info', $data));
    }

    public function get_ad_info_list($start, $count)
    {
        return $this->db_r()->query("select * from ad_info where finish_quota_total < quota_total ORDER BY task_id DESC LIMIT $start, $count")->result_array();
    }

    public function get_my_ad_info_list($uid, $start, $count)
    {
        return $this->db_r()->query("select * from ad_info where uid = '$uid' ORDER BY task_id DESC LIMIT $start, $count")->result_array();
    }

    public function get_ad_finish_info($uid, $task_id)
    {
        $data = $this->db_r()->query("select * from ad_finish_list WHERE uid = '$uid' and task_id = $task_id")->row_array();
        if (!isset($data))
            return array();
        return $data;
    }

    public function insert_ad_finish_info($data)
    {
        $uid = $data['uid'];
        $task_id = $data['task_id'];
        $this->db->query("DELETE FROM ad_finish_list WHERE uid = '$uid' and task_id = $task_id and state != 1");
        $rows = $this->db->affected_rows();
        if ($rows == 0)
        {
            $is_finish = $this->db->query("select * from ad_info where finish_quota_total >= quota_total and task_id = $task_id")->row_array();
            if (isset($is_finish))
                return 0;

            $this->db->query("update ad_info set finish_quota_total = finish_quota_total + 1 WHERE task_id = $task_id");
        }

        $data['begin_date'] = date("Y-m-d H:i:s", time());
        return intval($this->db->insert('ad_finish_list', $data));
    }

    public function do_ad_info($data)
    {
        $uid = $data['uid'];
        $task_id = $data['task_id'];
        $image_url1 = $data['image_url1'];
        $image_url2 = $data['image_url2'];
        $info1 = $data['info1'];
        $info2 = $data['info2'];
        $finish_date = date("Y-m-d H:i:s", time());
        $result = $this->db->query("UPDATE ad_finish_list SET image_url1 = '$image_url1', image_url2 = '$image_url2', info1 = '$info1', info2 = '$info2', finish_date = '$finish_date' WHERE uid = '$uid' and task_id = $task_id");

        return intval($result);
    }

    public function get_ad_finish_info_list($uid, $start, $count)
    {
        return $this->db_r()->query("select f.uid as finish_uid, f.image_url1 as finish_image_url1, f.image_url2 as finish_image_url2, f.info1 as finish_info1, f.info2 as finish_info2, f.finish_date, f.extra_message, i.* from ad_finish_list f left join ad_info i on i.task_id = f.task_id WHERE f.uid = '$uid' and f.state = 1 ORDER BY id DESC LIMIT $start, $count")->result_array();
    }

    public function get_examine_ad_finish_info_list($uid, $start, $count)
    {
        return $this->db_r()->query("select f.uid as finish_uid, f.image_url1 as finish_image_url1, f.image_url2 as finish_image_url2, f.info1 as finish_info1, f.info2 as finish_info2, f.finish_date, f.extra_message, i.* from ad_finish_list f left join ad_info i on i.task_id = f.task_id WHERE f.uid = '$uid' and f.state = 0 and finish_date != '' ORDER BY id DESC LIMIT $start, $count")->result_array();
    }

    public function get_uncommitted_ad_finish_info_list($uid, $start, $count)
    {
        return $this->db_r()->query("select f.uid as finish_uid, f.image_url1 as finish_image_url1, f.image_url2 as finish_image_url2, f.info1 as finish_info1, f.info2 as finish_info2, f.finish_date, f.extra_message, i.* from ad_finish_list f left join ad_info i on i.task_id = f.task_id WHERE f.uid = '$uid' and f.state = 0 and finish_date = '' ORDER BY id DESC LIMIT $start, $count")->result_array();
    }

    public function get_refuse_ad_finish_info_list($uid, $start, $count)
    {
        return $this->db_r()->query("select f.uid as finish_uid, f.image_url1 as finish_image_url1, f.image_url2 as finish_image_url2, f.info1 as finish_info1, f.info2 as finish_info2, f.finish_date, f.extra_message, i.* from ad_finish_list f left join ad_info i on i.task_id = f.task_id WHERE f.uid = '$uid' and f.state = 2 ORDER BY id DESC LIMIT $start, $count")->result_array();
    }

    public function get_owner_examine_ad_finish_list($uid, $start, $count)
    {
        return $this->db_r()->query("select f.uid as finish_uid, w.nickname as finish_nickname, w.headimgurl as finish_headimgurl, f.image_url1 as finish_image_url1, f.image_url2 as finish_image_url2, f.info1 as finish_info1, f.info2 as finish_info2, f.finish_date, f.extra_message, f.state, i.* from ad_info i right join ad_finish_list f on f.task_id = i.task_id left join wx_info w on w.openid = f.uid where f.state = 0 and i.uid = '$uid' and f.finish_date != '' ORDER BY f.id DESC LIMIT $start, $count")->result_array();
    }

    public function get_owner_examine_finish_ad_finish_list($uid, $start, $count)
    {
        return $this->db_r()->query("select f.uid as finish_uid, w.nickname as finish_nickname, w.headimgurl as finish_headimgurl, f.image_url1 as finish_image_url1, f.image_url2 as finish_image_url2, f.info1 as finish_info1, f.info2 as finish_info2, f.finish_date, f.extra_message, f.state, i.* from ad_info i right join ad_finish_list f on f.task_id = i.task_id left join wx_info w on w.openid = f.uid where f.state != 0 and i.uid = '$uid' ORDER BY f.id DESC LIMIT $start, $count")->result_array();
    }

    public function owner_examine_ad_info($uid, $task_id, $other_uid, $state, $extra_message)
    {
        $data = $this->db_r()->query("select * from ad_info WHERE uid = '$uid' and task_id = $task_id")->row_array();
        if (!isset($data))
            return 0;
        if ($state == 0 && $data['refuse_times'] <= 0)
            return 3001;

        $data2 = $this->get_ad_finish_info($other_uid, $task_id);
        if (!isset($data2))
            return 0;

        $state = $state ? 1 : 2;
        $this->db->query("update ad_finish_list set state = $state, extra_message = '$extra_message' WHERE uid = '$other_uid' and task_id = $task_id");

        if ($state == 1)
        {
            $title = $data['title'];
            $feed = $data['reward_coin_once'];
            $this->compensate_model->inset_compensate_feed($other_uid, "广告系统", "由于您在完成广告【.$title.】，特发放".$feed."饲料给您，请查收…", $feed);
            return 1;
        }

        $this->db->query("update ad_info set finish_quota_total = finish_quota_total - 1, refuse_times = refuse_times - 1 WHERE uid = '$uid' and task_id = $task_id");


        return 1;
    }

    // 处理用户未提交
    public function task_handle_uncommitted()
    {
        $date = date("Y-m-d H:i:s", time() - 3600);
        $data = $this->db->query("SELECT count(*) as num, task_id FROM ad_finish_list WHERE state = 0 and begin_date < '$date' and finish_date = '' group by task_id")->result_array();

        foreach ($data as $k => $item) {

            $task_id = $item['task_id'];
            $num = $item['num'];
            $this->db->query("update ad_info set finish_quota_total = finish_quota_total - $num WHERE task_id = $task_id");
        }
        $this->db->query("delete from ad_finish_list WHERE state = 0 and begin_date < '$date' and finish_date = ''");
        echo '[task_handle_uncommitted]  '.json_encode($data);
    }

    // 处理用户未审核
    public function task_handle_no_audit()
    {
        $date = date("Y-m-d H:i:s", time() - 86400 * 2);
        $data = $this->db->query("SELECT f.uid, i.title, i.reward_coin_once FROM ad_finish_list f LEFT JOIN ad_info i ON i.task_id = f.task_id WHERE f.state = 0 and f.finish_date < '$date' and f.finish_date != ''")->result_array();
        foreach ($data as $k => $item) {

            $uid = $item['uid'];
            $title = $item['title'];
            $feed = $item['reward_coin_once'];
            $this->compensate_model->inset_compensate_feed($uid, "广告系统", "由于您在完成广告【.$title.】，特发放".$feed."饲料给您，请查收…", $feed);
        }
        $this->db->query("update ad_finish_list set state = 1 WHERE state = 0 and finish_date < '$date' and finish_date != ''");
        echo '[task_handle_no_audit]  '.json_encode($data);

    }
}