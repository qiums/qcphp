<?php if ( ! defined('ROOT')) exit('No direct script access allowed');
!defined('DS') AND define ('DS', DIRECTORY_SEPARATOR);

!defined('SELF') AND define('SELF', pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_BASENAME));
!defined('SYS_PATH') AND define('SYS_PATH', dirname(__FILE__). DS);
!defined('ROOT') AND define('ROOT', dirname(SYS_PATH).DS);
!defined('THIS_PATH') AND define('THIS_PATH', ROOT);
!defined('CORE_PATH') AND define('CORE_PATH', ROOT.'core'.DS);
!defined('APP_PATH') AND define('APP_PATH', CORE_PATH.'app'.DS);
!defined('DATA_PATH') AND define ('DATA_PATH', THIS_PATH. 'data'. DS);
!defined('CACHE_PATH') AND define ('CACHE_PATH', DATA_PATH.'cache'. DS);
!defined('TMP_PATH') AND define('TMP_PATH', DATA_PATH.'tmp'. DS);
!defined('LOG_PATH') AND define('LOG_PATH', DATA_PATH.'logs'. DS);
!defined('UPLOAD_PATH') AND define('UPLOAD_PATH', ROOT. 'uploads'. DS);
//!defined('VIEWPATH') AND define('VIEWPATH', ROOT.'views'.DS);
define('NOW', time());
unset($_ENV);
$GLOBALS['config'] = array();
$GLOBALS['routes'] = array();
$GLOBALS['lang'] = array();
$GLOBALS['G'] = array();
$_ENV = array();

require SYS_PATH. 'helper'.DS.'common.php';
import('util');
import('config');
if ('debug'==$config['base']['run_mode']&&function_exists('ini_set')) @ini_set('display_errors','on');
error_reporting('debug'==$config['base']['run_mode'] ? E_ALL ^ E_NOTICE ^ E_STRICT : 0);
// Headers
header("Content-Type:text/html; charset=". gc('base.charset'));
header('P3P: CP="CAO PSA OUR"');
$_ENV['ajaxreq'] = request::req('ajax', FALSE, 'XMLHttpRequest'===request::server('HTTP_X_REQUESTED_WITH'));
if ($_ENV['ajaxreq']){
	$_ENV['datatype'] = is_bool($_ENV['ajaxreq']) ? 'json' : $_ENV['ajaxreq'];//request::req('ajaxget', 'json');
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Content-Type:text/{$_ENV['datatype']}; charset=utf-8");
}
// PHP version
$config['env']['phpver'] = phpversion();
if (!$config['env']['domain']){
	$config['env']['domain'] = rtrim("http://{$_SERVER['HTTP_HOST']}".
		(FALSE===strpos($_SERVER['HTTP_HOST'],':') AND 80!=$_SERVER['SERVER_PORT'] ? ":{$_SERVER['SERVER_PORT']}" : ''),'/');
}
// Web sub-directory, if have
$config['env']['webpath'] = rtrim(str_replace(DS, '/', pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME)), '/').'/';
// Web root-directory
if (ROOT === THIS_PATH){
	$config['env']['webroot'] = $config['env']['webpath'];
}else{
	$dir = str_replace('\\', '/', str_replace(ROOT, '', THIS_PATH));
	$config['env']['webroot'] = str_replace($dir, '', $config['env']['webpath']);
	unset($dir);
}
// Full web url
$config['env']['baseurl'] = $config['env']['domain']. $config['env']['webpath'];
if (!$config['base']['charset']) $config['base']['charset'] = 'utf-8';
// Cookie path
if (!$config['cookie']['path']) $config['cookie']['path'] = $config['env']['webroot'];

Debug::start();
Boot::run();
Debug::end();
unset($_ENV);

/****Boot****/
class Boot{
	static private function start(){
		Debug::setbm('system_init');
		import('util.dispatch');
		import('sys.model');
		//$_ENV['hooks'] = new Hooks;
		//$_ENV['hooks']->callhook('pre_system');
	}
	static public function run(){
		self::start();
		self::dispatch();
	}
	static private function dispatch(){
		Dispatch::run();//dump($_GET);die;
		$dir = gc('env.directory');
		$class = gc('env.controller');
		$ac = gc('env.action');
		$file = ltrim("{$dir}.{$class}", '.');
		$_class = ltrim(trim($dir,'/'). "_{$class}", '_');
		if ($_ENV['iscp']){
			$_class = "com_{$class}";
			if (!import("com.{$class}.controller")) show_404("Not load components controller({$class}).");
		}elseif (!import("ctrl.{$file}")){
			show_404("Not found controller({$file}) file.");
		}
		$suffix = gc('base.controller_suffix');
		if (!class_exists($_class.$suffix)){
			$_class = $class;
			if (!class_exists($_class.$suffix)){
				show_error('Not load controller('.$_class.')');
			}
		}
		$class = $_class. $suffix;
		$ctrl = new $class();
		Debug::setbm('load_controller');
		//$_ENV['hooks']->callhook('load_controller');
		self::autoload($ctrl);
		if (method_exists($ctrl, '__call') OR method_exists($ctrl, $ac)) $ctrl->$ac();
		unset($_class, $suffix, $class, $ctrl, $dir, $file, $ac);
	}
	static private function autoload($instance){
		if (!is_object($instance)) return ;
		$methods = get_class_methods($instance);
		foreach ($methods as $method){
			if ('_init_'!=substr($method, 0, 6)) continue;
			$instance->{$method}();
		}
	}
}
/***************
class Base
***************/
class EmptyClass{}
class Base {
	private static $instance;
	public $qdata = array();
	public $cp;
	public $widget;
	public $load;

