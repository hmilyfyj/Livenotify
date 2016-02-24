<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 直播助手
 * 
 * @copyright Copyright (c) 2011-2015 Shanjing Inc
 */

class Livehelper extends CI_Controller {
	
	public $notified_file;
	
	public $cookie_file;
	
	public $douyu_cookie;
	
	public $huya_cookie;
	
	public $longzhu_cookie;
	
	public $weibo_user;
	
	public $weibo_pass;

  	/**
	 * 初始化类
	 */
	public function __construct() {
		parent::__construct();
		
		//初始参数
		$this->notified_file	= LIVE_NOTIFY_DATA_FILE;
		$this->cookie_file		= LIVE_NOTIFY_COOKIE_FILE;
		$this->longzhu_cookie	= LIVE_LONGZHU_COOKIE;
		$this->weibo_user		= WEIBO_USERNAME;
		$this->weibo_pass		= WEIBO_PASSWD;
	}
	
	/**
	 *
	 *
	 * @author Feng <fengit@shanjing-inc.com>
	 */
	public function live_notify() {
		//今日 0 点
		$today = strtotime(date('Y-m-d', time()));
	
		//8-23点运行。
		if (time() <= $today + 8 * 60 * 60 && time() >= $today + 23 * 60 * 60) die();
	
		//设置全局参数
		$cookie_file = $this->cookie_file;
		Unirest\Request::verifyPeer(false);
		Unirest\Request::cookieFile($cookie_file);
	
		//虎牙通知列表
		$this->huya();
	
		//斗鱼
		$this->douyu();
	
		//龙珠
		$this->longzhu();
	}
	
	/**
	 * 龙珠直播
	 *
	 * @author Feng <fengit@shanjing-inc.com>
	 */
	public function longzhu() {
		//导入类库
		$this->load->library('serverchan');
	
		//获取通知列表
		$notify_lists	= $this->get_notify_lists();
	
		//获取在线直播列表
		$cookie 	= $this->longzhu_cookie;
		$url		= 'http://star.api.plu.cn/feed/getlivefeedbyroom?pageIndex=0&pageSize=3';
		Unirest\Request::cookie($cookie);
		$resp		= Unirest\Request::get($url);	
		$live_lists = json_decode($resp->raw_body, TRUE);
	
		if(isset($live_lists['totalItems']) && $live_lists['totalItems'] > 0) {
			//过滤已经通知过的主播
			foreach ($live_lists['items'] as $room) {
				if (!isset($notify_lists['longzhu_notify'][$room['room']['roomId']])) {
					$notify_lists['longzhu_notify'][$room['room']['roomId']] = $room;
					
					//通知用户
					$title		= $this->get_week_time().':主播上线通知';
					$content	= $room['room']['name'].'开播啦'."![轻量级交互]({$room['room']['logo']})";
	
					print_r($this->serverchan->notify_all_admins($title, $content));
				}
			}
		}
	
		//保存结果
		$this->save_notify_lists($notify_lists);
	}
	
	/**
	 * 虎牙直播
	 *
	 * @author Feng <fengit@shanjing-inc.com>
	 */
	public function huya() {
		//导入类库
		$this->load->library('serverchan');
	
		//登陆
	
		//获取通知列表
		$notify_lists	= $this->get_notify_lists();
	
		//获取在线直播列表
		$url		= "http://fw.huya.com/dispatch?do=livesubscribe&uid=1454926533";
		$response	= Unirest\Request::get($url);
		$live_lists = json_decode($response->raw_body, TRUE);
	
		if(isset($live_lists['status']) && !empty($live_lists['result']['list'])) {
			//过滤已经通知过的主播
			foreach ($live_lists['result']['list'] as $room) {
				if (!isset($notify_lists['huya_notify'][$room['privateHost']])) {
					$notify_lists['huya_notify'][$room['privateHost']] = $room;
					
					//构造通知内容
					$title		= $this->get_week_time().':主播上线通知';
					$content	= $room['nick'].'开播啦'."![轻量级交互]({$room['screenshot']})";
						
					print_r($this->serverchan->notify_all_admins($title, $content));
				}
			}
		}
	
		$this->save_notify_lists($notify_lists);
	}
	
