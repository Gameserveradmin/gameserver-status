<?php 
/**
 *
 * @author tobi.schaefer@gmail.com
 * @package Gameserver Query
 * @copyright (c) 2014 Tobias Schäfer
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */


class server_query
{
	var $ip = '';
	var $port = 0;
	var $network_protocol = 'udp';
	private $rcon_passwd = '';
	private $request_id = 0;
	private $pointer = 0;
	private $response = null;
	private $player = false;
	
	function __construct($ip, $port)
	{
		$this->socket = new socket;
		$this->ip = gethostbyname($ip);
		$this->port = (int) $port;
		$this->connect($ip, $port, $this->network_protocol);
	}
	function __destruct()
	{
		$this->close();
	}
	public function send($data)
	{
		return $this->socket->write($data);
	}
	public function connect($ip, $port)
	{
		return $this->socket->connect($ip, $port, $this->network_protocol);
	}
	public function close()
	{
		return $this->socket->close();
	}
	public function read()
	{
		$this->response = '';
		$this->pointer = 0;
		$this->response = $this->socket->read();
		return $this->response;
	}
	private function _read_result($length = 1)
	{
		if(strlen($this->response) < $this->pointer)
		{
			return chr(0);
		}
		$string = substr($this->response, $this->pointer, $length);
		$this->pointer += $length;
		return $string;
	}
	private function _get_byte()
	{
		return ord($this->_read_result(1));
	}
	private function _get_char()
	{
		return $this->_read_result(1);
	}
	private function _get_int16()
	{
		if(strlen($this->response) < 2)
		{
			return;
		}
		$unpacked = unpack('sint', $this->_read_result(2));
		return $unpacked['int'];
	}
	private function _get_int32()
	{
		if(strlen($this->response) < 4)
		{
			return;
		}
		$unpacked = unpack('iint', $this->_read_result(4));
		return $unpacked['int'];
	}
	private function _get_float32()
	{
		$unpacked = unpack('fint', $this->_read_result(4));
		return $unpacked['int'];
	}
	private function _get_string()
	{
		if(strlen($this->response) == 0)
		{
			return;
		}
		
		$str = '';
		$i = 0;
		while(($char = $this->_read_result(1)) != chr(0))
		{
			$i++;
			$str .= $char;
		}
		return $str;
	}
	
	
	private function _get_long()
	{
		$data = UnPack('l', $this->_read_result(4));
			
		return $data[1];
	}
	private function _get_string_part($len)
	{
		return $this->_read_result($len);
	}
	private function _remove_header()
	{
		if(strlen($this->response) == 0)
		{
			return;
		}
		
		$str = '';
		while(($char = $this->_read_result(1)) != chr(0))
		{
			if(ord($char) != 0)
			{
				return $char;
			}
		}
	}
	
	// RCON Funktionen
	public function rcon_send_doom3($command)
	{
		$send = "\xFF\xFFrcon\x00" . $this->rcon_passwd . "\x00" . $command . "\x00";
		$this->send($send);
		$data = $this->read();
		$data = preg_replace("/..print/", "", $data);
		$data = substr($data, 5);
		return $data;
	}
	public function rcon_login_hlds($pw, $username = false)
	{
		// socket_set_blocking($this->socket, false);
		$this->rcon_passwd = $pw;
		$this->send("\xff\xff\xff\xffchallenge rcon\n");
		// $this->_remove_header();
		
		$response = $this->read();
		$request_id = preg_replace('/....challenge.rcon./', '', $response);
		$this->request_id = trim($request_id);
		return $this->request_id;
	}
	public function rcon_send_hlds($command)
	{
		$this->send("\xff\xff\xff\xffrcon " . $this->request_id . ' "' . $this->rcon_passwd . '" ' . $command . "\n");
		$response = $this->_remove_header();
		$response .= $this->read();
		if(preg_match("#^\x{FF}\x{FF}\x{FF}\x{FF}lBad rcon_password.#", $response))
		{
			return false;
		}
		$response = preg_replace("#^\x{FF}\x{FF}\x{FF}\x{FF}.#", '', $response);
		
		return $response;
	}
	public function rcon_login_srcds($pw, $username = false)
	{
		$this->rcon_send_srcds($pw, 3);
		$this->read();
		$this->_remove_header();
	}
	public function rcon_send_srcds($command, $auth = 2)
	{
		$send = $command . "\x00\x00\x00";
		$send = pack('VV', ++$this->request_id, $auth) . $send;
		$send = pack('V', strlen($send)) . $send;
		$this->send($send);
		// if($auth == 2)
		// {
		$response = $this->read();
		$response = substr($response, 12);
		return $response;
		// }
	}
	public function rcon_login_gamespy($pw, $username = false)
	{
		$this->rcon_passwd = $pw;
	}
	public function rcon_send_gamespy($command)
	{
		$send = "\xff\xff\xff\xff\x02rcon \"" . $this->rcon_passwd . '" ' . $command . "\x0a\x00";
		$this->send($send);
		$data = $this->read();
		$data = preg_replace("/....print/", "", $data);
		
		$data = substr($data, 1);
		return $data;
	}
	public function rcon_send_gamespy2($command)
	{
		$send = "\xff\xff\xff\xffrcon \"" . $this->rcon_passwd . '" ' . $command . "\x0a\x00";
		$this->send($send);
		$data = $this->read();
		// $data = stream_get_contents($this->socket, -1);
		$data = preg_replace("/....print\n/", "", $data);
		return $data;
	}
	
