<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Server酱
 *
 * @author Feng <fengit@shangjing-inc.com>
 */
class Serverchan {
    protected $_ci;
    protected $to_user;
    protected $to_users;
    
    //@TODO 通知名单应当从管理员表中获取
    function __construct($params = '') {
        $this->_ci = & get_instance();
        
        //初始化通知名单
        $this->to_users = array(
                '冯英杰'   => SERVERCHAN_FYJ_KEY,
        );
    }
    
    /**
     * 向所有用户发送通知
     *
     * @author Feng <fengit@shanjing-inc.com>
     */
    public function notify_all_admins($title, $desc = '') {
        //导入类库
        $this->_ci->load->library('curl');
        
        $result_notify = array();
        foreach ($this->to_users as $user => $user_key) {
            //构造请求地址
            $url = 'http://sc.ftqq.com/'.$user_key.'.send?desp='.urlencode($desc ? : date('Y-m-d H:i:s')).'&text='.urlencode($title);
            
            //构造参数
            $options = array(
                    CURLOPT_FOLLOWLOCATION     => 1,
                    CURLOPT_SSL_VERIFYPEER     => 0
            );
            $result = json_decode($this->_ci->curl->simple_get($url, array(), $options), TRUE);
            
            //判断通知结果
            if (isset($result['errmsg']) && $result['errmsg'] == 'success') {
                $result_notify[] = array(
                        'status'    => 'success',
                        'username'  => $user
                );
            } else {
                $result_notify[] = array(
                        'status'    => 'error',
                        'msg'       => $result['errmsg'],
                        'username'  => $user,
                );
                
                log_message('error', '【Server酱】错误：'.$result['errmsg']);
            }
        }
        
        //返回通知
        return $result_notify;
    }
}

/* End of file Serverchan.php */
/* Location: ./application/libraries/Serverchan.php */