	/**
	 * 斗鱼直播
	 *
	 * @author Feng <fengit@shanjing-inc.com>
	 */
	public function douyu() {
		//导入类库
		$this->load->library('serverchan');
	
		//获取通知列表
		$notify_lists	= $this->get_notify_lists();
	
		//获取直播列表
		$live_lists		= $this->get_live_lists();
	
		if(isset($live_lists['nowtime']) && !empty($live_lists['room_list'])) {
			//过滤已经通知过的主播
			foreach ($live_lists['room_list'] as $room) {
				if (!isset($notify_lists['douyu_notify'][$room['room_id']])) {
					$notify_lists['douyu_notify'][$room['room_id']] = $room;
	
					//通知用户
					$title		= $this->get_week_time().':主播上线通知';
					$content	= $room['nickname'].'开播啦'."![轻量级交互]({$room['room_src']})";
					$this->serverchan->notify_all_admins($title, $content);
				}
			}
		}
	
		$this->save_notify_lists($notify_lists);
	}
	
	/**
	 * 保存通知结果
	 *
	 * @author Feng <fengit@shanjing-inc.com>
	 */
	public function save_notify_lists($config) {
		$config_file = APPPATH.'cache/douyu_config.txt';
		return file_put_contents($config_file, json_encode($config));
	}
	
	/**
	 * 获取斗鱼的直播
	 *
	 * @author Feng <fengit@shanjing-inc.com>
	 */
	public function get_live_lists() {
		//登陆新浪微博并保存cookie
		$this->weibo_login();
	
		//登陆斗鱼
		$douyu_url	= 'http://www.douyutv.com/member/oauth/signin/weibo';
		$response	= Unirest\Request::get($douyu_url);
	
		//获取主播列表
		$response 	= Unirest\Request::get('http://www.douyutv.com/member/cp/get_follow_list');
		return json_decode($response->raw_body, TRUE);
	}
	
	/**
	 *
	 *
	 * @author Feng <fengit@shanjing-inc.com>
	 */
	public function get_week_time() {
		$weekarray = array("日","一","二","三","四","五","六");
		return "周".$weekarray[date("w")];
	}
	
	/**
	 *
	 *
	 * @author Feng <fengit@shanjing-inc.com>
	 */
	public function get_notify_lists() {
		$config_file = $this->notified_file;
	
		if(file_exists($config_file)) {
			//获取配置文件
			$file_content	= file_get_contents($config_file);
	
			//解析成 array
			$config_array	= json_decode($file_content, TRUE);
	
			//已存在则直接返回
			if ($config_array['time'] == strtotime(date('Y-m-d', time()))) {
				return $config_array;
			}
		}
	
		//初始化保存列表
		$config = array(
				'douyu_notify'		=> array(),
				'huya_notify'		=> array(),
				'longzhu_notify'	=> array(),
				'time'				=> strtotime(date('Y-m-d', time()))
		);
			
		//当前只有我自己使用，所以选择保存在文件里。
		file_put_contents($config_file, json_encode($config));
		return $config;
	}
	
	/**
	 *
	 *
	 * @author Feng <fengit@shanjing-inc.com>
	 */
	public function weibo_login() {
		$u							= $this->weibo_user;
		$p							= $this->weibo_pass;
		$password					= $p;
		$username					= base64_encode($u);
		$loginUrl					= 'https://login.sina.com.cn/sso/login.php?client=ssologin.js(v1.4.15)&_=1403138799543';
		$loginData['entry']			= 'sso';
		$loginData['gateway']		= '1';
		$loginData['from']			= 'null';
		$loginData['savestate']		= '30';
		$loginData['useticket']		= '0';
		$loginData['pagerefer']		= '';
		$loginData['vsnf']			= '1';
		$loginData['su']			= base64_encode($u);
		$loginData['service']		= 'sso';
		$loginData['sp']			= $password;
		$loginData['sr']			= '1920*1080';
		$loginData['encoding']		= 'UTF-8';
		$loginData['cdult']			= '3';
		$loginData['domain']		= 'sina.com.cn';
		$loginData['prelt']			= '0';
		$loginData['returntype']	= 'TEXT';
		$login						= Unirest\Request::post($loginUrl, array(), $loginData);
	
		//访问一下产生登陆 cookie
		$response					= Unirest\Request::get(json_decode($login->raw_body, TRUE)['crossDomainUrlList'][0]);
	}
}

/* End of file Livehelper.php */
/* Location: ./application/controllers/Livehelper.php */