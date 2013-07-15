<?php if ( ! defined('ROOT')) exit('No direct script access allowed');

function __autoload($class){
	if (!$class OR !is_scalar($class)) return ;
	$dir = gc('env.directory');
	$ctrl_suffix = gc('base.controller_suffix');
	$model_suffix = gc('base.model_suffix');
	if (FALSE !== strpos($class, $ctrl_suffix)){
		$class = str_replace($ctrl_suffix, '', $class);
		if (!import("ctrl.{$dir}.{$class}")){
			if (!import("ctrl.{$class}"))
				show_404("Not found controller({$class}) file.");
		}
	}elseif (FALSE !== strpos($class, $model_suffix)){
		$class = str_replace($model_suffix, '', $class);
		if (!import("model.{$class}")){
			show_404("Not found model({$class}) file.");
		}
	}
}
function microtime_float() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
function gc($name=NULL, $dvalue=NULL, $config=array()){
	$set = TRUE===$config;
	if (!is_array($config) OR !$config) global $config;
	if (is_null($name)) return $config;
	if (FALSE!==strpos($name, '.')) list($name, $key) = explode('.', $name);
	if (isset($config[$name])){
		if ($set){
			$config[$name][$key] = $dvalue;
		}else{
			if ($key){
				if (isset($config[$name][$key])) return $config[$name][$key];
				return $dvalue;
			}
			return $config[$name];
		}
	}elseif ($set){
		$config[$name] = $key ? array($key=>$dvalue) : $dvalue;
	}
	return $dvalue;
}
function lang($key=NULL, $data=array()){
	global $lang;
	if (!$key) return $lang;
	list($key, $sub) = explode('.', "{$key}.");
	$language = gc('site.language');
	if ('common'==$key){
		if (!isset($_ENV['import']['lang.common']) AND is_file($file = DATA_PATH. "lang/{$language}.php")){
			$lang = array_merge($lang, require $file);
			$_ENV['import']['lang.common'] = $file;
			unset($file);
		}
		return $lang;
	}
	if (!isset($_ENV['import'][$language.'.'. $key]) AND is_file($file = DATA_PATH. "lang/{$language}_{$key}.php")){
		$_ENV['import'][$language.'.'. $key] = $file;
		$lang = array_merge_recursive($lang, include $file);
		return $lang;
	}
	if(!isset($lang[$key])) return NULL;
	$a = $lang[$key];
	if(!is_array($a)){
		return preg_replace('/\%(.[^\}\]\%]*?)\%/ies', '\$data[\'$1\']', $a);
	}else{
		if ($sub){
			if (isset($a[$sub])) return $a[$sub];
			return NULL;
		}
		if (!$data) return $a;
	}
	foreach ($a as $key=>$one){
		if(isset($data[$key])){
			$a[$key] = preg_replace('/\%(.[^\}\]\%]*?)\%/ies', '\$data[$key][\'$1\']', $one);
		}
	}
	return $a;
}
/*
Example:
import('user'), import('util.cookie'), import('libs.upload')
*/
$_ENV['import'] = array();
function import($name=NULL, $re=FALSE, $path=''){
	$name = str_replace('..', '.', $name);
	if (!is_bool($re)){
		$path = $re;
		$re = FALSE;
	}
	if ('config'==$name){
		global $config;
		if (FALSE !== ($config = cache::fq('config'))) return TRUE;
		require SYS_PATH. 'config.php';
		$_ENV['import']['system.config'] = SYS_PATH. 'config.php';
		if (is_file($config_file = DATA_PATH. 'config'. DS. 'default.php')){
			require $config_file;
			$_ENV['import']['app.config'] = $config_file;
		}
		if (is_file($config_file = THIS_PATH. 'config.php')){
			require $config_file;
			$_ENV['import']['root.config'] = $config_file;
		}
		cache::fq('config', $config);
		return TRUE;
	}
	if (is_null($name)) return $_ENV['import'];
	if ('util'==$name) $name = "util.{$name}";
	if (FALSE===strpos($name, '.')) $name = "model.{$name}";
	$cache_key = "{$name}";
	if (isset($_ENV['import'][$cache_key])){
		return TRUE;
	}
	$rule = array(
		'libs' => array(
			'path' => array(CORE_PATH. 'libs'.DS, SYS_PATH. 'libs'.DS),
		),
		'util' => array(
			'path' => array(CORE_PATH. 'include'.DS, SYS_PATH. 'util'.DS),
		),
		'com' => array(
			'path' => CORE_PATH. 'components'. DS,
		),
		'helper' => array(
			'path' => array(CORE_PATH. 'include'.DS, SYS_PATH. 'helper'.DS),
		),
		'plugin' => array(
			'path' => array(CORE_PATH. 'plugins'.DS, SYS_PATH. 'plugins'.DS),
		),
		'core' => array(
			'path' => array(CORE_PATH),
		),
		'include' => array(
			'path' => array(CORE_PATH. 'include'.DS),
		),
		'sys' => array(
			'path' => array(SYS_PATH),
		),
		'data' => array(
			'path' => array(DATA_PATH),
		),
		'ctrl' => array(
			'path' => array(CORE_PATH. 'controllers'. DS),
			'ext' => '_ctrl',
		),
		'this' => array(
			'path' => array(THIS_PATH),
		),
		'widget' => array(
			'path' => array(CORE_PATH. 'widget'. DS, SYS_PATH. 'widget'. DS),
		),
		'config' => array(
			'path' => array(DATA_PATH. 'config'. DS),
		),
		'model' => array(
			'path' => array(CORE_PATH. 'models'. DS),
			'ext' => '_model',
		)
	);
	if (defined('GROUP_PATH')){
		array_unshift($rule['ctrl']['path'], GROUP_PATH. 'controllers'. DS);
		array_unshift($rule['model']['path'], GROUP_PATH. 'models'. DS);
		array_unshift($rule['config']['path'], GROUP_PATH. 'common'. DS);
		array_unshift($rule['data']['path'], GROUP_PATH. 'common'. DS);
	}
	$index = strpos($name, '.');
	$key = substr($name, 0, $index);
	$name = str_replace('.', DS, substr($name, $index+1));
	unset($index);
	if (isset($rule[$key])){
		$dir = $rule[$key]['path'];
		if ('config'===$key) global $config;
	}/**/
	if ($path) array_unshift($dir, $path);
	if (!is_array($dir)) $dir = array($dir);
	$res = FALSE;
	foreach ($dir as $path){
		$ext = isset($rule[$key]['ext']) ? $rule[$key]['ext'] : '';
		$file = $name. $ext.'.php';
		if (!is_file($in = $path. $key.'_'.$file)){
			if (!is_file($in = $path. $file)){
				if (!is_file($in = "{$path}{$name}.php")) continue;
			}
		}
		$_ENV['import'][$cache_key] = realpath($in);
		if (TRUE === $re) return $_ENV['import'][$cache_key];
		$res = require $in;
		break;
	}
	return $res;
}
function to_guid_string($mix) {
    if(is_object($mix) && function_exists('spl_object_hash')) {
        return spl_object_hash($mix);
    }elseif(is_resource($mix)) {
        $mix = get_resource_type($mix).strval($mix);
    }else {
        $mix = serialize($mix);
    }
    return md5($mix);
}
function &getInstance($name, $method='', $args=array()){
    static $_instance = array();
    $identify = empty($args) ? $name.$method : $name.$method.to_guid_string($args);
    if (!isset($_instance[$identify])) {
        if(class_exists($name, FALSE)){
            $o = new $name();
			//log_message('Load class "'.$name.'".',2);
            if($method && method_exists($o,$method)) {
                if(!empty($args)){
                    $_instance[$identify] = call_user_func_array(array(&$o, $method), $args);
                }else {
                    $_instance[$identify] = $o->$method();
                }
            }
            else
                $_instance[$identify] = $o;
        }
        else{
            logs($name. ' Class not exists.',__FILE__,__LINE__);
			show_error("Class ({$name}) not exists");
		}
    }
    return $_instance[$identify];
}
function str2array($input,$first='',$suffix='/'){
	$args=array();
	if (empty($input)) return $args;
	if ('/'===$first){
		$suffix = $first;
		$first = '';
	}
	if (!$suffix) $suffix = '/';
	if (is_scalar($input)) $input = explode($suffix,$input);
	$input = array_values($input);
	if (!empty($first)) $args[$first] = array_shift($input);
	$count = count($input);
	for ($i=0; $i<$count;$i+=2){
		$args[$input[$i]] = isset($input[$i+1]) ? $input[$i+1] : NULL;
	}
	return $args;
}
function array2str(&$array,$suffix='/',$filter=NULL,$urlencode=FALSE){
	if (!is_array($array)) return $array;
	$rs = '';
	foreach ($array as $key=>$value){
		if (is_int($key) OR FALSE!==strpos($value,$suffix)) continue;
		unset($array[$key]);
		if ($urlencode) $value = urlencode($value);
		$value = trim($value);
		if (!is_null($filter) AND ''===$value) continue;
		$rs .= trim($key).$suffix.$value.$suffix;
	}
	return trim($rs, $suffix);
}
function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
	$ckey_length = 4;
	$key = md5($key ? $key : gc('base.encryption_key'));
	$keya = md5(substr($key, 0, 16));
	$keyb = md5(substr($key, 16, 16));
	$keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
	$cryptkey = $keya.md5($keya.$keyc);
	$key_length = strlen($cryptkey);
	$string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
	$string_length = strlen($string);
	$result = '';
	$box = range(0, 255);
	$rndkey = array();
	for($i = 0; $i <= 255; $i++) {
		$rndkey[$i] = ord($cryptkey[$i % $key_length]);
	}

	for($j = $i = 0; $i < 256; $i++) {
		$j = ($j + $box[$i] + $rndkey[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}

	for($a = $j = $i = 0; $i < $string_length; $i++) {
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
	}

	if($operation == 'DECODE') {
		if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
			return substr($result, 26);
		} else {
			return '';
		}
	} else {
		return $keyc.str_replace('=', '', base64_encode($result));
	}
}
function str_to_array(&$input,$first='',$suffix='/'){
	return str2array($input,$first,$suffix);
}
function array_to_str(&$input,$suffix='',$filter=NULL){
	return array2str($input,$suffix,$filter);
}
function set_class_property(&$instance, $property=array()){
	Base::getInstance()->load->_assign_params($instance, $property);
	return $instance;
}

function logs($str,$file='',$line=''){
	$str = date('Y-m-d H:i:s', NOW)."\t{$str}";
	io::append(LOG_PATH. date('Y-m-d', NOW).'.log', $str);
}
function show_error($message, $status_code = 500, $heading = 'An Error Was Encountered'){
	getInstance('QcExceptions')->show_error($heading, $message, $status_code);
	exit;
}
function show_404($page = '', $log_error = TRUE){
	getInstance('QcExceptions')->show_404($page, $log_error);
	exit;
}
function dump($var, $echo=true,$label=null, $strict=true){
	return response::dump($var, $echo, $label, $strict);
}
function show_message($str){
	response::cprint($str);
}
function url($url='', $data=''){
	return Dispatch::url($url, $data);
}
function redirect($url, $build=TRUE){
	if ($build) $url = url($url);
	if ($_ENV['ajaxreq']){
		if ('script' === $_ENV['datatype']){
			exit('window.location.href="'. $url. '"');
		}else{
			return response::cprint(1, NULL, array('url'=>$url));
		}
	}
	header('Location:'. $url);
	exit;
}
