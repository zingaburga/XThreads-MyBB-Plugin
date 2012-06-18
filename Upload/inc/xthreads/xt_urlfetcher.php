<?php
if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

/**
 * Abstract class/interface to handle URL fetching
 */
/*abstract*/ class XTUrlFetcher {
	/**
	 * URL to fetch
	 */
	var $url;
	/**
	 * Timeout in seconds
	 */
	var $timeout = 30;
	
	/**
	 * Number of redirects (Location header) to follow.  0 to disable.
	 * Doesn't work properly with cURL fetcher
	 */
	var $follow_redir = 5;
	/**
	 * Contents of Referrer HTTP header
	 */
	var $referer=null;
	/**
	 * Contents of User-Agent HTTP header
	 */
	var $user_agent=null;
	
	/**
	 * Callback function to send meta information to; not guaranteed to be called, and values received shouldn't be completely trusted
	 * Should accept 3 arguments: this object, meta name and value
	 * Meta names can be:
	 *  - retcode: HTTP response code, sent as an array, eg array(404, 'Not Found')
	 *  - size: size of file in bytes (HTTP Content-Length header)
	 *  - name: custom filename (HTTP Content-Disposition header)
	 *  - type: sent MIME type (HTTP Content-Type header)
	 * Should return true if everything is well, false to abort the transfer
	 */
	var $meta_function=null;
	/**
	 * Callback function to process chunks of body data; if not set, will return all data on fetch() call
	 * Should accept two arguments: this object and data
	 *  -> Note that although the variables are passed as references (for speed purposes), they should NOT be modified
	 * Should return true if everything is well, false to abort
	 */
	var $body_function=null;
	
	/**
	 * Error number and string
	 */
	/*protected*/ var $_errno=null;
	/*protected*/ var $_errstr=null;
	
	/**
	 * Whether or not connection was aborted by calling app
	 * Should not be written to externally
	 */
	var $aborted=false;
	
	/**
	 * Whether this fetcher can be used
	 * @return boolean true if fetcher can be used
	 */
	//abstract static function available();
	
	/**
	 * Free allocated resources
	 */
	function close() {}
	function __destruct() {
		$this->close();
	}
	
	/**
	 * Fetch $url
	 * @return true if successful, false if not, and null if aborted
	 *         if body_function not supplied, will return fetched data
	 */
	//abstract function fetch();
	
	/**
	 * Set $referer based on $url; uses the host of $url as the referer
	 */
	function setRefererFromUrl() {
		$purl = parse_url($this->url);
		$this->referer = $purl['scheme'].'://'.$purl['host'].'/';
	}
	
	/**
	 * Generate an array of headers; does not include Host or GET header
	 */
	/*protected*/ function &_generateHeaders() {
		$headers = array(
			'Connection: close',
			'Accept: */*',
		);
		// TODO: follow_redir, encoding
		
		if(isset($this->user_agent))
			$headers[] = 'User-Agent: '.$this->user_agent;
		if(isset($this->charset))
			$headers[] = 'Accept-Charset: '.$this->charset.';q=0.5, *;q=0.2';
		if(isset($this->lang))
			$headers[] = 'Accept-Language: '.$this->lang.';q=0.5, *;q=0.3';
		if(isset($this->referer))
			$headers[] = 'Referrer: '.$this->referer;
		
		return $headers;
	}
	
	/**
	 * Processes a HTTP header, and calls the meta function if necessary
	 * @return false to abort, true otherwise
	 */
	/*protected*/ function _processHttpHeader($header) {
		if(!isset($this->meta_function)) return true;
		
		$result = self::_processHttpHeader_parse($header);
		if(empty($result)) return true;
		foreach($result as $mname => &$mdata) {
			if(!call_user_func_array($this->meta_function, array(&$this, &$mname, &$mdata))) {
				$this->aborted = true;
				return false;
			}
		}
		
		return true;
	}
	/**
	 * Parse info from HTTP header
	 * @return array of info retrieved, or null if nothing retrieved
	 */
	/*private*/ static function _processHttpHeader_parse(&$header) {
		$header = trim($header);
		$p = strpos($header, ':');
		if(!$p) {
			// look for HTTP/1.1 type header
			if(strtoupper(substr($header, 0, 5)) == 'HTTP/') {
				if(preg_match('~^HTTP/[0-9.]+ (\d+) (.*)$~i', $header, $match)) {
					return array('retcode' => array((int)$match[1], trim($match[2])));
				}
			}
			return null;
		}
		$hdata = trim(substr($header, $p+1));
		switch(strtolower(substr($header, 0, $p))) {
			case 'content-length':
				$size = (int)$hdata;
				if($size || $hdata === '0') {
					return array('size' => $size);
				}
			break;
			case 'content-disposition':
				foreach(explode(';', $hdata) as $disp) {
					$disp = trim($disp);
					if(strtolower(substr($disp, 0, 9)) == 'filename=') {
						$tmp = substr($disp, 9);
						if(!xthreads_empty($tmp)) {
							if($tmp{0} == '"' && $tmp{strlen($tmp)-1} == '"')
								$tmp = substr($tmp, 1, -1);
							return array('name' => trim(str_replace("\x0", '', $tmp)));
						}
					}
				}
			break;
			case 'content-type':
				return array('type' => $hdata);
			break;
		}
		return null;
	}
	
	// since fread'ing won't necessarily fill the requested buffer size...
	/*protected*/ static function &fill_fread(&$fp, $len) {
		//$fill = 0;
		$ret = '';
		while(!feof($fp) && $len > 0) {
			$data = fread($fp, $len);
			$len -= strlen($data);
			$ret .= $data;
		}
		return $ret;
	}
	
	/**
	 * Get error code/message
	 * @param [out] variable to receive error code
	 * @return error message
	 *         special messages are 'cantwritesocket', 'headernotfound' and 'urlopenfailed'
	 */
	function getError(&$code=null) {
		$code = $this->errno;
		return $this->errstr;
	}
}