	public function __construct(){
		self::$instance = &$this;
		$this->load = new Loader();
		$this->cp = new components();
		$this->widget = new widget();
	}
	public function Base(){$this->__construct();}
	public function __get($name){
		$me = self::$instance;
		$name = str_replace('_', '/', $name);
		if (!($o=$me->load->model($name))){
			if (!($o=$me->load->libs($name))){
				show_error("Cannot load model or libs ({$name})");
				return $me;
			}
		}
		return $o;
	}
	public function __set($name, $value){
		$me = self::$instance;
		if (is_object($value)){
			$me->$name = $value;
		}else{
			$tname = str_replace('_', '/', $name);
			if (!($o=$me->load->model($tname))){
				if (!($o=$me->load->libs($tname))){
					$me->$name = $value;
					return ;
				}
			}
		}
		if (is_object($o)){
			$o->config = $value;
			return ;
		}
	}
	public function __call($name, $args){show_error('Not find action ('.$name.')');}
	public static function &getInstance(){
		if (!self::$instance) self::$instance = new Base;
		return self::$instance;
	}
}
class controller extends Base{
	public $vars = array();
	public $post = array();
	protected $html_content;

	public function __construct(){
		parent::__construct();
		$this->load->template(gc('tpl.engine'), 'tpl');
		$this->post = (array)request::post();
		$this->qdata = array_merge(request::get(), $this->post, $this->qdata);
	}
	public function __destruct() {
		unset($this->qdata);
		unset($this->vars);
	}
	public function controller(){$this->__construct();}
	public function gp($key, $default=NULL){
		$this->load->helper('extend');
		if (FALSE!==strpos($key, ',')){
			return AIKE(explode(',', $key), $this->qdata);
		}
		if (is_array($key)) return AIKE($key, $this->qdata);
		return isset($this->qdata[$key]) ? $this->qdata[$key] : $default;
	}
	protected function assign($name, $value=NULL){
		if (is_array($name)){
			$this->vars = array_merge($this->vars, $name);
		}else{
			$this->vars[$name] = $value;
		}
		return $this;
	}
	protected function output($code=NULL, $message='', $data=array()){
		if (is_string($code)){
			$message = $code;
			$code = NULL;
		}
		if ($message){
			$msg = lang("tips.{$message}");
			if (!$msg) $msg = lang($message);
			if (!$msg) $msg = $message;
		}
		unset($message);
		return response::cprint($code, $msg, $data);
	}
	protected function view($file, $return=FALSE){
		//$_ENV['hooks']->callhook('pre_display');
		Debug::setbm('pre_display');
		global $config, $lang;
		if ($_ENV['ajaxreq'] && 'html' !== $_ENV['datatype']){
			$this->output();
		}else{
			$this->load->helper('extend');
			$config['env']['token'] = formhash();
			$G = $this->qdata;
			unset($_POST);
			extract($this->vars);
			if ($_ENV['iscp']) $file = "cp/". gc('env.controller')."/{$file}";
			if ($return){
				ob_start();
				require $this->tpl->view($file);//
				$this->html_content = ob_get_contents();
				ob_end_clean();
			}else{
				$gzip = gc('base.gzip_output');
				if ($gzip AND function_exists('ob_gzhandler')) {
					ob_end_clean();
					ob_start("ob_gzhandler");
				}
				require $this->tpl->view($file);
			}
		}
		unset($this->qdata, $this->vars);
		//$_ENV['hooks']->callhook('post_system');
	}
}
/***************
class Loader
***************/
$_ENV['models'] = $_ENV['libs'] = $_ENV['helpers'] = $_ENV['components'] = $_ENV['widget'] = array();
class Loader {
    protected $_not_exists_model = TRUE;

