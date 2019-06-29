<?php
/**
 * Created by PhpStorm.
 * User: wps
 * Date: 2018/5/6
 * Time: 10:59
 */

class TaskRun extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('wxinfo_model');
        $this->load->model('ugame_model');
        $this->load->model('wakuang_model');
        $this->load->model('systemrun_model');
        $this->load->model('adsystem_model');
        $this->load->model('redis_model');
    }

    function delete_task()
    {
        $time1 = time() - 86400;
        $this->db->query("DELETE FROM wakuang WHERE ore_status = 0 and ore_type_id != 1 and find_time < " . $time1);

        $time2 = time() - 172800;
        $this->db->query("DELETE FROM wakuang_record WHERE find_time < " . $time2);
    }

    function move_task()
    {
        echo "<pre>";
        var_dump('A' . time());
        echo "</pre>";
        $table_name_wakuang = 'wakuang';
        $sql_got = "select * from $table_name_wakuang where ore_status = 1";
        $got = $this->db->query($sql_got)->result_array();
        echo "<pre>";
        var_dump('B' . time());
        echo "</pre>";
        if (count($got) > 0) {
            var_dump('count：' . count($got));
            $sql = "INSERT INTO wakuang_record(`id`,`uid`,`ore_type_id`,`ore_num`,`find_time`) VALUES ";
            foreach ($got as $k => $value) {
                $sql .= '(' . $value['id'] . ',' . "'" . $value['uid'] . "'" . ',' . $value['ore_type_id'] . ',' . $value['ore_num'] . ',' . $value['find_time'] . '),';
            }
            $sql = rtrim($sql, ',');
            $this->db->query($sql);

            echo "<pre>";
            var_dump('C' . time());
            echo "</pre>";
            $ids = '';
            foreach ($got as $k => $value) {
                $ids .= ',' . $value['id'];
            }
            $ids = substr($ids, 1);
            $sql_delete = "delete from $table_name_wakuang where id in($ids)";
            $this->db->query($sql_delete);
        }
        echo "<pre>";
        var_dump('D' . time());
        echo "</pre>";
    }

    // 每日凌晨第一个要执行的任务
    // 将不活跃的用户移出矿场，并统计刷新矿场动态数据
    function delete_not_online_within_2_days()
    {
        set_time_limit(0);
        $this->db->query("update config set key_value = '1' where key_name = 'ereryday_data_processing'");

        $this->clear_everyday_data();

        $all_mine_field = $this->db->query("SELECT * FROM ugame WHERE is_active = 1")->result_array();

        $time = strtotime(date('Y-m-d', time()));
        $ids = '';
        $index = 0;
        foreach ($all_mine_field as $k => $v) {
            $uid = $v['uid'];

            $login_date = date('Y-m-d', $v['logintime']);
            $login_time = strtotime($login_date);
            if ($time - $login_time > 129600) {
                $ids .= ',' . "'" . $uid . "'";
                $index += 1;
            }
        }
        if ($index > 0) {
            $ids = substr($ids, 1);
            $this->db->query("UPDATE ugame SET is_active = 0 WHERE uid in($ids)");
            $this->db->query("UPDATE c_h_staff SET is_active = 0 WHERE uid in($ids)");
            echo "DELETE => " . $index;
        }

        $this->reload_c_h_staff_data();

        echo 'OK';
        $this->db->query("update config set key_value = '0' where key_name = 'ereryday_data_processing'");
    }

    function reload_c_h_staff_data()
    {
        $this->db->query("UPDATE c_h_staff_owner_dynamic set active_staff_count = 0");

        $data = $this->db->query("SELECT * FROM c_h_staff where is_active = 1")->result_array();

        $index = 0;
        foreach ($data as $k=>$v)
        {
            $owner_uid = $v['owner_uid'];
            $this->db->query("insert ignore into c_h_staff_owner_dynamic(`owner_uid`) value('$owner_uid')");
            $this->db->query("UPDATE c_h_staff_owner_dynamic set active_staff_count = active_staff_count + 1 where owner_uid = '$owner_uid'");

            $index += 1;
        }
    }

//    /*
    function new_task()
    {
        if ($_SERVER['SERVER_ADDR'] != $this->getIp())
            return;
        set_time_limit(0);

        $cycle_time = 1800;

        $this->db->query("update config set key_value = '1' where key_name = 'producing'");
        echo "<pre>";
        var_dump('A' . time());
        echo "</pre>";

        // 所有活跃用户
        $all_miner = $this->db->query("SELECT * FROM ugame WHERE is_active = 1")->result_array();

        /***/

        $this->systemrun_model->make_eggs($all_miner);

        /***/

        $now = time() + $cycle_time + 15;
        $this->db->query("update config set key_value = $now where key_name = 'next_output_time'");
        var_dump('END' . time());

        $this->db->query("update config set key_value = '0' where key_name = 'producing'");

    }
