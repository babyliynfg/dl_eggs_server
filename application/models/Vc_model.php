<?php
class Vc_model extends MY_Model{

    public function insert_phone_verification_code($phone_number, $code)
    {
        $code_date = strval(date("Y-m-d H:i:s",intval(time())));
        if ($this->get_phone_verification_code($phone_number))
        {
            $this->db->query("update phone_verification_code set code = '$code', code_date = '$code_date' where phone_number = '$phone_number'");
        }
        else
        {
            $data = array(
                'phone_number' => $phone_number,
                'code' => $code,
                'code_date' => $code_date
            );
            $this->db->insert('phone_verification_code', $data);
        }
    }

    public function get_phone_verification_code($phone_number)
    {
        $result = $this->db_r()->query("select code, code_date from phone_verification_code where phone_number = '$phone_number'")->result_array();
        if (empty($result))
            return null;
        return $result[0];
    }
}