<?php 
/**
 *
 * @author tobi.schaefer@gmail.com
 * @package Gameserver Query
 * @copyright (c) 2014 Tobias Schäfer
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */


require('class_socket.php');
require('class_gameserver_query.php');


$ip = isset($_GET['ip']) ? $_GET['ip'] : '';
$port = isset($_GET['port']) ? (int) $_GET['port'] : 0;
$protokoll = isset($_GET['protokoll']) ? $_GET['protokoll'] : '';


if(empty($ip))
{
	die('No IP given');
}

if($port == 0)
{
	die('No Port given');
}

$gameserver = new server_query($ip, $port);

switch($protokoll)
{
	case 'ase':
		$data = $gameserver->ase();
		break;	
	case 'source':
		$data = $gameserver->source();
		break;
	case 'samp':
		$data = $gameserver->samp();
		break;
	case 'hlds':
		$data = $gameserver->hlds();
		break;
	case 'quake3':
		$data = $gameserver->quake3();
		break;
	case 'quake2':
		$data = $gameserver->quake2();
		break;
	case 'doom3':
		$data = $gameserver->doom3();
		break;
	case 'gamespy1':
		$data = $gameserver->gamespy1();
		break;
	case 'gamespy2':
		$data = $gameserver->gamespy2();
		break;
	case 'gamespy3':
		$data = $gameserver->gamespy3();
		break;
	case 'gamespy4':
		$data = $gameserver->gamespy4();
		break;
	default:
		die('No valide protokoll given');
		break;
}

echo json_encode($data);