/**
 * Fetch URL through cURL
 */
class XTUrlFetcher_Curl extends XTUrlFetcher {
	/**
	 * Internal cURL resource handle
	 */
	/*private*/ var $_ch;
	
	/*const*/ var $name = 'cURL';
	
	static function available($scheme='') {
		return (!$scheme || $scheme != 'data') && function_exists('curl_init');
	}
	
	function XTUrlFetcher_Curl() {
		$this->_ch = curl_init();
	}
	function close() {
		if(isset($this->_ch))
			@curl_close($this->_ch); // curl_close may not succeed if called within callback
	}
	
	function fetch() {
		curl_setopt($this->_ch, CURLOPT_URL, $this->url);
		curl_setopt($this->_ch, CURLOPT_HEADER, false);
		curl_setopt($this->_ch, CURLOPT_TIMEOUT, $this->timeout);
		if(isset($this->user_agent))
			curl_setopt($this->_ch, CURLOPT_USERAGENT, $this->user_agent);
		if(isset($this->referer))
			curl_setopt($this->_ch, CURLOPT_REFERER, $this->referrer);
		
		if($this->follow_redir) {
			if(defined('CURLOPT_AUTOREFERER'))
				curl_setopt($this->_ch, CURLOPT_AUTOREFERER, true);
			// PHP safe mode may restrict the following
			@curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($this->_ch, CURLOPT_MAXREDIRS, $this->follow_redir);
		}
		// TODO: 
		curl_setopt($this->_ch, CURLOPT_ENCODING, '');
		
		if(isset($this->meta_function)) {
			// can only use this if http/s request
			if(strtolower(substr($this->url, 0, 4)) == 'http')
				curl_setopt($this->_ch, CURLOPT_HEADERFUNCTION, array($this, 'curl_header_func'));
		}
		if(isset($this->body_function)) {
			curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, false);
			curl_setopt($this->_ch, CURLOPT_WRITEFUNCTION, array($this, 'curl_body_func'));
		} else
			curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
		
		$success = curl_exec($this->_ch);
		if($this->aborted)
			return null;
		else
			return $success;
	}
	
	function getError(&$code=null) {
		$this->errno = curl_errno($this->_ch);
		$this->errstr = curl_error($this->_ch);
		return parent::getError($code);
	}
	
	function curl_header_func(&$ch, $header) {
		if($this->_processHttpHeader(trim($header)))
			return strlen($header);
		else {
			$this->close();
			return 0;
		}
	}
	function curl_body_func(&$ch, $data) {
		if(call_user_func_array($this->body_function, array(&$this, &$data)))
			return strlen($data);
		else {
			$this->aborted = true;
			$this->close();
			return 0;
		}
	}
}


/**
 * Fetch URL through Sockets
 */
class XTUrlFetcher_Socket extends XTUrlFetcher {
	/**
	 * Optional preferred character set to send via Accept header
	 */
	var $charset;
	/**
	 * Optional preferred language set to send via Accept header
	 */
	var $lang;
	
	/*const*/ var $name = 'Sockets';
	
	static function available($scheme='') {
		return (!$scheme || $scheme == 'http' || $scheme == 'https') && function_exists('fsockopen');
	}
	
