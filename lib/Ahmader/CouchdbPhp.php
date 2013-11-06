<?php
/*
Copyright (c) 2009 <ahmader@gmail.com>

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/
?>
<?php
namespace Ahmader;

class CouchDBResponse {

	private $raw_response = '';
	private $headers = '';
	private $body = '';


	function __construct($response = '') {
		$this->raw_response = $response;
		list($this->headers, $this->body) = explode("\r\n\r\n", $response);
	}

	function getRawResponse() {
		return $this->raw_response;
	}

	function getHeaders() {
		return $this->headers;
	}

	function getBody($decode_json = false) {
		return $decode_json ? CouchdbPhp::decode_json($this->body) : $this->body;
	}
}

class CouchDBRequest {

	static $VALID_HTTP_METHODS = array('DELETE', 'GET', 'POST', 'PUT');

	private $method = 'GET';
	private $url = '';
	private $data = NULL;
	private $sock = NULL;
	private $username;
	private $password;

	function __construct($host, $port = 5984, $url, $method = 'GET', $data = NULL, $username = null, $password = null) {
		$method = strtoupper($method);
		$this->host = $host;
		$this->port = $port;
		$this->url = $url;
		$this->method = $method;
		$this->data = $data;
		$this->username = $username;
		$this->password = $password;

		if(!in_array($this->method, self::$VALID_HTTP_METHODS)) {
			throw new CouchdbException('Invalid HTTP method: '.$this->method, 2);
		}
	}

	function getRequest() {
		$req = "{$this->method} {$this->url} HTTP/1.0\r\nHost: {$this->host}\r\n";

		if($this->username || $this->password)
			$req .= 'Authorization: Basic '.base64_encode($this->username.':'.$this->password)."\r\n";

		if($this->data) {
			$req .= 'Content-Length: '.strlen($this->data)."\r\n";
			$req .= 'Content-Type: application/json'."\r\n\r\n";
			$req .= $this->data."\r\n";
		} else {
			$req .= "\r\n";
		}

		return $req;
	}

	private function connect() {
		$this->sock = @fsockopen($this->host, $this->port, $err_num, $err_string);
		if(!$this->sock) {
			throw new CouchdbException('Could not open connection to '.$this->host.':'.$this->port.' ('.$err_string.')', 1);
		}
	}

	private function disconnect() {
		fclose($this->sock);
		$this->sock = NULL;
	}

	private function execute() {
		fwrite($this->sock, $this->getRequest());
		$response = '';
		while(!feof($this->sock)) {
			$response .= fgets($this->sock);
		}
		$this->response = new CouchDBResponse($response);
		return $this->response;
	}

	function send() {
		$this->connect();
		$this->execute();
		$this->disconnect();
		return $this->response;
	}

	function getResponse() {
		return $this->response;
	}
}

class CouchdbPhp {

	private $username;
	private $password;

	function __construct($db, $host = 'localhost', $port = 5984, $username = null, $password = null) {
		$this->db = urlencode($db);
		$this->host = $host;
		$this->port = $port;
		$this->username = $username;
		$this->password = $password;
	}

	static function decode_json($str) {
		return json_decode($str);
	}

	static function encode_json($str) {
		return json_encode($str);
	}

	function send($url, $method = 'get', $data = NULL) {
		$url = (!empty($this->db) ? '/'.$this->db:'').(substr($url, 0, 1) == '/' ? $url : '/'.$url);
		$request = new CouchDBRequest($this->host, $this->port, $url, $method, $data, $this->username, $this->password);
		return $request->send();
	}

	function get_all_dbs() {
		$_db=$this->db;
		$this->db='';
		$response=$this->send('/_all_dbs');
		$this->db=$_db;

		//$databases=array();
		
		return json_decode($response->getBody());
	}

	function get_all_docs($id='', $start='', $end='') {
		$query='';
		if (!empty($start))
			$query='?startkey="'.$start.'"';

		if (!empty($start) && !empty($end))
			$query.='&endkey="'.$end.'"';

		$response=$this->send($id.'/_all_docs'.$query);

		return json_decode($response->getBody());
	}

	function get_item($id) {
		$response=$this->send($id);
		return json_decode($response->getBody());
	}
	function post_item($id, $data) {
		$response=$this->send($id, 'POST', $data);
		return json_decode($response->getBody());
	}
	function put_item($id, $data) {
		$response=$this->send($id, 'PUT', $data);
		return json_decode($response->getBody());
	}
	function remove_item($id) {
		$response=$this->send($id, 'DELETE');
		return json_decode($response->getBody());
	}
}
?>