	public function clean_value($string)
	{
		$string = preg_replace('/\x1b.../', '', $string);
		$string = preg_replace('#(\^.[0-9]{3})#', '', $string);
		$string = preg_replace('#(\^.)#', '', $string);
		$string = str_replace(array(chr(0), chr(1)), '', $string);
		return trim(mb_convert_encoding($string, 'utf-8'));
	}
	
	
	
	// Status funktionen
	
	/**
	 * ASE status query
	 * http://int64.org/docs/gamestat-protocols/ase.html
	 *
	 * @return array with gameserver data
	 */
	public function ase()
	{
		$this->send("s");
		$this->_get_string_part(4);
		$return['gamename'] = $this->_get_string_part($this->_get_byte() - 1);
		$return['port'] = $this->_get_string_part($this->_get_byte() - 1);
		$return['hostname'] = $this->_get_string_part($this->_get_byte() - 1);
		$return['gamemode'] = $this->_get_string_part($this->_get_byte() - 1);
		$return['mapname'] = $this->_get_string_part($this->_get_byte() - 1);
		$return['version'] = $this->_get_string_part($this->_get_byte() - 1);
		$return['password'] = $this->_get_string_part($this->_get_byte() - 1);
		$return['numplayers'] = $this->_get_string_part($this->_get_byte() - 1);
		$return['maxplayers'] = $this->_get_string_part($this->_get_byte() - 1);
		$return['protokoll'] = 'ASE';
		return $return;
	}
	/**
	 * Doom 3 status query
	 * http://www.int64.org/docs/gamestat-protocols/doom3.html
	 *
	 * @return array with gameserver data
	 */
	public function doom3()
	{
		$this->send("\xFF\xFFgetInfo\x00PiNGPoNG\x00");
		$reply = $this->read();
		
		$reply = preg_replace("#^\x{ff}\x{ff}infoResponse#", '', $reply);
		$reply = preg_replace("#\x{ff}\x{ff}\x{ff}\x{ff}$#", '', $reply);
	//	$reply =  $this->clean_value($reply);
		$reply = preg_split("#\x{00}#", $reply);
		$this->player = $reply;
		for($i = 0; isset($reply[$i]); $i++)
		{
			if(! empty($reply[$i]))
			{
				$key = $reply[$i];
				$i++;
				$value = isset($reply[$i]) ? $reply[$i] : '';
				$value = str_replace(chr(0), '', $value);
				//$return[$key] = $value;
				$return[$key] = $this->clean_value($value);
			}
		}
		if(isset($return) && sizeof($return))
		{
			$return['bots'] = isset($return['bot_enable']) ? $return['bot_enable'] : '';
			$return['minplayers'] = isset($return['si_minPlayers']) ? $return['si_minPlayers'] : '';
			$return['maxplayers'] = isset($return['si_maxPlayers']) ? $return['si_maxPlayers'] : '';
			$return['gamemode'] = isset($return['si_gameType']) ? $return['si_gameType'] : '';
			$return['version'] = isset($return['si_version']) ? $return['si_version'] : '';
			$return['hostname'] = isset($return['si_name']) ? $return['si_name'] : '';
			$return['mapname'] = isset($return['si_map']) ? $return['si_map'] : '';
			$return['protokoll'] = 'Doom 3';
			return $return;
		}
	}
	
	
	/**
	 * Doom 3 player query
	 * http://www.int64.org/docs/gamestat-protocols/doom3.html
	 *
	 * @return array with player data
	 */
	public function doom3_player()
	{
		if(!$this->player)
		{
			$this->send("\xFF\xFFgetInfo\x00PiNGPoNG\x00");
			$reply = $this->read();
			$reply = preg_replace("#^\x{ff}\x{ff}infoResponse#", '', $reply);
			$reply = preg_replace("#\x{ff}\x{ff}\x{ff}\x{ff}$#", '', $reply);
			$reply = preg_split("#\x{00}#", $reply);
			$this->player = $reply;
		}
		$reply = $this->player;
		$return = array();
		for($i = 1; isset($reply[$i]); $i++)
		{
			$key = $reply[$i];
			$i++;
			$value = isset($reply[$i]) ? $reply[$i] : '';
			$return[$key] = $this->clean_value($value);
		}
		$start = false;
		foreach($return as $key => $value)
		{
			if($key == '')
			{
				$start = true;
			}
			if($start)
			{
				$players[] = $key;
			}
		}
		$player = array();
		for($i = 1; isset($players[$i]); $i++)
		{
			$player[] = array(
				'ping'	=> ord($players[$i]),
				'name'	=> isset($players[$i+1]) ? $this->clean_value($players[$i+1]) : '',
			);
			$i++;
		}
		array_pop($player);
		$player['count'] = sizeof($player);
		return $player;
	}
	
