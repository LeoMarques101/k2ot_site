<?php
/**
 * NPC class
 *
 * @package   MyAAC
 * @author    Gesior <jerzyskalski@wp.pl>
 * @author    Slawkens <slawkens@gmail.com>
 * @author    Lee
 * @copyright 2021 MyAAC
 * @link      https://my-aac.org
 */
defined('MYAAC') or die('Direct access not allowed!');

class NPCs
{
	public static $npcs;

	public static function loadFromXML($show = false)
	{
		$npc_path = config('data_path') . 'npc/';
		if (!file_exists($npc_path))
			return false;

		$npcs = [];
		$xml = new DOMDocument();
		foreach (preg_grep('~\.(xml)$~i', scandir($npc_path)) as $npc) {
			$xml->load($npc_path . $npc);
			if ($xml) {
				$element = $xml->getElementsByTagName('npc')->item(0);
				if (isset($element)) {
					$name = $element->getAttribute('name');
					if (!empty($name) && !in_array($name, $npcs)) {
						$npcs[] = strtolower($name);
					}
				}
			}
		}

		if (count($npcs) == 0) {
			return false;
		}

		require_once LIBS . 'cache_php.php';
		$cache_php = new Cache_PHP(config('cache_prefix'), CACHE);
		$cache_php->set('npcs', $npcs, 5 * 365 * 24 * 60 * 60);
		return true;
	}

	public static function load()
	{
		if (self::$npcs) {
			return;
		}

		require_once LIBS . 'cache_php.php';
		$cache_php = new Cache_PHP(config('cache_prefix'), CACHE);
		self::$npcs = $cache_php->get('npcs');
	}
}
