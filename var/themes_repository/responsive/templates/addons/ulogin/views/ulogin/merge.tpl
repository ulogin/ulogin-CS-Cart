<head></head>
<body>
<script src="//ulogin.ru/js/ulogin.js" type="text/javascript"></script>
<script>uLogin.mergeAccounts("{$token}","{$identity}")</script>
<h2>{__('ulogin_sync_title')}</h2>
<p>{__('ulogin_sync_accounts_error_msg')}</p>
<p>{__('ulogin_sync_accounts_error')}</p>
</body>
{include file="common/scripts.tpl"}