	/**
	 * GS 1 status query
	 * http://www.int64.org/docs/gamestat-protocols/gamespy.html
	 *
	 * @return array with gameserver data
	 */
	public function gamespy1()
	{	
		$this->send("\\info\\");
		$reply = $this->read();
		$reply = preg_split("#\\\#", $reply);
		$this->player = $reply;
		for($i = 0; isset($reply[$i]); $i++)
		{
			if(! empty($reply[$i]))
			{
				$key = $reply[$i];
				$i++;
				$value = isset($reply[$i]) ? $reply[$i] : '';
				$return[$key] = $this->clean_value($value);
			}
		}
		if(isset($return) && sizeof($return))
		{
			$this->gamespy1_player();
			$return['protokoll'] = 'GameSpy 1';
			return $return;
		}
	}
	
	/**
	 * GS 1 player query
	 * http://www.int64.org/docs/gamestat-protocols/gamespy.html
	 *
	 * @return array with player data
	 */
	public function gamespy1_player()
	{
		if(!$this->player)
		{
			$this->send("\\info\\");
			$reply = $this->read();
			$reply = preg_split("#\\\#", $reply);
			$this->player = $reply;
		}
		$reply = $this->player;
		for($i = 0; isset($reply[$i]); $i++)
		{
			if(! empty($reply[$i]))
			{
				$key = $reply[$i];
				$i++;
				$value = isset($reply[$i]) ? $reply[$i] : '';
				$return[$key] = $this->clean_value($value);
			}
		}		
		$player = array();
		for($i = 0; isset($return['player_' . $i]); $i++)
		{
			$player[] = array(
				'name'	=> $return['player_' . $i],
				'score'	=> $return['score_' . $i],
				'ping'	=> $return['ping_' . $i],
				'team'	=> $return['team_' . $i],
				
			);
		}
		$player['count'] = sizeof($player);
		return $player;
	}
	
