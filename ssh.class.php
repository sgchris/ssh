<?php

/*
 * ssh client for using from php
 *
 * Checking:
 * $ php -m | grep ssh
 * 
 * Installing:
 * $ sudo pecl install -f ssh2
 * $ echo 'extension=ssh2.so' > /etc/php5/conf.d/ssh2.ini
 *
 * Using:
 * |
 * |- Connect:
 * |  | $ssh = new ssh('localhost', 'root', 'password');
 * |
 * |- Exec command:
 * |  | $result = $ssh("ls -la");
 * |  | or
 * |  | $array_of_result = $ssh(array("ls -la", "uptime"));
 * |  | or
 * |  | $array_of_result = $ssh("ls -la", "uptime");
 * |
 * |- Tunnel:
 * |  | $ssh-tunnel("10.0.0.100", 1234);
 * |
 * |- Download:
 * |  | $ssh->download("/remote/file", "/local/file");
 * |
 * |- Upload:
 * |  | $ssh->upload("/local/file", "/remote/file");
 * |
 * |- Reconnect:
 * |  | $ssh->reconnect();
 * |
 * |- Disconnect is automatic;
 *
 * Thx 4 using;
 *
 */





class ssh {
	private $host      = "localhost";
	private $login     = "root";
	private $password  = "";
	private $port      = 22;
	private $connect   = "";
	public  $connected = FALSE;

	public function __construct($host = "localhost", $login = "root", $password = "", $port = 22) {
		$this->host     = $host;
		$this->login    = $login;
		$this->password = $password;
		$this->port     = $port;
	}

	public function __destruct() {
		$this->disconnect();
	}

	public function __invoke() {
		$out = array();
		if(func_num_args() === 1) {
			if(is_array(func_get_arg(0))) {
				return call_user_func_array(array($this, "__invoke"), func_get_arg(0));
			} else {
				return call_user_func(array($this, "exec"), func_get_arg(0));
			}
		} else {
			foreach(func_get_args() as $k => $arg) {
				if(is_array($arg)) {
					$out[$k] = call_user_func_array(array($this, "__invoke"), $arg);
				} else {
					$out[$k] = call_user_func(array($this, "exec"), $arg);
				}
			}
		}
		return $out;
	}

	public function tunnel($host = "localhost", $port = 22) {
		if ( ! $this->connected) {
			$this->connect();
		}
		return ssh2_tunnel($this->connect, $host, $port);
	}

	public function reconnect() {
		if ($this->connected) {
			ssh2_exec($this->connect, 'exit');
		}
		return $this->connect();
	}

	public function download($remote_file = "/", $local_file = "/") {
		if ( ! $this->connected) {
			$this->connect();
		}
		ssh2_scp_recv($this->connect, $remote_file, $local_file);
	}

	public function upload($local_file = "/", $remote_file = "/", $chmod = 0644) {
		if ( ! $this->connected) {
			$this->connect();
		}
		return ssh2_scp_send($this->connect, $local_file, $remote_file, $chmod);
	}

	private function connect() {
		if ( ! $this->connected) {
			$this->connect   = ssh2_connect($this->host, $this->port);
			$this->connected = ssh2_auth_password($this->connect, $this->login, $this->password);
			if ( ! $this->connected) {
				print "Fail: Unable auth\n";
			}
		}
		return $this->connected;
	}

	private function exec($cmd) {
		if ( ! $this->connected) {
			$this->connect();
		}
		$data = "";
		$stream = ssh2_exec($this->connect, $cmd);
		if($stream) {
			stream_set_blocking($stream, true);
			while($buf = fread($stream, 4096)) {
				$data .= $buf;
			}
			fclose($stream);
		} else {
			print "Fail: Unable to execute command\n";
		}
		return $data;
	}

	private function disconnect () {
		if ($this->connected) {
			ssh2_exec($this->connect, 'exit');
		}
	}
}

?>