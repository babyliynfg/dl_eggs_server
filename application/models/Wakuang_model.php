<?php
/**
 * Created by PhpStorm.
 * User: wps
 * Date: 2018/5/2
 * Time: 18:10
 */

class Wakuang_model extends MY_Model{

    function __construct()
    {
        parent::__construct();
        $this->load->model('compensate_model');
        $this->load->model('ugame_model');
    }

    //获取某个用户的领取矿记录
    public function get_lingqu_record($uid)
    {
        $result = $this->db->query("select * from wakuang_record where uid = '$uid' order by find_time desc ")->result_array();
        return $result;
    }

    public function get_kw_list($uid)
    {
        return $this->db->query("select id, ore_type_id, ore_num from wakuang where uid = '$uid' and ore_status = 0")->result_array();
    }

    public function receive_kw($uid, $id)
    {
        $kw_info = $this->db->query("select * from wakuang where id = $id")->result_array();
        if (empty($kw_info))
            return 0;
        $kw_info = $kw_info[0];
        if ($kw_info['ore_type_id'] != 1)
            return 0;
        if ($kw_info['ore_status'] > 0)
            return 0;
        if ($kw_info['uid'] != $uid)
            return 0;
        $number = $kw_info['ore_num'];
        $owner_uid = $this->db->query("SELECT owner_uid FROM c_h_staff WHERE uid = '$uid'")->row_array();
        if (isset($owner_uid))
        {
            $owner_uid = $owner_uid['owner_uid'];
            $user_info = $this->db->query("SELECT * FROM ugame WHERE uid = '$owner_uid'")->row_array();
            if (isset($user_info))
            {
                $all_xpot = $user_info['xpot'] + $user_info['total_sell_xpot'];
                $fuli = $user_info['fuli'];
                $make_info = $this->ugame_model->get_make_level_info($all_xpot, $fuli);
                $tax_pro = $make_info['tax_pro'];
//            $tax_pro = 0.03;
                $tax = round($number * $tax_pro, 5);
                $tax = max($tax, 0.00001);
                $this->db->query("update c_h_staff set tax = tax + $tax where uid = '$uid'");
            }
        }


        $this->db->query("update wakuang set ore_status = 1 where id = $id");
        $result = $this->db->affected_rows();
        if (!$result) return 0;

        $this->db->query("update ugame set xpot = xpot + $number where uid = '$uid'");
        $result = $this->db->affected_rows();
        if (!$result) return 0;

        return $number;
    }

    public function receive_kw_red($uid, $id, $is_double)
    {
        $kw_info = $this->db->query("select * from wakuang where id = $id")->result_array();
        if (empty($kw_info))
            return 0;
        $kw_info = $kw_info[0];
        if ($kw_info['ore_type_id'] != 3)
            return 0;
        if ($kw_info['ore_status'] > 0)
            return 0;
        if ($kw_info['uid'] != $uid)
            return 0;
        $number = $kw_info['ore_num'];

        if ($is_double == 0)
        {
            $this->db->query("update wakuang set ore_status = -1 where id = $id");
            return 0;
        }

        $ore_status = $is_double;
        $this->db->query("update wakuang set ore_status = $ore_status where id = $id");
        $result = $this->db->affected_rows();
        if (!$result) return 0;

        $number = $number * $ore_status;
        $this->db->query("update ugame set xpot = xpot + $number where uid = '$uid'");
        $result = $this->db->affected_rows();
        if (!$result) return 0;

        return $number;
    }

//    public function receive_kw_cny($uid, $id)
//    {
//        $kw_info = $this->db->query("select * from wakuang where id = $id")->result_array();
//        if (empty($kw_info))
//            return 0;
//        $kw_info = $kw_info[0];
//        if ($kw_info['ore_type_id'] != 2)
//            return 0;
//        if ($kw_info['ore_status'] == 1)
//            return 0;
//        if ($kw_info['uid'] != $uid)
//            return 0;
//        $number = $kw_info['ore_num'];
//
//        $this->db->query("update wakuang set ore_status = 1 where id = $id");
//        $result = $this->db->affected_rows();
//        if (!$result) return 0;
//
//        $this->db->query("update ugame set cny = cny + $number where uid = '$uid'");
//        $result = $this->db->affected_rows();
//        if (!$result) return 0;
//
//        return $number;
//    }

//    public function receive_kw_hb($uid, $id)
//    {
//        $kw_info = $this->db->query("select * from wakuang where id = $id")->result_array();
//        if (empty($kw_info))
//            return 0;
//        $kw_info = $kw_info[0];
//        if ($kw_info['ore_type_id'] < 100 || $kw_info['ore_type_id'] > 200)
//            return 0;
//        if ($kw_info['ore_status'] == 1)
//            return 0;
//        if ($kw_info['uid'] != $uid)
//            return 0;
//        $number = $kw_info['ore_num'];
//
//        if ($kw_info['ore_type_id'] == 101)
//        {
//
//        }
//        else if ($kw_info['ore_type_id'] == 102)
//
//        {
//
//        }
//        else
//        {
//            return 0;
//        }
//
//        $this->db->query("update wakuang set ore_status = 1 where id = $id");
//        $result = $this->db->affected_rows();
//        if (!$result) return 0;
//
//        $this->db->query("update ugame set cny = cny + $number where uid = '$uid'");
//        $result = $this->db->affected_rows();
//        if (!$result) return 0;
//
//        return $number;
//    }

    public function steal_kw($uid, $other_uid, $id, $friend_count)
    {
        $kw_info = $this->db->query("select * from wakuang where id = $id and uid = '$other_uid' and be_stolen_times = 0")->row_array();
        if (!isset($kw_info))
            return 0;

        $steal_kw_count = $this->db->query("select steal_kw_count from everyday_user_record where uid = '$uid'")->row_array();
        if (!isset($steal_kw_count))
        {
            $this->db_w()->insert('everyday_user_record', array("uid" => $uid));
            $steal_kw_count = 0;
        }
        else
        {
            $steal_kw_count = $steal_kw_count['steal_kw_count'];
        }

        if ($steal_kw_count < $friend_count * 72 * 0.1)
        {
            $pro = 0.16;
        }
        else if ($steal_kw_count < $friend_count * 72 * 0.3)
        {
            $pro = 0.08;
        }
        else
        {
            $pro = 0.04;
        }

        $stole = $kw_info['ore_num'] * $pro;

        $this->db->query("update ugame set xpot = xpot + $stole where uid = '$uid'");
        $this->db->query("update wakuang set ore_num = ore_num - $stole, be_stolen_times = be_stolen_times + 1 where id = $id");
        $this->db->query("update everyday_user_record set steal_kw_count = steal_kw_count + 1 where uid = '$uid'");


        $nickname = $this->db->query("select nickname from wx_info where openid = '$uid'")->row_array();
        $nickname = $nickname['nickname'];

        $text = '你的好友【'.$nickname.'】偷了你'.$stole.'斤鸡蛋~';
        $this->compensate_model->inset_compensate_none($other_uid, "系统提示", $text);

        return $stole;
    }

    public function get_can_be_stolen_kw_list($other_uid)
    {
        return $this->db->query("select id, uid, ore_type_id, ore_num from wakuang where uid = '$other_uid' and ore_status = 0 and be_stolen_times = 0 and ore_type_id = 1")->result_array();
    }
}