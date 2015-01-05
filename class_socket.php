<?php 
/**
 *
 * @author tobi.schaefer@gmail.com
 * @package Gameserver Query
 * @copyright (c) 2014 Tobias Schäfer
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */


class socket
{
	private $socket = null;
	
	/**
	 * Connect to a socket
	 *
	 * @param string $ip
	 *        	IP to connect
	 * @param string $port
	 *        	Port to connect
	 * @param string $network_protocol
	 *        	tcp or udp? default:udp
	 * @return bool true if connection success
	 * @access public
	 */
	public function connect($ip, $port, $network_protocol = 'udp')
	{
		$ip = gethostbyname($ip);
		$port = (int) $port;
		isset($this->query_counter['socket']) ? $this->query_counter['socket']++ : ($this->query_counter['socket'] = 1);
	
		if($network_protocol == 'tcp')
		{
			$context = stream_context_create();
			if($socket = stream_socket_client("tcp://{$ip}:{$port}", $errno, $error, (1 / 10), STREAM_CLIENT_CONNECT, $context))
			{
				$this->socket = $socket;
				return true;
			}
			else
			{
				die('Failed to open socket');
			}
		}
		else
		{
			if($socket = fsockopen('udp://' . $ip, $port, $errno, $error, 3))
			{
				socket_set_blocking($socket, false);
				$this->socket = $socket;
				return true;
			}
			else
			{
				die('Failed to open socket');
			}
		}
	}
	
	/**
	 * Close a socket
	 *
	 * @param resource $socket
	 *        	the socket to close
	 * @access public
	 */
	public function close()
	{
		if(is_resource($this->socket))
		{
			fclose($this->socket);
		}
	}
	
	/**
	 * Send data to a socket
	 *
	 * @param resource $socket
	 *        	socket to send to
	 * @param string $data
	 *        	data to send
	 * @access public
	 */
	public function write($data)
	{
		if(is_resource($this->socket))
		{
			return fwrite($this->socket, $data, strlen($data));
		}
	}
	
	/**
	 * Read data from a socket
	 *
	 * @param resource $socket
	 *        	socket to read from
	 * @param int $timeout
	 *        	timeout im ms
	 * @param string $size
	 *        	size of the data to read in byte
	 * @return string Data recived from socket
	 * @access public
	 */
	public function read($timeout = 500, $size = 8192)
	{
		if(is_resource($this->socket))
		{
			$loops = 0;
			$starttime = microtime(true);
			$read = array($this->socket);
			$null = null;
			
			/*$a = stream_get_meta_data($this->socket);
			print_r($a);
			$result = stream_socket_recvfrom($this->socket, 1024);
			//die($result);
			*/
			while(($t = $timeout * 1000 - (microtime(true) - $starttime) * 10000) > 0)
			{
				$s = stream_select($read, $null, $null, 0, $t);
				if(($s === false || $s <= 0) || ++$loops > 200)
				{
					break;
				}
	
				if($size > 8192)
				{
					$buffer = '';
					while(! feof($this->socket))
					{
						$buffer .= fgets($this->socket);
					}
					$result = trim($buffer);
				}
				else
				{
					$result = stream_socket_recvfrom($this->socket, $size);
				}
				
				return $result;
			}
		}
	}
}