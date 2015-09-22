<?php
/**
 * Plugin Name: uLogin - виджет авторизации через социальные сети
 * Plugin URI:  https://ulogin.ru/
 * Description: uLogin — это инструмент, который позволяет пользователям получить единый доступ к различным
 * Интернет-сервисам без необходимости повторной регистрации, а владельцам сайтов — получить дополнительный
 * приток клиентов из социальных сетей и популярных порталов (Google, Яндекс, Mail.ru, ВКонтакте, Facebook и др.)
 * Version:     2.0.0
 * Author:      uLoginTeam
 * Author URI:  https://ulogin.ru/
 * License:     GPL2
 */
if(!defined('BOOTSTRAP')) {
	die('Access denied');
}
use Tygh\Registry;
use Tygh\Http;
use Tygh\Mailer;

function fn_ulogin_authpanel($place = 0) {
	$backurl = fn_url(Registry::get('config.current_url'));
	$redirect_uri = urlencode(fn_url(Registry::get('config.http_location') . '/index.php?dispatch=ulogin.login&backurl=' . $backurl));
	$ulogin_default_options = array();
	$ulogin_default_options['display'] = 'panel';
	$ulogin_default_options['providers'] = 'vkontakte,odnoklassniki,mailru,facebook,google,yandex,twitter';
	$ulogin_default_options['fields'] = 'first_name,last_name,email,photo,photo_big';
	$ulogin_default_options['optional'] = 'sex,bdate,country,city';
	$ulogin_default_options['hidden'] = '';
	$ulogin_options = array();
	$options = Registry::get('addons.ulogin');
	$ulogin_options['ulogin_id1'] = $options['ulogin_auth_id'];
	$ulogin_options['ulogin_id2'] = $options['ulogin_sync_id'];
	$default_panel = false;
	switch($place) {
		case 0:
			$ulogin_id = $ulogin_options['ulogin_id1'];
			break;
		case 1:
			$ulogin_id = $ulogin_options['ulogin_id2'];
			break;
		default:
			$ulogin_id = $ulogin_options['ulogin_id1'];
	}
	if(empty($ulogin_id)) {
		$ul_options = $ulogin_default_options;
		$default_panel = true;
	}
	$panel = '';
	$panel .= '<p>' . __('ulogin_social_login') . ':</p>
	<div class="ulogin_panel"';
	if($default_panel) {
		$ul_options['redirect_uri'] = $redirect_uri;
		unset($ul_options['label']);
		$x_ulogin_params = '';
		foreach($ul_options as $key => $value)
			$x_ulogin_params .= $key . '=' . $value . ';';
		if($ul_options['display'] != 'window')
			$panel .= ' data-ulogin="' . $x_ulogin_params . '"></div>'; else
			$panel .= ' data-ulogin="' . $x_ulogin_params . '" href="#"><img src="https://ulogin.ru/img/button.png" width=187 height=30 alt="МультиВход"/></div>';
	} else
		$panel .= ' data-uloginid="' . $ulogin_id . '" data-ulogin="redirect_uri=' . $redirect_uri . '"></div>';
	$panel = '<div class="ulogin_block">' . $panel . '</div><div style="clear:both"></div>';

	return $panel;
}

function fn_ulogin_syncpanel($user_id = 0) {
	$auth = $_SESSION['auth'];
	$current_user = $auth['user_id'];
	$current_user = isset($current_user) ? $current_user : 0;
	$user_id = empty($user_id) ? $current_user : $user_id;
	if(empty($user_id)) {
		return '';
	}
	$networks = array();
	$res = db_get_array("SELECT * FROM ?:ulogin WHERE user_id = ?i", $user_id);
	if($res) {
		foreach($res as $network) {
			$networks[] = $network;
		}
	} else {
		return '<h3 class="ty-subheader">' . __('ulogin_sync_title') . '</h3>' . fn_ulogin_authpanel(1) . '<p>' . __('ulogin_sync_help') . '</p>';
	}
	$output = '
			<style>
			    .big_provider {
			        display: inline-block;
			        margin-right: 10px;
			    }
			</style>
			<h3 class="ty-subheader">' . __('ulogin_sync_title') . '</h3>' . fn_ulogin_authpanel(1) . '<p>' . __('ulogin_sync_help') . '</p>
            <h3 class="ty-subheader">' . __('ulogin_sync_accounts') . '</h3>';
	if($networks) {
		$output .= '<div id="ulogin_accounts">';
		foreach($networks as $network) {
			if($network['user_id'] = $user_id)
				$output .= "<div data-ulogin-network='{$network['network']}'  data-ulogin-identity='{$network['identity']}' class='ulogin_network big_provider {$network['network']}_big'></div>";
		}
		$output .= '</div>
            <p>' . __('ulogin_sync_accounts_delete') . '</p>';

		return $output;
	}

	return '';
}

