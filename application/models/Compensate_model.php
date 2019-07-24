<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2018/5/21
 * Time: 下午2:42
 */

class Compensate_model extends MY_Model{

    //获取用户补偿信息
    public function get_compensate($uid){
        $info = $this->db_r()->query("select * from compensate where uid = '$uid' ORDER BY id DESC")->result_array();
        if (!empty($info))
        {
            return $info;
        }
        else
        {
            return null;
        }
    }
    //领取用户补偿
    public function rec_compensate($uid, $id){
        $info = $this->db_r()->query("select * from compensate where uid = '$uid' and id = $id")->result_array();
        if (!empty($info))
        {
            $info = $info[0];
            $cny_add = $info['cny'];
            $xpot_add = $info['xpot'];
            $feed_add = $info['feed'];
            $fuli_add = $info['fuli'];
            $dy_fuli_add = $info['dy_fuli'];
            $dy_fuli_tov = $info['dy_fuli_tov'];


            if ($cny_add > 0) $this->db->query("update ugame set cny = cny + $cny_add where uid = '$uid'");
            if ($xpot_add > 0.00000) $this->db->query("update ugame set xpot = xpot + $xpot_add where uid = '$uid'");
            if ($feed_add > 0) $this->db->query("update ugame set feed = feed + $feed_add where uid = '$uid'");
            if ($fuli_add > 0) $this->db->query("update ugame set fuli = fuli + $fuli_add where uid = '$uid'");
            if ($dy_fuli_add > 0)
            {
                $this->db->query("update ugame set dy_fuli = dy_fuli + $dy_fuli_add where uid = '$uid'");
                $expiration_date = date('Y-m-d', time() + 86400 * $dy_fuli_tov);
                $data2 = array(
                    'uid' => $uid,
                    'dy_fuli' => $dy_fuli_add,
                    'expiration_date' => $expiration_date,
                );
                $this->db->insert('term_of_validity_dy_fuli', $data2);
            }

            $info['date'] = date('Y-m-d H:i:s', time());

            $this->db->query("delete from compensate_record where id = $id");
            $this->db->insert("compensate_record", $info);

            $this->db->query("delete from compensate where uid = '$uid' and id = $id");

            return True;
        }
        else
        {
            return null;
        }
    }

    // 清除所有无领取内容的邮件
    public function clear_none_compensate($uid)
    {
        $this->db->query("delete from compensate where uid = '$uid' and cny = 0.00 and fuli = 0 and dy_fuli = 0 and xpot = 0 and feed = 0");
    }

    // 创建邮件
    public function inset_compensate_none($uid, $title, $text, $send_uid = 'gm')
    {
        $result = $this->db->insert('compensate', array(
            'uid' => $uid,
            'title' => $title,
            'text' => $text,
            'send_uid' => $send_uid,
            'send_date' => date('Y-m-d H:i:s', time())
        ));
        return intval($result);
    }

    // 创建邮件, 赠送人民币
    public function inset_compensate_cny($uid, $title, $text, $cny)
    {
        $this->db->insert('compensate', array(
            'uid' => $uid,
            'title' => $title,
            'text' => $text,
            'cny' => $cny,
            'send_date' => date('Y-m-d H:i:s', time())
        ));
    }

    // 创建邮件, 赠送鸡蛋
    public function inset_compensate_xpot($uid, $title, $text, $xpot)
    {
        $this->db->insert('compensate', array(
            'uid' => $uid,
            'title' => $title,
            'text' => $text,
            'xpot' => $xpot,
            'send_date' => date('Y-m-d H:i:s', time())
        ));
    }

    // 创建邮件, 赠送母鸡
    public function inset_compensate_fuli($uid, $title, $text, $fuli)
    {
        $this->db->insert('compensate', array(
            'uid' => $uid,
            'title' => $title,
            'text' => $text,
            'fuli' => $fuli,
            'send_date' => date('Y-m-d H:i:s', time())
        ));
    }

    // 创建邮件, 赠送临福利鸡
    public function inset_compensate_dy_fuli($uid, $title, $text, $dy_fuli, $dy_fuli_tov)
    {
        $this->db->insert('compensate', array(
            'uid' => $uid,
            'title' => $title,
            'text' => $text,
            'dy_fuli' => $dy_fuli,
            'dy_fuli_tov' => $dy_fuli_tov,
            'send_date' => date('Y-m-d H:i:s', time())
        ));
    }

    // 创建邮件, 赠送饲料
    public function inset_compensate_feed($uid, $title, $text, $feed)
    {
        $this->db->insert('compensate', array(
            'uid' => $uid,
            'title' => $title,
            'text' => $text,
            'feed' => $feed,
            'send_date' => date('Y-m-d H:i:s', time())
        ));
    }
}