	/**
	 * GS 2 status query
	 * http://www.int64.org/docs/gamestat-protocols/gamespy2.html
	 *
	 * @return array with gameserver data
	 */
	public function gamespy2()
	{
		$this->send("\xFE\xFD\x00CORY\xFF\x00\x00");
		$delimiter = $this->_get_byte();
		$reply = $this->read();
		$reply = preg_replace('/CORY/', '', $reply);
		$reply = preg_split("#\x{" . $delimiter . "}#", $reply);
		for($i = 0; isset($reply[$i]); $i++)
		{
			if(! empty($reply[$i]))
			{
				$key = $reply[$i];
				$i++;
				$value = isset($reply[$i]) ? $reply[$i] : '';
				$return[$key] = $this->clean_value($value);
			}
		}
		if(isset($return) && sizeof($return))
		{
			$return['protokoll'] = 'GameSpy 2';
			return $return;
		}
	}
	
	/**
	 * GS 2 player query
	 * http://www.int64.org/docs/gamestat-protocols/gamespy2.html
	 *
	 * @return array with player data
	 */
	public function gamespy2_player()
	{
		$this->send("\xFE\xFD\x00\x43\x4F\x52\x59\x00\xFF\xFF");
		$delimiter = $this->_get_byte();
		$reply = $this->read();
		$reply = preg_replace('/CORY/', '', $reply);
		$reply = preg_split("#\x{" . $delimiter . "}#", $reply);
		$data = $type = array();
		foreach($reply as $id => $item)
		{
			$item = str_replace(chr(0), '-', $item);
			$item = str_replace(chr(1), '', $item);
			if($item != '')
			{
				if(substr($item, -1) == '_')
				{
					$type[] = substr($item, 0, -1);
				}
				elseif(substr($item, -2) == '_t')
				{
					break;
				}
				else
				{
					$data[] = $item;
				}
			}
		}
		unset($item, $reply);
		$player = array();
		if($type && $data)
		{
			$players_data = array_chunk($data, sizeof($type));
			foreach($players_data as $i => $data)
			{
				foreach($data as $j => $item)
				{
					$player[$i][trim($type[$j])] = $item;
				}
			}
			
			$player['count'] = sizeof($player);
			return $player;
		}
	}
	
	/**
	 * GS 3 status query
	 *
	 * @return array with gameserver data
	 */
	public function gamespy3()
	{
		$query = "\xFE\xFD\x00\x02\x32\x03\x05\xFF\xFF\xFF\x01";
		$this->send($query);
		$reply = $this->read();
		$reply = preg_replace("#^\x{00}\x{02}...splitnum\x{00}..#", '', $reply);
		$reply = preg_split("#\x{00}#", $reply);
		
		$this->player = $reply;
		
		for($i = 0; isset($reply[$i]); $i++)
		{
			if(! empty($reply[$i]))
			{
				$key = $reply[$i];
				$i++;
				$value = isset($reply[$i]) ? $reply[$i] : '';
				$return[$key] = $this->clean_value($value);
			}
		}
		if(isset($return) && sizeof($return))
		{
			$this->gamespy3_player();
			$return['protokoll'] = 'GameSpy 3';
			return $return;
		}
	}
	
	/**
	 * GS 3 player query
	 *
	 * @return array with player data
	 */
	public function gamespy3_player()
	{
		if(!$this->player)
		{
			$query = "\xFE\xFD\x00\x02\x32\x03\x05\xFF\xFF\xFF\x01";
			$this->send($query);
			$reply = $this->read();
			
			$reply = preg_replace("#^\x{00}\x{02}...splitnum\x{00}..#", '', $reply);
			$reply = preg_split("#\x{00}#", $reply);
		}
		$type = 'other';
		$data = array();
		foreach($this->player as $id => $item)
		{
			if(!empty($item))
			{
				$item = str_replace(chr(1), '', $item);
				if(substr($item, -1) == '_')
				{
					$type = substr($item, 0, -1);
				}
				elseif(substr($item, -2) == '_t')
				{
					$type = 'other';
				}
				else 
				{
					$data[$type][] = $item;
				}
			}
		}
		unset($data['other']);
		$player = array();
		foreach($data as $type => $items)
		{
			foreach($items as $id => $name)
			{
				$player[$id][$type] = $name;
			}
		}
		$player['count'] = sizeof($player);
		return $player;
	}
	