/**
 * Обменивает токен на пользовательские данные
 * @param bool $token
 * @return bool|mixed|string
 */
function fn_ulogin_GetUserFromToken($token = false) {
	$response = false;
	if($token) {
		$data = array('cms' => 'cs-cart', 'version' => constant('PRODUCT_VERSION'));
		$request = 'https://ulogin.ru/token.php?token=' . $token . '&host=' . $_SERVER['HTTP_HOST'] . '&data=' . base64_encode(json_encode($data));
		$response = Http::get($request);
	}

	return $response;
}

/**
 * Проверка пользовательских данных, полученных по токену
 * @param $u_user - пользовательские данные
 * @return bool
 */
function fn_ulogin_CheckTokenError($u_user) {
	if(!is_array($u_user)) {
		fn_set_notification('E', __('ulogin_error'), __('ulogin_error_data'));

		return false;
	}
	if(isset($u_user['error'])) {
		$strpos = strpos($u_user['error'], 'host is not');
		if($strpos) {
			fn_set_notification('E', __('ulogin_error'), __('ulogin_error_host'));

			return false;
		}
		switch($u_user['error']) {
			case 'token expired':
				fn_set_notification('E', __('ulogin_error'), __('ulogin_error_timeout'));

				return false;
			case 'invalid token':
				fn_set_notification('E', __('ulogin_error'), __('ulogin_error_token_invalid'));

				return false;
			default:
				fn_set_notification('E', __('ulogin_error'), $u_user['error']);

				return false;
		}
	}
	if(!isset($u_user['identity'])) {
		fn_set_notification('E', __('ulogin_error'), __('ulogin_error_data_identity'));

		return false;
	}

	return true;
}

function fn_ulogin_getUserIdByIdentity($identity) {
	$user_data = db_get_field("SELECT user_id FROM ?:ulogin WHERE identity = ?s", $identity);
	if($user_data)
		return $user_data;

	return false;
}

function fn_ulogin_getUserById($user_id) {
	$user_data = db_get_row("SELECT * FROM ?:users WHERE user_id = ?i", $user_id);
	if($user_data)
		return $user_data;

	return false;
}

function fn_ulogin_getUserInfoByEmail($email) {
	$user_data = db_get_field("SELECT user_id FROM ?:users WHERE email = ?s", $email);
	if($user_data)
		return $user_data;

	return false;
}

/**
 * Регистрация на сайте и в таблице uLogin
 * @param Array $u_user - данные о пользователе, полученные от uLogin
 * @param int $in_db - при значении 1 необходимо переписать данные в таблице ?:ulogin
 * @return bool|int|Error
 */
