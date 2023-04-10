<?php

//----------------------------------------------------------------------------------
function GetServer($field)
{
	if (isset($_SERVER))
		$val = isset($_SERVER[$field]) ? $_SERVER[$field] : false;
	else
	{
		global $HTTP_SERVER_VARS;
		if (isset($HTTP_SERVER_VARS))
			$val = isset($HTTP_SERVER_VARS[$field]) ? $HTTP_SERVER_VARS[$field] : false;
		else
		{
			$val = getenv($field);
			if (empty($val))
				$val = false;
		}
	}

	return $val;
}

//-----------------------------------------------------------------------------
function getip()
{
	$realip = GetServer('HTTP_X_FORWARDED_FOR');
	if ($realip === false)
	{
		$realip = GetServer('HTTP_CLIENT_IP');
		if ($realip === false)
			$realip = GetServer('REMOTE_ADDR');
	}

	return $realip;
}

//-----------------------------------------------------------------------------
function phpLog($oper, $param1, $param2)
{
	if (!is_dir('log'))
		return;

	$produto = GetSessao('produto');
	if (empty($produto))
		$produto = "PHP";

	$file = 'log/'.$produto.date('my').'.log';
	$fp=@fopen($file,'a');
	if($fp == false)
		return;

	$xforward = GetServer('HTTP_X_FORWARDED_FOR');
	if ($xforward === false)
		$xforward = "";
	$http_user_agent = GetServer('HTTP_USER_AGENT');
	if ($http_user_agent === false)
		$http_user_agent = '';
	$remote_add = getip();
	if ($remote_add === false)
		$remote_add = '0.0.0.0';

	$linelog = sprintf('%s - %s %s %s - %s - %s - %s\n',
						$remote_add, $oper, $param1?$param1:"", $param2?$param2:'',
						date('d/m/y Hi'), $http_user_agent, $xforward);
	
	fwrite($fp,$linelog, strlen($linelog));
	fclose($fp);
}

?>