	/**
	 * GS 4 status query
	 * http://wiki.unrealadmin.org/UT3_query_protocol
	 *
	 * @return array with gameserver data
	 */
	public function gamespy4()
	{
		$this->send("\xFE\xFD\x09\x48\x4C\x53\x03");
		$this->read();
		$this->_get_string_part(5);
		
		$challenge = $this->_get_string();
		$send = sprintf("\xFE\xFD\x00\x10\x20\x30\x40%c%c%c%c\xFF\xFF\xFF\x01", ($challenge >> 24), ($challenge >> 16), ($challenge >> 8), ($challenge >> 0));
		$this->send($send);
		$reply = $this->read();
		$reply = preg_replace('/^\x{00}\x{10}\x{20}\x{30}\x{40}splitnum.../', '', $reply);
		$reply = preg_split("#\x{00}#", $reply);
		$this->player = $reply;
		for($i = 0; isset($reply[$i]); $i++)
		{
			if(! empty($reply[$i]))
			{
				$key = $reply[$i];
				$i++;
				$value = isset($reply[$i]) ? $reply[$i] : '';
				$return[$key] = $this->clean_value($value);
			}
		}
		if(isset($return) && sizeof($return))
		{
			$return['protokoll'] = 'GameSpy 4';
			return $return;
		}
	}
	
	/**
	 * GS 4 player query
	 * http://wiki.unrealadmin.org/UT3_query_protocol
	 *
	 * @return array with player data
	 */
	public function gamespy4_player()
	{
		if(!$this->player)
		{
			$this->send("\xFE\xFD\x09\x48\x4C\x53\x03");
			$this->read();
			$this->_get_string_part(5);
			$challenge = $this->_get_string();
			$send = sprintf("\xFE\xFD\x00\x10\x20\x30\x40%c%c%c%c\xFF\xFF\xFF\x01", ($challenge >> 24), ($challenge >> 16), ($challenge >> 8), ($challenge >> 0));
			$this->send($send);
			$reply = $this->read();
			$reply = preg_replace('/^\x{00}\x{10}\x{20}\x{30}\x{40}splitnum.../', '', $reply);
			$reply = preg_split("#\x{00}#", $reply);
			$this->player = $reply;
		}
		$type = 'other';
		$data = array();
		foreach($this->player as $id => $item)
		{
			if(!empty($item))
			{
				$item = str_replace(chr(1), '', $item);
				if(substr($item, -1) == '_')
				{
					$type = substr($item, 0, -1);
				}
				elseif(substr($item, -2) == '_t')
				{
					$type = 'other';
				}
				else 
				{
					$data[$type][] = $item;
				}
			}
		}
		unset($data['other']);
		$player = array();
		foreach($data as $type => $items)
		{
			foreach($items as $id => $name)
			{
				$player[$id][$type] = $name;
			}
		}
		
		$player['count'] = sizeof($player);
		return $player;
	}
	
/*
	function bf3()
	{
		$this->send("\x00\x00\x00\x00\x1b\x00\x00\x00\x01\x00\x00\x00\x0a\x00\x00\x00serverInfo\x00");
		$reply = $this->read();
	die($reply);
	}
	*/
	
	/**
	 * Quake 2 status query
	 *
	 * @return array with gameserver data
	 */
	public function quake2()
	{
		$this->send("\xFF\xFF\xFF\xFFstatus");
		$reply = $this->read();
		$read = preg_replace("#\xFF\xFF\xFF\xFFprint\n\\\#", '', $reply);
		$read = preg_split("#\n#", $read);
		$reply = preg_split("#\\\#", $read[0]);
		for($i = 0; isset($reply[$i]); $i++)
		{
			if(! empty($reply[$i]))
			{
				$key = $reply[$i];
				$i++;
				$value = isset($reply[$i]) ? $reply[$i] : '';
				$return[$key] = $this->clean_value($value);
			}
		}
		unset($read[0]);
	
		$this->player = $read;
		if(isset($return) && sizeof($return))
		{
			$return['maxplayers'] = isset($return['maxclients']) ? $return['maxclients'] : '';
			$return['password'] = isset($return['needpass']) ? $return['needpass'] : '';
			$return['protokoll'] = 'Quake 2';
			return $return;
		}
	}
	/**
	 * Quake 2 player query
	 *
	 * @return array with player data
	 */
	public function quake2_player()
	{
		if(!$this->player)
		{
			$this->send("\xFF\xFF\xFF\xFFstatus");
			$reply = $this->read();
			$read = preg_replace("#\xFF\xFF\xFF\xFFprint\n\\\#", '', $reply);
			$read = preg_split("#\n#", $read);
			unset($read[0]);
			$this->player = $read;
		}
		$i = 1;
		$player = array();
		foreach($this->player as $id => $data)
		{
			if(!empty($data))
			{
				$player_data = preg_split("# #", $data, 3);
				if(sizeof($player_data) == 3)
				{
					$player[$i]['index'] = $i++;
					$player[$i]['score'] = $player_data[0];
					$player[$i]['ping'] = $player_data[1];
					$player[$i]['name'] = $this->clean_value(substr($player_data[2], 1 , strlen($player_data[2])-2));
				}
			}
		}
		$player['count'] = sizeof($player);
		return $player;
	}
	