function fn_ulogin_registration_user($u_user, $in_db = 0) {
	if(!isset($u_user['email'])) {
		Tygh::$app['view']->assign('ulogin_title', __('ulogin_auth_error_title'));
		Tygh::$app['view']->assign('ulogin_error', __('ulogin_auth_error_msg'));
		Tygh::$app['view']->assign('backurl', $_GET['backurl']);
		Tygh::$app['view']->display('addons/ulogin/views/ulogin/error.tpl');
		exit;
	}
	$u_user['network'] = isset($u_user['network']) ? $u_user['network'] : '';
	$u_user['phone'] = isset($u_user['phone']) ? $u_user['phone'] : '';
	// данные о пользователе есть в ulogin_table, но отсутствуют в Базе
	if($in_db == 1) {
		db_query('DELETE FROM ?:ulogin WHERE identity = ?s', $u_user['identity']);
	}
	$user_id = fn_ulogin_getUserInfoByEmail($u_user['email']);
	// $check_m_user == 1 -> есть пользователь с таким email
	$check_m_user = !empty($user_id) ? 1 : 0;
	$auth = $_SESSION['auth'];
	$current_user = isset($auth['user_id']) ? $auth['user_id'] : 0;
	// $isLoggedIn == true -> ползователь онлайн
	$isLoggedIn = (!empty($current_user)) ? 1 : 0;
	if(!$check_m_user && !$isLoggedIn) { // отсутствует пользователь с таким email в базе -> регистрация
		$date = explode('.', $u_user['bdate']);
		$user_data = array();
		$user_data['email'] = $u_user['email'];
		$user_data['user_login'] = fn_ulogin_generateNickname($u_user['first_name'], $u_user['last_name'], $u_user['nickname'], $u_user['bdate']);
		$user_data['user_type'] = 'C';
		$user_data['is_root'] = 'N';
		$user_data['salt'] = fn_generate_salt();
		$user_data['password1'] = $user_data['password2'] = fn_generate_password();
		$user_data['b_firstname'] = $u_user['first_name'];
		$user_data['s_firstname'] = $u_user['first_name'];
		$user_data['b_lastname'] = $u_user['last_name'];
		$user_data['s_lastname'] = $u_user['last_name'];
		$user_data['b_phone'] = isset($u_user['phone']) ? trim(preg_replace('/[^0-9]/', ' ', $u_user['phone'])) : '';
		$user_data['s_phone'] = isset($u_user['phone']) ? trim(preg_replace('/[^0-9]/', ' ', $u_user['phone'])) : '';
		$user_data['b_city'] = isset($u_user['city']) ? $u_user['city'] : '';
		$user_data['s_city'] = isset($u_user['city']) ? $u_user['city'] : '';
		$user_data['birthday'] = $date['2'];
		list($user_data['user_id'], $profile_id) = fn_update_user('', $user_data, $auth, true, true, true);
		$u_user_data = array('user_id' => $user_data['user_id'], 'identity' => $u_user['identity'], 'network' => $u_user['network']);
		db_query("INSERT INTO ?:ulogin ?e", $u_user_data);

		return $user_data['user_id'];
	} else { // существует пользователь с таким email или это текущий пользователь
		if(!isset($u_user["verified_email"]) || intval($u_user["verified_email"]) != 1) {
			Tygh::$app['view']->assign('token', $_REQUEST['token']);
			Tygh::$app['view']->display('addons/ulogin/views/ulogin/confirm.tpl');
			exit;
		}
		if(intval($u_user["verified_email"]) == 1) {
			$user_id = $isLoggedIn ? $current_user : $user_id;
			$other_u = db_get_row("SELECT identity FROM ?:ulogin WHERE user_id = ?i", $user_id);
			if($other_u) {
				if(!$isLoggedIn && !isset($u_user['merge_account'])) {
					Tygh::$app['view']->assign('token', $_REQUEST['token']);
					Tygh::$app['view']->assign('identity', $other_u['identity']);
					Tygh::$app['view']->display('addons/ulogin/views/ulogin/merge.tpl');
					exit;
				}
			}
			$u_user_data = array('user_id' => $user_id, 'identity' => $u_user['identity'], 'network' => $u_user['network']);
			db_query("INSERT INTO ?:ulogin ?e", $u_user_data);

			return $user_id;
		}
	}

	return false;
}

/**
 * Гнерация логина пользователя
 * в случае успешного выполнения возвращает уникальный логин пользователя
 * @param $first_name
 * @param string $last_name
 * @param string $nickname
 * @param string $bdate
 * @param array $delimiters
 * @return string
 */
