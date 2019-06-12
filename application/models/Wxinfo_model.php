<?php
/**
 * Created by PhpStorm.
 * User: wps
 * Date: 2018/5/2
 * Time: 16:45
 */
require 'MY_Model.php';
class Wxinfo_model extends MY_Model{

    function __construct()
    {
        parent::__construct();
        $this->load->model('ugame_model');
    }

    public function get_nickname_info($uid)
    {
        $result = $this->db_r()->query("select nickname from wx_info where openid = '$uid'")->result_array();
        if (empty($result))
            return null;
        return $result[0]['nickname'];
    }

    public function get_c_h_staff_info($uid)
    {
        $result = $this->db_r()->query("select owner_uid from c_h_staff where uid = '$uid'")->row_array();
        if (!isset($result))
            return null;
        return $result;
    }

    public function get_user_info($uid)
    {
        $result = $this->db_r()->query("select u.*, w.* from ugame u left join wx_info w on w.openid = u.uid where uid = '$uid'")->row_array();
        unset($result['unionid']);
        unset($result['tax']);
        unset($result['tax_total']);
        unset($result['user_sign']);

        $result['fullname'] = strlen($result['fullname']) ? md5('傻逼黑客') : '';
        $result['id_card'] = strlen($result['id_card']) ? md5('你放弃吧！傻逼黑客！') : '';

        $result2 = $this->get_c_h_staff_info($uid);
        if ($result2)
        {
            unset($result2['uid']);
            unset($result2['login_date']);
            unset($result2['is_active']);
            $result = array_merge($result, $result2);
        }
        else
        {
            $result['owner_uid'] = "";
            $result['tax'] = 0.00000;
            $result['tax_total'] = 0.00000;
        }

        $result['is_gm'] = $this->get_user_is_gm($uid);

//        $register_time = $result['register_time'];
//        $result['is_novice'] = (time() - $register_time < 86400 * 3) ? 1 : 0;
        $result['is_novice'] = 0;

        $result['green_fuli'] = 0;
        $result['white_fuli'] = 0;
        $result['huge_fuli'] = 0;
        $result['ad_fuli'] = 0;


        return $result;
    }

    public function get_wx_info($uid)
    {
        $result = $this->db_r()->query("select * from wx_info where openid = '$uid'")->result_array();
        $result = $result[0];
        unset($result['user_sign']);
        return $result;
    }

    public function get_user_in_blacklist($uid)
    {
        $result = $this->db_r()->query("select * from user_blacklist where uid = '$uid'")->result_array();
        if (empty($result))
            return 0;
        return 1;
    }

    public function get_user_is_gm($uid)
    {
        $result = $this->db_r()->query("select * from user_gm where uid = '$uid'")->result_array();
        if (empty($result))
            return 0;
        return 1;
    }

    public function update_last_openid($uid, $last_openid, $array)
    {
        // 待定功能 更新用户信息
        $this->db->query("update wx_info set last_openid = '$last_openid' where openid = '$uid'");
        return $this->db->affected_rows();
    }

    public function get_user_uid($last_openid)
    {
        $result = $this->db_r()->query("select openid from wx_info where last_openid = '$last_openid'")->result_array();
        if (empty($result))
        {
            return null;
        }
        return $result[0]['openid'];
    }

    public function get_user_uid_with_id($id)
    {
        $result = $this->db_r()->query("select openid from wx_info where id = '$id'")->result_array();
        if (empty($result))
        {
            return null;
        }
        return $result[0]['openid'];
    }

    public function get_user_uid_with_phone_number($phone_number)
    {
        $result = $this->db_r()->query("select openid from wx_info where phone_number = '$phone_number'")->result_array();
        if (empty($result))
        {
            return null;
        }
        return $result[0]['openid'];
    }

    public function check_register_uinfo($unionid)
    {
        if ($unionid == '')
        {
            return null;
        }

        $result = $this->db_r()->query("select openid from wx_info where unionid = '$unionid'")->result_array();
        if (empty($result))
        {
            return null;
        }
        return $result[0]['openid'];
    }

    public function update_info($uid, $array)
    {
        $nickname = $array['nickname'];
        $sex = $array['sex'];
        $headimgurl = $array['headimgurl'];
        $city = $array['city'];
        $province = $array['province'];
        $country = $array['country'];

        $this->db->query("update wx_info set nickname = '$nickname', sex = '$sex', headimgurl = '$headimgurl', city = '$city', province = '$province', country = '$country' where openid = '$uid'");
    }

    public function modify_user_nickname($uid, $nickname)
    {
        $this->db->query("update wx_info set nickname = '$nickname' where openid = '$uid'");
        return 1;
    }

    public function modify_user_headimgurl($uid, $headimgurl)
    {
        $this->db->query("update wx_info set headimgurl = '$headimgurl' where openid = '$uid'");
        return 1;
    }

