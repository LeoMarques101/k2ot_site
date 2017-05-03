<?php
/**
 * Server status
 *
 * @package   MyAAC
 * @author    Slawkens <slawkens@gmail.com>
 * @copyright 2017 MyAAC
 * @version   0.0.2
 * @link      http://my-aac.org
 */
defined('MYAAC') or die('Direct access not allowed!');

$status = array();
$status['online'] = false;
$status['players'] = 0;
$status['playersMax'] = 0;
$status['lastCheck'] = 0;
$status['uptime'] = '0h 0m';
$status['monsters'] = 0;

$status_ip = $config['lua']['ip'];
if(isset($config['lua']['statusProtocolPort'])) {
	$config['lua']['loginPort'] = $config['lua']['statusProtocolPort'];
	$config['lua']['statusPort'] = $config['lua']['statusProtocolPort'];
	$status_port = $config['lua']['statusProtocolPort'];
}
else if(isset($config['lua']['status_port'])) {
	$config['lua']['loginPort'] = $config['lua']['status_port'];
	$config['lua']['statusPort'] = $config['lua']['status_port'];
	$status_port = $config['lua']['status_port'];
}

$status_port = $config['lua']['statusPort'];

if(isset($config['status_ip'][0]))
{
	$status_ip = $config['status_ip'];
	$status_port = $config['status_port'];
}
else if(!isset($status_ip[0])) // try localhost if no ip specified
{
	$status_ip = '127.0.0.1';
	$status_port = 7171;
}

$fetch_from_db = true;
if($cache->enabled())
{
	$tmp = '';
	if($cache->fetch('status', $tmp))
	{
		$status = unserialize($tmp);
		$fetch_from_db = false;
	}
}

if($fetch_from_db)
{
	// get info from db
	$status_query = $db->query('SELECT ' . $db->fieldName('name') . ', ' . $db->fieldName('value') . ' FROM ' . $db->tableName(TABLE_PREFIX . 'config') . ' WHERE ' . $db->fieldName('name') . ' LIKE "%status%"');
	if($status_query->rowCount() <= 0) // empty, just insert it
	{
		foreach($status as $key => $value)
			registerDatabaseConfig('status_' . $key, $value);
	}
	else
	{
		foreach($status_query as $tmp)
			$status[str_replace('status_', '', $tmp['name'])] = $tmp['value'];
	}
}

if(isset($config['lua']['statustimeout']))
	$config['lua']['statusTimeout'] = $config['lua']['statustimeout'];

// get status timeout from server config
$status_timeout = eval('return ' . $config['lua']['statusTimeout'] . ';') / 1000 + 1;

if($status['lastCheck'] + $status_timeout < time())
{
	// get server status and save it to database
	$serverInfo = new OTS_ServerInfo($status_ip, $status_port);
	$serverStatus = $serverInfo->status();
	if(!$serverStatus)
	{
		$status['online'] = false;
		$status['players'] = 0;
		$status['playersMax'] = 0;
	}
	else
	{
		$status['lastCheck'] = time(); // this should be set only if server respond

		$status['online'] = true;
		$status['players'] = $serverStatus->getOnlinePlayers(); // counts all players logged in-game, or only connected clients (if enabled on server side)
		$status['playersMax'] = $serverStatus->getMaxPlayers();

		// for status afk thing
		if($config['online_afk'])
		{
			// get amount of players that are currently logged in-game, including disconnected clients (exited)
			$query = $db->query('SELECT COUNT(' . $db->fieldName('id') . ') AS playersTotal FROM ' . $db->tableName('players') .
				' WHERE ' . $db->fieldName('online') . ' > 0');

			if($query->rowCount() > 0)
			{
				$query = $query->fetch();
				$status['playersTotal'] = $query['playersTotal'];
			}
		}

		$status['uptime'] = $serverStatus->getUptime();
		$h = floor($status['uptime'] / 3600);
		$m = floor(($status['uptime'] - $h * 3600) / 60);
		$status['uptimeReadable'] = $h . 'h ' . $m . 'm';

		$status['monsters'] = $serverStatus->getMonstersCount();
		$status['motd'] = $serverStatus->getMOTD();

		$status['mapAuthor'] = $serverStatus->getMapAuthor();
		$status['mapName'] = $serverStatus->getMapName();
		$status['mapWidth'] = $serverStatus->getMapWidth();
		$status['mapHeight'] = $serverStatus->getMapHeight();

		$status['server'] = $serverStatus->getServer();
		$status['serverVersion'] = $serverStatus->getServerVersion();
		$status['clientVersion'] = $serverStatus->getClientVersion();
	}

	if($cache->enabled())
		$cache->set('status', serialize($status), 120);

	foreach($status as $key => $value) {
		updateDatabaseConfig('status_' . $key, $value);
	}
}
?>
