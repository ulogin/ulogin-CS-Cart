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

/*
 * Выводит в форму html для генерации виджета
 */
$authpanel = fn_ulogin_authpanel(0);
Tygh::$app['view']->assign('ulogin_authpanel', $authpanel);
$syncpanel = fn_ulogin_syncpanel();
Tygh::$app['view']->assign('ulogin_syncpanel', $syncpanel);