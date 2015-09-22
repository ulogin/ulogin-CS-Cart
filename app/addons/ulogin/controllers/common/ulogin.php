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
use Tygh\Registry;
use Tygh\Http;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
	if($mode == 'login') {
		if(isset($_REQUEST['network'])) {
			try {
				db_query('DELETE FROM ?:ulogin WHERE identity = ?s', $_REQUEST['identity']);
				echo json_encode(array('answerType' => 'ok', 'msg' => "Удаление привязки аккаунта " . $_REQUEST['network'] . " успешно выполнено"));
				unset($udata);
				exit;
			} catch(Exception $e) {
				echo json_encode(array('answerType' => 'error', 'msg' => "Ошибка при удалении аккаунта \n Exception: " . $e->getMessage()));
				unset($udata);
				exit;
			}
		}

		if(!$_REQUEST['token']) {
			fn_redirect(fn_url());
		}

		$u_user = fn_ulogin_GetUserFromToken($_REQUEST['token']);
		if(!$u_user) {
			fn_set_notification('E', __('ulogin_error'), __('ulogin_error_token'));
			exit;
		}
		$u_user = json_decode($u_user, true);
		$check = fn_ulogin_CheckTokenError($u_user);
		if(empty($check)) {
			return false;
		}
		$user_id = fn_ulogin_getUserIdByIdentity($u_user['identity']);
		if(isset($user_id) && !empty($user_id)) {
			$d = fn_get_user_short_info($user_id);
			if($user_id > 0 && $d['user_id'] > 0) {
				fn_ulogin_CheckUserId($user_id);
			} else {
				$user_id = fn_ulogin_registration_user($u_user, 1);
			}
		} else $user_id = fn_ulogin_registration_user($u_user);

		if($user_id > 0) {
			fn_login_user($user_id);
		}

		$redirect_url =  fn_url('/profiles-update/');

		fn_redirect(isset($_GET['backurl']) ? $_GET['backurl']: $redirect_url, true);
	}
}