<?php
class http {//类定义开始
    /**
     +----------------------------------------------------------
     * 下载文件
     * 可以指定下载显示的文件名，并自动发送相应的Header信息
     * 如果指定了content参数，则下载该参数的内容
     +----------------------------------------------------------
     * @static
     * @access public
     +----------------------------------------------------------
     * @param string $filename 下载文件名
     * @param string $showname 下载显示的文件名
     * @param string $content  下载的内容
     * @param integer $expire  下载内容浏览器缓存时间
     +----------------------------------------------------------
     * @return void
     +----------------------------------------------------------
     * @throws ThinkExecption
     +----------------------------------------------------------
     */
    static function download ($filename, $showname='',$content='',$expire=600){
		if ('' != $content){
			$size = strlen($content);
		}elseif(is_file($filename)){
			$size = sprintf("%u", filesize($filename));
		}
		if (!$size) exit($filename.' Not found.');
        if(empty($showname)) $showname = $filename;
        $showname = basename($showname);
		$time = date('Y-m-d H:i:s');
		if (!empty($filename)) $type = mime_content_type($filename);
		if (!$type) $type = "application/octet-stream";
		if (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
			$showname = str_replace('+','%20',urlencode($showname));
		}
		header("Cache-Control:");
		header("Cache-Control: public");
		header("Content-Type: $type");
		header("Content-Disposition: attachment; filename=" . $showname);
		header('Content-Length: '.$size);
		if ('' != $content) exit($content);
		header('Accept-Ranges: bytes');
		if(isset($_SERVER['HTTP_RANGE'])){
			list ($a, $range) = explode("=", $_SERVER['HTTP_RANGE']);
			$length = $size-1-$range;
			header('HTTP/1.1 206 Partial Content');
			header('Content-Length: '.($length>0 ?$length:0));
			header('Content-Range: bytes '.$range.($size-1).'/'.$size);
		}else{
			header("Content-Range: bytes 0-".($size-1)."/$size");
			header('Content-Length: '.$size);
		}
		if (FALSE === ($fp = fopen($filename, 'rb'))) return $fp;
		ini_set('memory_limit', '256M');
		fseek($fp, $range);
		set_time_limit(0);
		ob_start();
		while (!feof($fp)){
			echo (fread($fp, 1024*8));
			flush();
			ob_flush();
		}
		ob_end_flush();
		fclose($fp);
		exit;
    }
    /**
     +----------------------------------------------------------
     * 显示HTTP Header 信息
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    static function get_header_info($header='',$echo=true)
    {
        ob_start();
        $headers   = getallheaders();
        if(!empty($header)) {
            $info = $headers[$header];
            echo($header.':'.$info."\n"); ;
        }else {
            foreach($headers as $key=>$val) {
                echo("$key:$val\n");
            }
        }
        $output = ob_get_clean();
        if ($echo) {
            echo (nl2br($output));
        }else {
            return $output;
        }

    }

    /**
     * HTTP Protocol defined status codes
     * @param int $num
     */
	static function send_http_status($code) {
		static $_status = array(
			// Informational 1xx
			100 => 'Continue',
			101 => 'Switching Protocols',

			// Success 2xx
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',

			// Redirection 3xx
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',  // 1.1
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			// 306 is deprecated but reserved
			307 => 'Temporary Redirect',

			// Client Error 4xx
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',

			// Server Error 5xx
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			509 => 'Bandwidth Limit Exceeded'
		);
		if(array_key_exists($code,$_status)) {
			header('HTTP/1.1 '.$code.' '.$_status[$code]);
		}
	}
	static function request($url, $data=array(), $timeout=2) {
		if ( !function_exists('curl_init') ) { return empty($data) ? self::DoGet($url, $timeout) : self::DoPost($url, $data, $timeout); }
		$ch = curl_init();
		if (is_array($data) AND $data) {
			$formdata = http_build_query($data);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $formdata);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		$return = curl_exec($ch);
		curl_close($ch);
		return $return;
	}
	static function DoGet($url, $fsock_timeout=2){
		$url2 = parse_url($url);
		$url2["path"] = ($url2["path"] == "" ? "/" : $url2["path"]);
		$url2["port"] = (!isset($url2['port']) || empty($url2["port"])) ? 80 : $url2["port"];
		$host_ip = @gethostbyname($url2["host"]);
		if(($fsock = fsockopen($host_ip, $url2['port'], $errno, $errstr, $fsock_timeout)) < 0){
			return false;
		}
		$request =  $url2["path"] .($url2["query"] ? "?".$url2["query"] : "");
		$in  = "GET " . $request . " HTTP/1.0\r\n";
		$in .= "Accept: */*\r\n";
		$in .= "User-Agent: Payb-Agent\r\n";
		$in .= "Host: " . $url2["host"] . "\r\n";
		$in .= "Connection: Close\r\n\r\n";
		if(!@fwrite($fsock, $in, strlen($in))){
			fclose($fsock);
			return false;
		}
		return self::_GetHttpContent($fsock);
	}

	static function DoPost($url,$post_data=array(), $fsock_timeout=2){
		$url2 = parse_url($url);
		$url2["path"] = ($url2["path"] == "" ? "/" : $url2["path"]);
		$url2["port"] = (!isset($url2['port']) || empty($url2["port"])) ? 80 : $url2["port"];
		$host_ip = @gethostbyname($url2["host"]);
		if(($fsock = fsockopen($host_ip, $url2['port'], $errno, $errstr, $fsock_timeout)) < 0){
			return false;
		}
		$request =  $url2["path"].($url2["query"] ? "?" . $url2["query"] : "");
		$post_data2 = http_build_query($post_data);
		$in  = "POST " . $request . " HTTP/1.0\r\n";
		$in .= "Accept: */*\r\n";
		$in .= "Host: " . $url2["host"] . "\r\n";
		$in .= "User-Agent: Lowell-Agent\r\n";
		$in .= "Content-type: application/x-www-form-urlencoded\r\n";
		$in .= "Content-Length: " . strlen($post_data2) . "\r\n";
		$in .= "Connection: Close\r\n\r\n";
		$in .= $post_data2 . "\r\n\r\n";
		unset($post_data2);
		if(!@fwrite($fsock, $in, strlen($in))){
			fclose($fsock);
			return false;
		}
		return self::_GetHttpContent($fsock);
	}
	static private function _GetHttpContent($fsock=null) {
		$out = null;
		while($buff = @fgets($fsock, 2048)){
			$out .= $buff;
		}
		fclose($fsock);
		$pos = strpos($out, "\r\n\r\n");
		$head = substr($out, 0, $pos);    //http head
		$status = substr($head, 0, strpos($head, "\r\n"));    //http status line
		$body = substr($out, $pos + 4, strlen($out) - ($pos + 4));//page body
		if(preg_match("/^HTTP\/\d\.\d\s([\d]+)\s.*$/", $status, $matches)){
			if(intval($matches[1]) / 100 == 2){
				return $body;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
}//类定义结束
?>