function fn_ulogin_generateNickname($first_name, $last_name = "", $nickname = "", $bdate = "", $delimiters = array('.', '_')) {
	$delim = array_shift($delimiters);
	$first_name = fn_ulogin_translitIt($first_name);
	$first_name_s = substr($first_name, 0, 1);
	$variants = array();
	if(!empty($nickname))
		$variants[] = $nickname;
	$variants[] = $first_name;
	if(!empty($last_name)) {
		$last_name = fn_ulogin_translitIt($last_name);
		$variants[] = $first_name . $delim . $last_name;
		$variants[] = $last_name . $delim . $first_name;
		$variants[] = $first_name_s . $delim . $last_name;
		$variants[] = $first_name_s . $last_name;
		$variants[] = $last_name . $delim . $first_name_s;
		$variants[] = $last_name . $first_name_s;
	}
	if(!empty($bdate)) {
		$date = explode('.', $bdate);
		$variants[] = $first_name . $date[2];
		$variants[] = $first_name . $delim . $date[2];
		$variants[] = $first_name . $date[0] . $date[1];
		$variants[] = $first_name . $delim . $date[0] . $date[1];
		$variants[] = $first_name . $delim . $last_name . $date[2];
		$variants[] = $first_name . $delim . $last_name . $delim . $date[2];
		$variants[] = $first_name . $delim . $last_name . $date[0] . $date[1];
		$variants[] = $first_name . $delim . $last_name . $delim . $date[0] . $date[1];
		$variants[] = $last_name . $delim . $first_name . $date[2];
		$variants[] = $last_name . $delim . $first_name . $delim . $date[2];
		$variants[] = $last_name . $delim . $first_name . $date[0] . $date[1];
		$variants[] = $last_name . $delim . $first_name . $delim . $date[0] . $date[1];
		$variants[] = $first_name_s . $delim . $last_name . $date[2];
		$variants[] = $first_name_s . $delim . $last_name . $delim . $date[2];
		$variants[] = $first_name_s . $delim . $last_name . $date[0] . $date[1];
		$variants[] = $first_name_s . $delim . $last_name . $delim . $date[0] . $date[1];
		$variants[] = $last_name . $delim . $first_name_s . $date[2];
		$variants[] = $last_name . $delim . $first_name_s . $delim . $date[2];
		$variants[] = $last_name . $delim . $first_name_s . $date[0] . $date[1];
		$variants[] = $last_name . $delim . $first_name_s . $delim . $date[0] . $date[1];
		$variants[] = $first_name_s . $last_name . $date[2];
		$variants[] = $first_name_s . $last_name . $delim . $date[2];
		$variants[] = $first_name_s . $last_name . $date[0] . $date[1];
		$variants[] = $first_name_s . $last_name . $delim . $date[0] . $date[1];
		$variants[] = $last_name . $first_name_s . $date[2];
		$variants[] = $last_name . $first_name_s . $delim . $date[2];
		$variants[] = $last_name . $first_name_s . $date[0] . $date[1];
		$variants[] = $last_name . $first_name_s . $delim . $date[0] . $date[1];
	}
	$i = 0;
	$exist = true;
	while(true) {
		if($exist = fn_ulogin_userExist($variants[$i])) {
			foreach($delimiters as $del) {
				$replaced = str_replace($delim, $del, $variants[$i]);
				if($replaced !== $variants[$i]) {
					$variants[$i] = $replaced;
					if(!$exist = fn_ulogin_userExist($variants[$i]))
						break;
				}
			}
		}
		if($i >= count($variants) - 1 || !$exist)
			break;
		$i++;
	}
	if($exist) {
		while($exist) {
			$nickname = $first_name . mt_rand(1, 100000);
			$exist = fn_ulogin_userExist($nickname);
		}

		return $nickname;
	} else
		return $variants[$i];
}

/**
 * Проверка существует ли пользователь с заданным логином
 */
function fn_ulogin_userExist($login) {
	$user_data = db_get_row("SELECT user_id FROM ?:users WHERE user_login = ?s", $login);
	if(!empty($user_data))
		return true;

	return false;
}

/**
 * Транслит
 */
function fn_ulogin_translitIt($str) {
	$tr = array("А" => "a", "Б" => "b", "В" => "v", "Г" => "g", "Д" => "d", "Е" => "e", "Ж" => "j", "З" => "z", "И" => "i", "Й" => "y", "К" => "k", "Л" => "l", "М" => "m", "Н" => "n", "О" => "o", "П" => "p", "Р" => "r", "С" => "s", "Т" => "t", "У" => "u", "Ф" => "f", "Х" => "h", "Ц" => "ts", "Ч" => "ch", "Ш" => "sh", "Щ" => "sch", "Ъ" => "", "Ы" => "yi", "Ь" => "", "Э" => "e", "Ю" => "yu", "Я" => "ya", "а" => "a", "б" => "b", "в" => "v", "г" => "g", "д" => "d", "е" => "e", "ж" => "j", "з" => "z", "и" => "i", "й" => "y", "к" => "k", "л" => "l", "м" => "m", "н" => "n", "о" => "o", "п" => "p", "р" => "r", "с" => "s", "т" => "t", "у" => "u", "ф" => "f", "х" => "h", "ц" => "ts", "ч" => "ch", "ш" => "sh", "щ" => "sch", "ъ" => "y", "ы" => "y", "ь" => "", "э" => "e", "ю" => "yu", "я" => "ya");
	if(preg_match('/[^A-Za-z0-9\_\-]/', $str)) {
		$str = strtr($str, $tr);
		$str = preg_replace('/[^A-Za-z0-9\_\-\.]/', '', $str);
	}

	return $str;
}

/**
 * @param $user_id
 * @return bool
 */
function fn_ulogin_CheckUserId($user_id) {
	$auth = $_SESSION['auth'];
	$current_user = $auth['user_id'];
	if(($current_user > 0) && ($user_id > 0) && ($current_user != $user_id)) {
		Tygh::$app['view']->assign('ulogin_title', __('ulogin_sync_error_title'));
		Tygh::$app['view']->assign('ulogin_error', __('ulogin_sync_error_msg'));
		Tygh::$app['view']->assign('backurl', $_GET['backurl']);
		Tygh::$app['view']->display('addons/ulogin/views/ulogin/error.tpl');
		exit;
	}

	return true;
}