	/**
	 * Quake 3 status query
	 * http://www.int64.org/docs/gamestat-protocols/quake3.html
	 *
	 * @return array with gameserver data
	 */
	public function quake3()
	{	
		$this->send("\xFF\xFF\xFF\xFFgetstatus");
		$read = $this->read();
		$read = preg_split("#\n#", $read);
		if(isset($read[1]))
		{
			$reply = preg_split("#\\\#", $read[1]);
			for($i = 0; isset($reply[$i]); $i++)
			{
			if(! empty($reply[$i]))
			{
			$key = $reply[$i];
			$i++;
			$value = isset($reply[$i]) ? $reply[$i] : '';
			$return[$key] = $this->clean_value($value);
			}
			}
			unset($read[0]);
			unset($read[1]);
			$this->player = $read;
		}
		if(isset($return) && sizeof($return))
		{
			$return['hostname'] = isset($return['sv_hostname']) ? $return['sv_hostname'] : (isset($return['hostname']) ? $return['hostname'] : '');
			$return['maxplayers'] = isset($return['sv_maxclients']) ? $return['sv_maxclients'] : '';
			$return['gamemode'] = isset($return['g_gametype']) ? $return['g_gametype'] : '';
			$return['password'] = isset($return['g_needpass']) ? $return['g_needpass'] : '';
			$return['protokoll'] = 'Quake 3';
			return $return;
		}
	}
	
	/**
	 * Quake 3 player query
	 * http://www.int64.org/docs/gamestat-protocols/quake3.html
	 *
	 * @return array with player data
	 */
	public function quake3_player()
	{
		if(!$this->player)
		{
			$this->send("\xFF\xFF\xFF\xFFgetstatus");
			$read = $this->read();
			$read = preg_split("#\n#", $read);
			unset($read[0]);
			unset($read[1]);
			$this->player = $read;
		}
		$i = 1;
		$player = array();
		foreach($this->player as $id => $data)
		{
			if(!empty($data))
			{
				$player_data = preg_split("# #", $data, 3);
				$player[] = array(
					'index'	=> $i++,
					'score'	=> $player_data[0],
					'ping'	=> $player_data[1],
					'name'	=> $this->clean_value(substr($player_data[2], 1 , strlen($player_data[2])-2)),
				);
			}
		}
		$player['count'] = sizeof($player);
		return $player;
	}
	