	function fetch() {
		$redirs = $this->follow_redir;
		$url = $this->url;
		do {
			$redirect = false;
			$purl = @parse_url($url);
			if(empty($purl) || !isset($purl['host'])) {
				$this->errno = 0;
				$this->errstr = 'invalidurl';
				return false;
			}
			if(!isset($purl['path']) || $purl['path'] === '')
				$purl['path'] = '/';
			if(@$purl['query'])
				$purl['path'] .= '?'.@$purl['query'];
			if(!$purl['port']) $purl['port'] = ($purl['scheme']=='https' ? 443:80);
			if(!($fr = @fsockopen(($purl['scheme']=='https'?'ssl://':'').$purl['host'], $purl['port'], $errno, $errstr, $this->timeout))) {
				$this->errno = $errno;
				$this->errstr = $errstr;
				return false;
			}
			@stream_set_timeout($fr, $this->timeout);
			$headers = array_merge(array(
				'GET '.$purl['path'].' HTTP/1.1',
				'Host: '.$purl['host'],
			), $this->_generateHeaders());
			
			$headers[] = "\r\n";
			
			if(!@fwrite($fr, implode("\r\n", $headers))) {
				$this->errno = 0;
				$this->errstr = 'cantwritesocket';
				fclose($fr);
				return false;
			}
			
			$databuf = ''; // returned string if no body_function defined
			$doneheaders = false;
			while(!feof($fr)) {
				if(!$doneheaders) {
					$data = self::fill_fread($fr, 16384);
					$len = strlen($data);
					$p = strpos($data, "\r\n\r\n");
					if(!$p || $p > 12288 || substr($data, 0, 4) != 'HTTP') { // should be no reason to have >12KB headers
						$this->errno = 0;
						$this->errstr = 'headernotfound';
						break;
					}
					$headerdata = substr($data, 0, $p);
					// check redirect
					if($redirs && preg_match("~\r\nlocation\:([^\r\n]*?)\r\n~i", $headerdata, $match)) {
						$url = trim($match[1]);
						if($url) {
							$redirect = true;
							--$redirs;
							break;
						}
					}
					// parse headers
					if(isset($this->meta_function)) {
						foreach(explode("\r\n", $headerdata) as $header) {
							if(!$this->_processHttpHeader(trim($header))) {
								break;
							}
						}
						if($this->aborted) break;
					}
					
					$p += 4;
					$data = substr($data, $p);
					$len -= $p;
					$doneheaders = true;
				} else {
					$len = 0;
					while(!feof($fr) && !$len) {
						$data = fread($fr, 16384);
						$len = strlen($data);
					}
				}
				if($len) {
					if(isset($this->body_function)) {
						if(!call_user_func_array($this->body_function, array(&$this, &$data))) {
							$this->aborted = true;
							break;
						}
					} else {
						$databuf .= $data;
					}
				}
			}
			fclose($fr);
			if($this->aborted) return null;
			if(isset($this->errstr)) return false;
		} while($redirect);
		
		if(isset($this->body_function)) return true;
		return $databuf;
	}
}

/**
 * Fetch URL through PHP fopen
 */
class XTUrlFetcher_Fopen extends XTUrlFetcher {
	var $name = 'fopen';
	
	static function available($scheme='') {
		return ($scheme == 'data') || @ini_get('allow_url_fopen');
		// data:// streams don't require allow_url_fopen
	}
	
	function fetch() {
		$httpopts = array(
			'header' => $this->_generateHeaders(),
			'max_redirects' => $this->follow_redir
		);
		$context = stream_context_create(array(
			'http' => $httpopts,
			'https' => $httpopts
		));
		//if(isset($this->user_agent))
		//	@ini_set('user_agent', $this->user_agent);
		if(!($fr = @fopen($this->url, 'rb', false, $context))) {
			$this->errno = 0;
			$this->errstr = 'urlopenfailed';
			return false;
		}
		@stream_set_timeout($fr, $this->timeout);
		
		// send headers if possible
		$meta = @stream_get_meta_data($fr);
		if(isset($meta['wrapper_data'])) {
			foreach($meta['wrapper_data'] as $header) {
				if(!$this->_processHttpHeader($header)) {
					fclose($fr);
					return null;
				}
			}
		}
		
		
		$databuf = ''; // returned string if no body_function defined
		while(!feof($fr)) {
			$len = 0;
			while(!feof($fr) && !$len) {
				$data = fread($fr, 16384);
				$len = strlen($data);
			}
			
			if($len) {
				if(isset($this->body_function)) {
					if(!call_user_func_array($this->body_function, array(&$this, &$data))) {
						$this->aborted = true;
						break;
					}
				} else {
					$databuf .= $data;
				}
			}
		}
		fclose($fr);
		if($this->aborted) return null;
		//if(isset($this->errstr)) return false;
		
		if(isset($this->body_function)) return true;
		return $databuf;
	}
}

/**
 * URL fetcher factory method
 * @return a new XTUrlFetcher object, depending on what is available
 */
function getXTUrlFetcher($scheme='') {
	$scheme = strtolower($scheme);
	if($p = strpos($scheme, ':'))
		$scheme = substr($scheme, 0, $p);
	if(XTUrlFetcher_Curl::available($scheme))
		return new XTUrlFetcher_Curl;
	if(XTUrlFetcher_Socket::available($scheme))
		return new XTUrlFetcher_Socket;
	if(XTUrlFetcher_Fopen::available($scheme))
		return new XTUrlFetcher_Fopen;
	
	return null; // nothing can fetch it for us... >_>
}


// TODO add: support for data:// stream, FTP in cURL/fsockopen?
