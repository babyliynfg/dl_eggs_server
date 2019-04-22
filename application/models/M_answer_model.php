<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2018/12/6
 * Time: 4:27 PM
 */

class M_answer_model extends MY_Model{

    public function get_my_answer_info($uid)
    {
        $row = $this->db_r()->query("select * from small_answer_ranking where uid = '$uid'")->row_array();
        if (isset($row))
        {
            $row2 = $this->db_r()->query("select * from small_answer where uid = '$uid'")->row_array();
            $row['surplus_times'] = $row2['surplus_times'];
            return $row;
        }
        $row = $this->db_r()->query("select * from small_answer where uid = '$uid'")->row_array();
        if (!isset($row))
        {
            $row['uid'] = $uid;
            $row['score'] = 0;
            $row['surplus_times'] = 5;
            $this->db_w()->insert("small_answer", $row);
        }
        $row['id'] = 0;
        return $row;
    }

    public function get_answer_ranking()
    {
        return $this->db_r()->query("select * from small_answer_ranking order by id limit 0, 20")->result_array();
    }

    public function set_answer_bingo($uid, $timestamp)
    {
        $this->db_w()->query("update small_answer set score = score + 1, answer_times = answer_times + 1 where uid = '$uid'");
        return 1;
    }

    public function set_answer_error($uid, $timestamp)
    {
        $this->db_w()->query("update small_answer set surplus_times = surplus_times - 1, error_times = error_times + 1, answer_times = answer_times + 1 where uid = '$uid'");
        return $this->get_surplus_times($uid);
    }

    public function answer_share_times_change($uid)
    {
        $row = $this->db_r()->query("select * from everyday_user_record where uid = '$uid'")->row_array();
        if (!isset($row))
        {
            $this->db_w()->insert('everyday_user_record', array("uid" => $uid, 'answer_share' => 1));
        }
        else if ($row['answer_share'] == 1)
        {
            return 0;
        }
        $this->db_w()->query("update everyday_user_record set answer_share = 1 where uid = '$uid'");
        $this->db_w()->query("update small_answer set surplus_times = surplus_times + 1, user_share_count = user_share_count + 1 where uid = '$uid'");
        return $this->get_surplus_times($uid);
    }

    public function answer_eggs_times_change($uid)
    {
//        return 0;

        $eggs = 0.8;

        $row = $this->db_r()->query("select xpot from ugame where uid = '$uid'")->row_array();
        if ($row['xpot'] < $eggs)
        {
            return 0;
        }
        $this->db_w()->query("update ugame set xpot = xpot - $eggs where uid = '$uid'");
        $this->db_w()->query("update small_answer set surplus_times = surplus_times + 1, user_eggs_count = user_eggs_count + 1 where uid = '$uid'");
        return $this->get_surplus_times($uid);
    }

    private function get_surplus_times($uid)
    {
        $surplus_times = $this->db_w()->query("select surplus_times from small_answer where uid = '$uid'")->row_array();
        $surplus_times = $surplus_times['surplus_times'];
        return $surplus_times;
    }

    public function make_answer_ranking()
    {
        $this->db_w()->query("truncate small_answer_ranking");
        $this->db_w()->query("insert into small_answer_ranking(`uid`, `score`, `nickname`, `headimgurl`) select a.uid, a.score, w.nickname, w.headimgurl from small_answer a left join wx_info w on w.openid = a.uid order by a.score desc limit 0, 1000");
    }
}