//*/

    function new_red_eggs()
    {
        set_time_limit(0);
        $this->db->query("update config set key_value = '1' where key_name = 'producing'");
        echo "<pre>";
        var_dump('A' . time());
        echo "</pre>";
        $coin_single = 0.0003 / 2 * 1.5;

        // 所有活跃用户
        $all_miner = $this->db->query("SELECT * FROM ugame WHERE is_active = 1")->result_array();

        $uid_cnt = $this->db->query("SELECT uid, COUNT(id) AS eggs_cnt FROM wakuang group BY uid")->result_array();

        $dict = array();
        $index = 0;
        foreach ($uid_cnt as $k=>$v)
        {
            $uid = $v['uid'];
            $eggs_cnt = $v['eggs_cnt'];
            $dict[$uid] = $eggs_cnt;
            $index += 1;
        }

        $time = time();
        $ore_type_id = 3;
        $sql = "INSERT INTO wakuang(`uid`,`ore_type_id`,`ore_num`,`find_time`) VALUES ";
        foreach ($all_miner as $k => $item) {

            $fuli = $item['fuli'] + $item['dy_fuli'];
            $nums = $fuli * $coin_single;

            $sql .= '(' . "'" . $item['uid'] . "'" . ',' . $ore_type_id . ',' . $nums . ',' . $time . '),';
        }
        $sql = rtrim($sql, ',');
        $this->db->query($sql);

        var_dump('END' . time());

        $this->db->query("update config set key_value = '0' where key_name = 'producing'");

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

    // 回收临时算力
    function recovery_dy_fuli()
    {
        $date = date("Y-m-d",time());

        $dict = array();
        $data = $this->db->query("SELECT * FROM term_of_validity_dy_fuli WHERE expiration_date < '$date'")->result_array();
        $index = 0;
        foreach ($data as $k => $v)
        {
            $uid = $v['uid'];
            $dy_fuli = $v['dy_fuli'];

            if (isset($dict[$uid]))
            {
                $dict[$uid] += $dy_fuli;
            }
            else
            {
                $dict[$uid] = $dy_fuli;
            }

            $index += 1;
        }

        $index = 0;
        foreach ($dict as $k => $v)
        {
//            echo "<pre>";
//            echo $sql;
//            echo "</pre>";
            $this->db->query("UPDATE ugame SET dy_fuli = dy_fuli - $v WHERE uid = '$k'");
            $index += 1;
        }
        $this->db->query("DELETE FROM term_of_validity_dy_fuli WHERE expiration_date < '$date'");
        echo "recovery_dy_fuli OK";
    }


    function new_cny_task()
    {
        if ($_SERVER['SERVER_ADDR'] != $this->getIp())
            return;
        $this->db->query("update config set key_value = '1' where key_name = 'producing'");
        echo "<pre>";
        var_dump('A' . time());
        echo "</pre>";

        // 所有矿场
        $all_miner = $this->db->query("SELECT * FROM ugame WHERE is_active = 1")->result_array();

        /**********************截至目前，获取到所有用户uid，算力的表**********************/

        $time = time();
        $ore_type_id = 2;
        $sql = "INSERT INTO wakuang(`uid`,`ore_type_id`,`ore_num`,`find_time`) VALUES ";
        foreach ($all_miner as $k => $item)
        {
            $nums = mt_rand(1, 2) * 0.01;
            $sql .= '(' . "'" . $item['uid'] . "'" . ',' . $ore_type_id . ',' . $nums . ',' . $time . '),';
        }
        $sql = rtrim($sql, ',');
        $this->db->query($sql);

        $this->db->query("update config set key_value = '0' where key_name = 'producing'");

    }

    function add_xpot_cost_record()
    {
        $xpot_cost = $this->ugame_model->get_config('xpot_cost');

        $data = array(
            'title' => date('Y-m-d', time()),
            'date' => date('Y-m-d H:i', time()),
            'xpot_cost' => $xpot_cost);

        $this->db->insert('xpot_cost_record', $data);
    }

    function clear_everyday_data()
    {
        $this->db->query("truncate everyday_user_record");
        $this->db->query("truncate c_h_staff_owner_dynamic");
        $this->db->query("truncate a_finish_awaken");
    }

    function update_user_count_other_app()
    {
        $url = "http://xpot.qiaochucn.com/index.php/Zhao_cai_mao_task/get_user_count";

        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

        $output = curl_exec($ch);
        curl_close($ch);

        $output = json_decode($output, true);

        if (isset($output['user_count']))
        {
            $user_count = $output['user_count'];

            $this->db->query("update config set key_value = '$user_count' where key_name = 'user_count_xpot'");
        }
    }

    public function get_user_count()
    {
        $miner_count = $this->db->query("select key_value from config where key_name = 'user_count'")->result_array();
        $miner_count = $miner_count[0]['key_value'];
        $result['user_count'] = $miner_count;
        echo json_encode($result);
    }

    // 打赏排行榜
    function task_a_game_qds_ranking_list()
    {
        $this->db->query("TRUNCATE TABLE a_game_qds_dy_fuli_total_tmp");
        $this->db->query("TRUNCATE TABLE a_game_qds_ranking_list");

        $dict = array();
        $data = $this->db->query("SELECT uid, dy_fuli FROM a_game_qds_progressive WHERE dy_fuli > 0")->result_array();
        $index = 0;
        foreach ($data as $k => $v)
        {
            $uid = $v['uid'];
            $dy_fuli = $v['dy_fuli'];

            if (isset($dict[$uid]))
            {
                $dict[$uid] += $dy_fuli;
            }
            else
            {
                $dict[$uid] = $dy_fuli;
            }


            $index += 1;
        }

        $sql = "INSERT INTO a_game_qds_dy_fuli_total_tmp(`uid`,`dy_fuli`) VALUES ";
        foreach ($dict as $k => $v) {
            $sql .= '(' . "'" . $k . "'" . ',' . $v . '),';
        }
        $sql = rtrim($sql, ',');
        $this->db->query($sql);

        $this->db->query("INSERT INTO a_game_qds_ranking_list(uid, dy_fuli, nickname, headimgurl) SELECT tmp.uid, tmp.dy_fuli, w.nickname, w.headimgurl FROM a_game_qds_dy_fuli_total_tmp tmp LEFT JOIN wx_info w ON w.openid = tmp.uid ORDER BY tmp.dy_fuli DESC limit 0, 100");

    }

    public function task_tjs_seggs_cost()
    {
        $url = "https://openapi.tokeneco.co/open/api/get_records?symbol=seggusdt&period=60";

        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $output = curl_exec($ch);
        curl_close($ch);
        $output = json_decode($output, true);

        if ($output['code'] == 0)
        {
            $data = json_encode($output['data']);
            $this->db->query("UPDATE config SET key_value = '$data' WHERE key_name = 'tjs_segg_cost'");
        }
        echo 'task_tjs_seggs_cost ok';
        $this->task_all_ticker();
        $this->adsystem_model->task_handle_uncommitted();
        $this->adsystem_model->task_handle_no_audit();
        $this->task_redis_fuli_ranking();
    }

    public function task_all_ticker()
    {
        $url = "http://api.zb.cn/data/v1/allTicker";

        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $output = curl_exec($ch);
        curl_close($ch);
        $output = json_decode($output, true);

        if (isset($output['gramusdt']))
        {
            echo 'gramusdt ok';
            $cost = $output['gramusdt']['buy'];
            $this->db->query("UPDATE config SET key_value = '$cost' WHERE key_name = 'gramusdt'");
        }

        if (isset($output['btcusdt']))
        {
            echo 'btcusdt ok';
            $cost = $output['btcusdt']['buy'];
            $this->db->query("UPDATE config SET key_value = '$cost' WHERE key_name = 'btcusdt'");
        }

        if (isset($output['ethusdt']))
        {
            echo 'ethusdt ok';
            $cost = $output['ethusdt']['buy'];
            $this->db->query("UPDATE config SET key_value = '$cost' WHERE key_name = 'ethusdt'");
        }
        echo 'task_all_ticker ok';
    }


    public function task_redis_fuli_ranking()
    {
//        $data_zhandou = $this->db->query("SELECT u.uid, w.nickname, w.headimgurl, u.zhandou_fuli FROM ugame u LEFT JOIN wx_info w ON w.openid = u.uid ORDER BY u.zhandou_fuli DESC limit 10")->result_array();
//        $data_gujv = $this->db->query("SELECT u.uid, w.nickname, w.headimgurl, u.gujv_fuli FROM ugame u LEFT JOIN wx_info w ON w.openid = u.uid ORDER BY u.gujv_fuli DESC limit 10")->result_array();
//        $data_luhua = $this->db->query("SELECT u.uid, w.nickname, w.headimgurl, u.luhua_fuli FROM ugame u LEFT JOIN wx_info w ON w.openid = u.uid ORDER BY u.luhua_fuli DESC limit 10")->result_array();
//        $data_da = $this->db->query("SELECT u.uid, w.nickname, w.headimgurl, u.da_fuli FROM ugame u LEFT JOIN wx_info w ON w.openid = u.uid ORDER BY u.da_fuli DESC limit 10")->result_array();
//
//        $redis = $this->redis_model->get_redis();
//
//        // 设置测试key
//        $redis->set( "zhandou_ranking" , json_encode($data_zhandou));
//        $redis->set( "gujv_ranking" , json_encode($data_gujv));
//        $redis->set( "luhua_ranking" , json_encode($data_luhua));
//        $redis->set( "da_ranking" , json_encode($data_da));
    }
}
