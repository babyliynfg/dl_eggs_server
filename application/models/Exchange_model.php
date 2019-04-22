<?php
/**
 * Created by PhpStorm.
 * User: liyuanfeng
 * Date: 2018/5/30
 * Time: 下午5:22
 */

class Exchange_model extends MY_Model{

    //提交兑换码
    public function sub_exchange_code($uid, $code)
    {
        $info['error'] = 1;
        $info['code'] = 61;
        $info['info'] = "兑换码无效或已经被领取";
        $query = $this->db->query("select * from exchange where code = '$code'")->result_array();
        if (empty($query)) return $info;
        $query = $query[0];
        $batch = $query['batch'];

        $query2 = $this->db->query("select id from exchange_record where uid = '$uid' and batch = '$batch'")->result_array();
        if (!empty($query2))
        {
            $info['error'] = -1;
            $info['code'] = 62;
            $info['info'] = "您已经领过同类型的兑换码";
            return $info;
        }

        $fuli = $query['fuli'];
        $dy_fuli = $query['dy_fuli'];
        $xpot = $query['xpot'];

        if ($fuli > 0) $this->db->query("update ugame set fuli = fuli + $fuli where uid = '$uid'");
        if ($dy_fuli > 0) $this->db->query("update ugame set dy_fuli = dy_fuli + $fuli where uid = '$uid'");
        if ($xpot > 0.00000) $this->db->query("update ugame set xpot = xpot + $xpot where uid = '$uid'");

        $data = array(
            'uid' => $uid,
            'batch' => $batch,
            'code' => $code,
            'fuli' => $fuli,
            'xpot' => $xpot);

        $this->db->insert('exchange_record', $data);

        $this->db->query("delete from exchange where code = '$code'");

        $info['error'] = 0;
        $info['code'] = 1;
        $info['info'] = "兑换成功！您获得：";
        if ($fuli > 0)
        {
            $info['info'] = $info['info'].strval($fuli).'母鸡 ';
        }
        if ($dy_fuli > 0)
        {
            $info['info'] = $info['info'].strval($dy_fuli).'租借母鸡 ';

        }
        if ($xpot > 0.00000)
        {
            $info['info'] = $info['info'].strval($xpot).'鸡蛋 ';
        }
        return $info;

    }
}