	/**
	 * SOURCE status query
	 * http://developer.valvesoftware.com/wiki/Server_queries
	 *
	 * @return array with gameserver data
	 */
	function source()
	{
		$this->send("\xFF\xFF\xFF\xFFTSource Engine Query\x00\x00");
		if($r = $this->read())
		{
			$this->_get_byte();
			$this->_get_byte();
			$this->_get_byte();
			$this->_get_byte();

			$h = $this->_get_char();

			if($h == "I")
			{

				$return = array(
					'network_version'	=> $this->_get_byte(),
					'hostname'			=> $this->clean_value($this->_get_string()),
					'mapname'			=> $this->_get_string(),
					'directory'			=> $this->_get_string(),
					'discription'		=> $this->_get_string(),
					'steam_id'			=> $this->_get_int16(),
					'numplayers'		=> $this->_get_byte(),
					'maxplayers'		=> $this->_get_byte(),
					'bots'				=> $this->_get_byte(),
					'dedicated'			=> $this->_get_char(),
					'os'				=> $this->_get_char(),
					'password'			=> $this->_get_byte(),
					'secure'			=> $this->_get_byte(),
					'version'			=> $this->_get_string(),
					'protokoll'			=> 'Source Engine'
				);

				return $return;
			}

			// Old gold source
			if($h == 'm')
			{
				$return = array(
					'ip_address'		=> $this->_get_string(),
					'hostname'			=> $this->clean_value($this->_get_string()),
					'mapname'			=> $this->_get_string(),
					'directory'			=> $this->_get_string(),
					'discription'		=> $this->_get_string(),
					'steam_id'			=> '',
					'numplayers'		=> $this->_get_byte(),
					'maxplayers'		=> $this->_get_byte(),
					'protokoll'			=> $this->_get_byte(),
					'dedicated'			=> $this->_get_char(),
					'os'				=> $this->_get_char(),
					'public'			=> $this->_get_byte(),
					'mod'				=> $this->_get_byte(),
					'secure'			=> $this->_get_byte(),
					'bots'				=> $this->_get_byte(),
					'protokoll'			=> 'Source Engine Gold'
				);

				return $return;
			}
		}
	}
	
	/**
	 * SOURCE player query
	 * http://developer.valvesoftware.com/wiki/Server_queries
	 *
	 * @return array with player data
	 */
	function source_player()
	{

		$this->send("\xFF\xFF\xFF\xFF\x55\xFF\xFF\xFF\xFF");
		$r = $this->read();
		$this->_get_int32();
		$this->_get_byte();
		$challenge = $this->_read_result(4);
		$send = "\xFF\xFF\xFF\xFF\x55" . $challenge;
		$this->send($send);
		if($this->read())
		{
			$this->_get_byte();
			$this->_get_byte();
			$this->_get_byte();
			$this->_get_byte();

			if ($this->_get_char() == 'D')
			{
				$players = $this->_get_byte();
				$player = array();
				for($i=1; $i <= $players; $i++)
				{

					$player[] = array(
						'index'	=> $this->_get_byte(),
						'name'	=> $this->_get_string(),
						'score'	=> $this->_get_int32(),
						'time'	=> date('H:i:s', round($this->_get_float32(), 0)+82800),
					);
				}
				$player['count'] = sizeof($player);


				return $player;
			}
		}
	}

	
	/**
	 * SAMP status query
	 * http://wiki.sa-mp.com/wiki/Query_Mechanism
	 *
	 * @return array with gameserver data
	 */
	public function samp()
	{
		$ip = explode('.', $this->ip);
		$this->send('SAMP' . chr($ip[0]) . chr($ip[1]) . chr($ip[2]) . chr($ip[3]) . chr($this->port & 0xFF) . chr($this->port >> 8 & 0xFF) . 'i');
		if($this->read())
		{
			$this->_get_string_part(11);
			return array(
				'password' => $this->_get_byte(),
				'numplayers' => $this->_get_int16(),
				'maxplayers' => $this->_get_int16(),
				'hostname' => $this->clean_value($this->_get_string_part($this->_get_int32())),
				'gamemode' => $this->_get_string_part($this->_get_int32()),
				'mapname' => $this->_get_string_part($this->_get_int32())
			);
		}
	}
	
	/**
	 * SAMP player query
	 * http://wiki.sa-mp.com/wiki/Query_Mechanism
	 *
	 * @return array with player data
	 */
	public function samp_player()
	{
		$ip = explode('.', $this->ip);
		$this->send('SAMP' . chr($ip[0]) . chr($ip[1]) . chr($ip[2]) . chr($ip[3]) . chr($this->port & 0xFF) . chr($this->port >> 8 & 0xFF) . 'd');
		$this->read();
		$this->_get_string_part(11);
		$players = $this->_get_int16();
		$player = array();
		for($i=1; $i <= $players; $i++)
		{
			$player[$i]['index'] = $this->_get_byte();
			$player[$i]['name'] = $this->_get_string_part($this->_get_byte());
			$player[$i]['score'] = $this->_get_int32();
			$player[$i]['ping'] = $this->_get_int32();
			
		}
		$player['count'] = sizeof($player);
		return $player;
	}	
}