	public function __construct(){}
	public function model($class, $name='', $config=array()){
		if (is_string($class) && FALSE !== strpos($class, ',')) $class = explode(',', $class);
		if (is_array($class)){
			foreach ($class as $model){
				$this->model(trim($model), $name, $config);
			}
			return ;
		}
		if (!$class) return FALSE;
		if (FALSE === strpos($class, '/')){
			$class = $class;
			$path = '';
		}else{
			$path = trim(str_replace('/', '.', dirname($class)), '.'). '.';
			$class = basename($class);
		}
		if (is_array($name) AND !$config){
			$config = $name;
			$name = '';
		}
		if (!is_scalar($name) OR empty($name)) $name = $class;
		$cache_key = "{$path}{$name}";
		if (isset($_ENV['models'][$cache_key])){
			return Base::getInstance()->$name;
		}
        $this->_not_exists_model = TRUE;
		$_class = strtolower($class) .gc("base.model_suffix");
		if (!import("model.{$path}{$class}")){
			if (!import("model.{$class}.{$class}")){
				if (!$path) $path = $class;
				if (!import("core.group.{$path}.models.{$_class}")) return FALSE;
			}
		}
		$base = &Base::getInstance();
		if (class_exists($_class, FALSE)){
			$_ENV['models'][$cache_key] = $name;
			$base->$name = new $_class();
			//$this->_libs_to_models();
			return $base->$name;
		}
		return FALSE;
	}
	public function template($class, $name=''){
		if (!import("util.$class")){
			if (!import("libs.$class")) system_error("The template engine [$class] not exists.");
		}
		empty($name) && $name = $class;
		$class = gc('base.libclass_prefix'). $class;
		$base = Base::getInstance();
		$base->$name = getInstance($class);
		$this->_assign_params($base->$name, gc($name));
		if (method_exists($base->$name, 'factory')) $base->$name->factory(gc($name));
		return $base->$name;
	}
	public function database(){
		if ($_ENV['db']) return TRUE;
		if (!import("util.db")) show_error("Cannot load database class.");
		return import('config.database');
	}
	public function libs($class, $name=''){
		if (is_string($class) && FALSE !== strpos($class, ',')) $class = explode(',', $class);
		if (is_array($class)){
			foreach ($class as $lib) $this->libs($lib);
			return TRUE;
		}
		if (!$class) return FALSE;
		empty($name) && $name = $class;
		$cache_key = "libs_{$name}";
		if (isset($_ENV['libs'][$cache_key])) return Base::getInstance()->$name;
		if (!import("libs.{$class}")) return ;
		$class = gc('base.libclass_prefix'). $class;
		$subclass = gc('base.subclass_prefix'). $class;
		$base = Base::getInstance();
		if (class_exists($subclass)){
			$base->$name = getInstance($subclass);
		}elseif (class_exists($class)){
			$base->$name = getInstance($class);
		}else{
			return FALSE;
		}
		$this->_assign_params($base->$name, gc($name));
		if (method_exists($base->$name, 'factory')) $base->$name->factory(gc($name));
		$_ENV['libs'][$cache_key] = $name;
		return $base->$name;
	}
	public function helper($file){
		if ('common'===$file) return TRUE;
		if (is_string($file) && FALSE !== strpos($file, ',')) $class = explode(',', $file);
		if (is_array($file)){
			foreach ($file as $val){
				$this->helper($val);
			}
			return TRUE;
		}
		if (isset($_ENV['helpers'][$file])) return TRUE;
		if (!import("helper.{$file}")) return FALSE;
		$_ENV['helpers'][$file] = "helper.{$file}";
		return TRUE;
	}
	public function _libs_to_models(){
		if (count($_ENV['models']) == 0) return;
		$base = Base::getInstance();
		foreach ($_ENV['models'] as $model){
			$base->$model->_assign_libs();
		}
	}
	public function _assign_params(&$instance, $params=array()){
		if (!is_object($instance) OR !$params) return FALSE;
		foreach ($params as $name=>$value){
			if (is_numeric($name)) continue;
			$instance->$name = $value;
		}
	}
}
//class EmptyClass{}
class components {
	public function __get($name){
		return $this->load($name);
	}
	public function __set($name, $value){
		if (is_object($value)){
			$this->$name = $value;
		}else{
			if (!($this->load($name))) return ;
		}
	}
	protected function load($class, $name=''){
		if (!$name) $name = $class;
		$cache_key = "com_{$name}";
		if (isset($_ENV['components'][$cache_key])) return $this->$name;
		$file = strtolower($class). gc("base.model_suffix");
		$_class = "com_{$file}";
		if (!import("com.{$class}.{$file}")){
			if (!import("com.{$class}.model")) return FALSE;
		}
		$this->$name = new $_class();
		if (is_file($file = import("com.{$class}.config", TRUE))){
			global $config;
			require $file;
			$this->$name->config = gc($name);
			unset($file);
		}
		$_ENV['components'][$cache_key] = $name;
		return $this->$name;
	}
}
class widget extends components {
	public function __construct() {
		import('config.widget');
	}
	protected function load($class, $name=''){
		if (!$name) $name = $class;
		$cache_key = "widget_{$name}";
		if (isset($_ENV['widget'][$cache_key])) return $this->$name;
		if (!import("widget.{$class}.widget")) return FALSE;
		$class = gc('base.widgetclass_prefix'). $class;
		$this->$name = new $class();
		$_ENV['widget'][$cache_key] = $name;
		return $this->$name;
	}
}