    public function check_user_sign($uid, $sign)
    {
        $result = $this->db_r()->query("select user_sign from wx_info where openid = '$uid'")->row_array();
        if (!isset($result['user_sign']))
            return 0;
        return intval($sign == $result['user_sign']);
    }

    public function get_user_sign($uid)
    {
        $result = $this->db_r()->query("select user_sign from wx_info where openid = '$uid'")->row_array();
        if (!isset($result['user_sign']))
            return 0;
        return $result['user_sign'];
    }

    public function record_user_sign($uid)
    {
        $time = strtolower(md5(md5(date("Y-m-d^H:i:s",intval(time())))));
        $this->db->query("update wx_info set user_sign = '$time' where openid = '$uid'");
        return $time;
    }

    public function update_facebook_info($uid, $userinfo)
    {
        $nickname = $userinfo['nickname'];
        $sex = $userinfo['gender'];
        $headimgurl = $userinfo['icon'];

        $this->db->query("update wx_info set nickname = '$nickname', sex = '$sex', headimgurl = '$headimgurl' where openid = '$uid'");
    }

    public function register_facebook_uinfo($userinfo)
    {
        $reg_data = array(
            "openid" => $userinfo['userID'],
            "unionid" => $userinfo['userID'],
            "nickname" => $userinfo['nickname'],
            "sex" => $userinfo['gender'],
            "headimgurl" => $userinfo['icon'],
            "fullname" => 'fullname',
            "id_card" => '100000000000000000'
        );

        $res = $this->db->insert('wx_info', $reg_data);
        if (!$res)
            return 0;
        $id = $this->db->insert_id();
        $this->db->query("update config set key_value = key_value + 1 where key_name = 'user_count'");
        return $id;
    }

    public function update_wx_applet_info($uid, $userinfo)
    {
        $nickname = $userinfo['nickname'];
        $sex = $userinfo['gender'];
        $headimgurl = $userinfo['icon'];

        $this->db->query("update wx_info set nickname = '$nickname', sex = '$sex', headimgurl = '$headimgurl' where openid = '$uid'");
    }

    public function register_wx_applet_uinfo($userinfo)
    {
        $reg_data = array(
            "openid" => $userinfo['openid'],
            "unionid" => $userinfo['unionid'],
            "nickname" => $userinfo['nickname'],
            "sex" => $userinfo['sex'],
            "headimgurl" => $userinfo['headimgurl'],
            "city" => isset($userinfo['city']) ? $userinfo['city'] : '',
            "province" => isset($userinfo['province']) ? $userinfo['province'] : '',
            "country" => isset($userinfo['country']) ? $userinfo['country'] : '',
            "phone_number" => isset($userinfo['phone_number']) ? $userinfo['phone_number'] : ''
        );

        $res = $this->db->insert('wx_info', $reg_data);
        if (!$res)
            return 0;
        $id = $this->db->insert_id();
        $this->db->query("update config set key_value = key_value + 1 where key_name = 'user_count'");
        return $id;
    }

    //微信账号实名认证
    public function certification_wx($uid, $fullname, $id_card)
    {
        if (strlen($fullname) < 6 || strlen($id_card) != 18)
        {
            return 0;
        }

        $tmp = $this->db_r()->query("select id_card from wx_info where openid = '$uid'")->row_array();
        if ($tmp['id_card'] != '')
            return 16;

        $tmp = $this->db_r()->query("select openid from wx_info where id_card = '$id_card'")->row_array();
        if (isset($tmp))
            return 16;

        $num = $this->db_r()->query("select fuli_cnt from task where type_id = 4")->row_array();
        $num = $num["fuli_cnt"];
        $this->compensate_model->inset_compensate_fuli($uid, "系统奖励", "由于您完成微信实名认证送母鸡任务，特在此奉上".$num."只母鸡，请查收~", $num);
        $result = $this->db_w()->query("update wx_info set fullname = '$fullname', id_card = '$id_card' where openid = '$uid'");
        return ($result ? 1 : 0);
    }

    //微信账号绑定手机号
    public function binding_phone_number_wx($uid, $phone_number)
    {
        $tmp = $this->db_r()->query("select id from wx_info where phone_number = '$phone_number'")->result_array();
        if (!empty($tmp))
            return 13;

        $num = $this->db_r()->query("select fuli_cnt from task where type_id = 3")->row_array();
        $num = $num["fuli_cnt"];
        $this->compensate_model->inset_compensate_fuli($uid, "系统奖励", "由于您完成绑定手机号送母鸡任务，特在此奉上".$num."只母鸡，请查收~", $num);
        $this->db_w()->query("update wx_info set phone_number = $phone_number where openid = '$uid'");
        return 1;
    }

    public function check_id_card($uid, $fullname, $id_card)
    {
        $info = $this->db_r()->query("SELECT * FROM wx_info WHERE openid = '$uid'")->result_array();
        if (empty($info))
            return false;
        $info = $info[0];
        return boolval($info['fullname'] == $fullname && $info['id_card'] == $id_card);
    }
}