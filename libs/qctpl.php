<?php if ( ! defined('ROOT')) exit('No direct script access allowed');
/*
* create by EditPlus v3.01
* author by Sam <wuyou192@163.com>.
*/
class Lib_qctpl {
	public $cachepath;
	public $viewpath;
	public $theme = '';
	public $extension = '.html';
	private $dir = '';

	public function factory(){
		if (!$this->cachepath) $this->cachepath = CACHE_PATH. 'views'.DS;
		if (!is_null($theme = cookie::get('theme'))) $this->theme = $theme;
		if (!$this->viewpath) $this->viewpath = CORE_PATH. 'views'.DS;
		$this->dir = defined('GROUP_PATH') ? gc('env.group') : gc('env.directory');
		if ($this->dir) $this->cachepath .= $this->dir. DS;
		return $this;
	}
	public function template($name, $cachename=''){
		$file = self::getfile($name);
		if (!$file) return '';
		$name = str_replace('public/', '', $name);
		if (!$cachename) $cachename = $this->cachepath. ltrim($this->theme.'_'.$name, '_');
		$cache = $cachename.'.php';
		if (!is_file($cache) OR filemtime($file)>filemtime($cache)){
			io::qw($cache, QCtplTags::parse(file_get_contents($file)));
		}
		return $cache;
	}
	public function getfile($file){
        $exfile = $file = $file. $this->extension;
		if (is_file($file)) return $file;
		$rootview = $this->viewpath;
		if (0===strpos($file, 'public/')){
			if (is_file($rootview. $file)) return $rootview. $file;
			$file = str_replace('public/', '', $file);
		}
		$groupview = $itemview = (defined('ITEM_VIEW') ? ITEM_VIEW : $rootview);
		$fullview = rtrim($itemview. $this->theme.DS, DS). DS;
		if ($this->dir){
			if (defined('GROUP_PATH')) $groupview = GROUP_PATH. 'views'. DS;
			$rootview .= $this->dir. DS;
			$itemview .= $this->dir. DS;
			$fullview .= $this->dir. DS;
		}
		$ctrl = gc('env.controller');
		foreach (array_unique(array(
			$groupview.$exfile,
			$groupview.$file,
			$fullview.$ctrl.DS.$exfile,
			$fullview.$exfile,
			$fullview.$ctrl.DS.$file,
			$fullview.$ctrl.'_'.$file,
			$fullview.$file,
			$itemview.$exfile,
			$itemview.$ctrl.DS.$file,
			$itemview.$ctrl.'_'.$file,
			$itemview.$file,
			$rootview.$exfile,
			$rootview.$file,
			dirname($itemview).DS.$exfile,
			dirname($itemview).DS.$file,
			$rootview. 'public'.DS.$file,
			dirname($rootview).DS.'public'.DS.$file,
			)) as $tplfile){//echo $tplfile.'<br/>';
			if (is_file($tplfile)) break;
		}
		if (!is_file($tplfile)) return FALSE;
		return $tplfile;
    }
	public function html($name,$tplpath=''){
		return $this->view($name,$tplpath,'',TRUE);
	}
	public function cache_file($name, $cachepath=''){
		if (is_numeric($cachepath)){
			$cachepath = '';
		}
		if (!$cachepath) $cachepath = $this->cachepath;
		if (FALSE===($tplfile=$this->getfile($name))){
			return FALSE;
		}
		if (!gc('env.static_path')){
			gc('env.static_path', gc('env.webroot').'static/', TRUE);
		}
		if (!gc('env.theme_path')){
			$path = $this->dir ? $this->dir : "themes/{$this->theme}";
			gc('env.theme_path', gc('env.static_path'). "{$path}/", TRUE);
		}
		$cache = $cachepath. ltrim($this->theme.'_'.basename($name), '_').'.php';
		if (!is_file($cache) OR filemtime($tplfile)>filemtime($cache)){
			$content = QCtplTags::parse(file_get_contents($tplfile));
			io::qw($cache, $content);
		}
		return $cache;
	}
    public function view($name, $cachepath='', $return=0){
		if (is_array($name)){
			foreach ($name as $tplfile){
				if (FALSE!==($cache=$this->view($tplfile, '', 2))) break;
			}
			if (!$cache) show_error("Not found template file \"{$tplfile}\".");
			return $cache;
		}
		$file = $this->cache_file($name, $cachepath);
		if (2===$return) return $file;
		if (!$file) show_error("Not found template file \"{$tplfile}[{$name}]\".");
		!defined('IN_TEMPLATE') AND define('IN_TEMPLATE', TRUE);
		if (class_exists('Debug')) Debug::setbm('parse_template');
		return $file;
    }
}

class QCtplTags {
    static public function parse($cont){
        if (empty($cont)) return '';
        $var_regexp = "((\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(\[[a-zA-Z0-9_\-\.\"\'\[\]\$\x7f-\xff]+\])*)";
		$const_regexp = "([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)";
		$cont = preg_replace("/\\\$\{$const_regexp\}/s", '###\\1***', $cont);
		$cont = str_replace(array('{{', '}}'), array('@@@', '%%%'), $cont);
        $cont = preg_replace("/(src|href|action)=\"(.[^\"]*)\"/ise", "self::parse_path('\\1', '\\2')", $cont);
        $cont = preg_replace("/(background)=\"(.[^\{\"\<]*)\"/ise", "self::parse_path('background', '\\2')", $cont);
		$cont = preg_replace('/<form[^>]+?method=\"post\"(.*?)>/is', '\\0<input type="hidden" name="token" value="{$config[env][token]}" />', $cont);
        $cont = preg_replace("/\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $cont);
		$cont = preg_replace("/[\n\r\t]*\{(".$var_regexp."\s*\=\s*)*include\s+\'(.+?)\'\}[\n\r\t]*/ies", "self::parse_include('\\5', '\\3')", $cont);
        $cont = str_replace("{LF}", "<?=\"\\n\"?>", $cont);
		$cont = preg_replace("/\{(\\\$[a-zA-Z0-9_\[\]\'\"\$\.\x7f-\xff]+)\}/s", "<?=\\1;?>", $cont);
		$cont = preg_replace("/$var_regexp/es", "self::parse_quote('\\1')", $cont);
		$cont = preg_replace("/\<\?\=\<\?\=$var_regexp;\?\>;\?\>/es", "self::parse_quote('\\1')", $cont);
		$cont = preg_replace("/([\n\r\t]*)\{elseif\s+(.+?)\}([\n\r\t]*)/ies", "self::stripv_tags('\\1<?php elseif(\\3): ?>\\4','')", $cont);
		$cont = preg_replace("/([\n\r\t]*)\{else\}([\n\r\t]*)/is", "\\1<?php else: ?>\\3", $cont);
        /* Loop Parse */
        for($i = 0; $i < 5; $i++) {
			$cont = preg_replace("/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\}[\n\r]*(.+?)[\n\r]*\{\/loop\}[\n\r\t]*/ies",
					"self::stripv_tags('<?php \\2_index=0; if(isset(\\1) && is_array(\\1)): foreach(\\1 as \\2): \\2_index++; ?>','\\3<?php endforeach; endif; ?>')", $cont);
			$cont = preg_replace("/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\s+(\S+)\}[\n\r\t]*(.+?)[\n\r\t]*\{\/loop\}[\n\r\t]*/ies",
					"self::stripv_tags('<?php \\3_index=0; if(isset(\\1) && is_array(\\1)): foreach(\\1 as \\2 => \\3): \\3_index++; ?>','\\4<?php endforeach; endif; ?>')", $cont);
			$cont = preg_replace("/([\n\r\t]*)\{if\s+(.+?)\}([\n\r]*)(.+?)([\n\r]*)\{\/if\}([\n\r\t]*)/ies",
					"self::stripv_tags('\\1<?php if(\\2): ?>\\3','\\4\\5<?php endif; ?>\\6')", $cont);
		}
        /* Template Tag Parse */
		$cont = preg_replace("/[\n\r\t]*\{template\s+([a-z0-9_]+)\}[\n\r\t]*/ies", "self::parse_template('\\1')", $cont);
		$cont = preg_replace("/[\n\r\t]*\{template\s+(.+?)\}[\n\r\t]*/ies", "self::parse_template('\\1')", $cont);
		$cont = preg_replace("/[\n\r\t]*\{eval\s+(.+?)\}[\n\r\t]*/ies", "self::stripv_tags('<?php \\1 ?>','')", $cont);
		$cont = preg_replace("/[\n\r\t]*\{echo\s+(.+?)\}[\n\r\t]*/ies", "self::stripv_tags('<?=\\1; ?>','')", $cont);
        $cont = preg_replace("/\"(http)?[\w\.\/:]+\?[^\"]+?&[^\"]+?\"/e", "self::parse_transamp('\\0')", $cont);
        $cont = preg_replace("/\{$const_regexp\}/s", "<?php echo \\1;?>", $cont);
		$cont = str_replace('<?=', '<?php echo ', $cont);
        //$cont = preg_replace("/[\n\r\t]*\{list\s+(.[^\}]+?)\}[\n\r]*(.+?)[\n\r]*{\/list}/ies", "self::parse_block('list','\\1','\\2')", $cont);
		//$cont = preg_replace("/[\n\r\t]*\{data\s+(.[^\}]+?)\}/ies", "self::parse_block('data','\\1')", $cont);
		$cont = preg_replace("/\{widget:(.+?)(=(.+?))*\}/ies", "self::parse_widget('\\1', '\\3')", $cont);
        $cont = preg_replace("/\{(\w+)\s+(.[^=]+?)\}/ies", "self::parse_func('\\1','\\2', TRUE)", $cont);
		$cont = preg_replace("/[\n\r\t]*\{:(\w+)\s*(.[^\}]+?)\}/ies", "self::parse_func('\\1','\\2')", $cont);
		$cont = str_replace('&amp;', '&', $cont);
		$cont = preg_replace("/(\s*)\?\>[\n\r\s]*\<\?php(\s*)/s", " ", $cont);
		preg_match_all('/\{(list|data)\s+(.*?)\}/si', $cont, $matches);
		$cont = self::multi_tags($matches, $cont);//die($cont);die;
		$cont = str_replace(array('###','***','@@@','%%%'), array('${','}','{{','}}'), $cont);
        return $cont;
    }
	static private function multi_tags($m, $cont){
		if (!$m[0]) return $cont;
		foreach($m[0] as $k=>$v){
			$cont = str_ireplace($v,self::parse_block($m[1][$k], strtolower($m[2][$k])). ($m[1][$k]==='data' ? '?>' : ''), $cont);
			$cont = str_ireplace('{/'.$m[1][$k].'}', '<?php endforeach; endif; ?>', $cont);
		}
		return $cont;
	}
	static private function parse_block($type, $params){
		$params = self::explode_str(self::replace_vars($params));
		$name = 'blockv';
		if (isset($params['as'])){
			$as = $params['as'];
			$name = "block_{$as}";
			unset($params['as']);
		}else{
			$as = 'one';
			$name = ('data'===$type) ? $as : 'blockv';
		}
		if (isset($params['property'])){
			$property = $params['property'];
			unset($params['property']);
		}
		$code = '<?php $'.$name. '=';
		if (isset($params['sql'])){
			$code .= ' Db::getInstance()->'. ('list'===$type ? 'limit(1)' : ''). 'run("'. $params['sql']. '");';
		}elseif (count($params)===1){
			$code .= ' $this->'. str_replace('.', '->', key($params));
			if ($property) $code .= '->property(str2array("'. $property. '"))';
			$code .= '->block("'.$type.'", "'. current($params). '");';
		}
		if ('list'===$type){
			$code .= '$'.$as.'_index=0; if (is_array($'. $name. ')): foreach ($'.$name . ' as $'. $as. '): $'.$as.'_index++; ?>';
		}
		return $code;
	}
	static private function parse_func($fn, $args, $bool=FALSE){
		if (!function_exists($fn)) return '';
		$args = self::stripv_tags(self::parse_quote($args, 2), $bool);
		if (FALSE === strpos($args, '(')) $args = "(\"{$args}\")";
		return '<?php echo '.$fn. $args . '; ?>';
	}
    static private function parse_path($attr, $path){
		if ('{'== $path{0} OR '[' == $path{0}
			OR FALSE!==strpos($path,'\'')
			OR 'javascript'==substr($path,0,10)
			OR preg_match('/^(#|http\:\/\/|mailto\:|ftp\:\/\/|file\:|\{echo|\{url)/i', $path)
			) return $attr.'="'.$path.'"';
		$re = preg_match('/\.(gif|jpg|jpeg|png|bmp|css|swf|ico|js)$/i',$path);
		if ('/'===$path{0}){
			$path = '{echo '. ($re ? '$config[env][static_path]' : '$config[env][webroot]'). '}'. ltrim($path, '/');
		}elseif (is_numeric($path{0})){
			$path = preg_replace('/^(\d+)\//is', '{echo $config[site][static_path][\\1]}', $path);
		}elseif ($path == '' || $path == '\\' || '.'===$path{0}){
			$path = '{echo $config[env][webpath]}'. ltrim(ltrim($path, '.'), '/');
		}else{
			if ($re){
				$path = '{$config[env][theme_path]}'. ltrim($path, '/');
			}else{
                $path = '{echo url("'. $path. '")}';
            }
		}
		if (empty($attr)) return $path;
        if ('background' == $attr) return 'background="'. $path. '"';
		return $attr.'="'.$path.'"';
    }
	static private function parse_widget($name, $args){
		$name = '<?php $this->widget->'. str_replace('/', '->', $name). '('.(string)$args.')';
		//if ($args) $name .= "({$args})";
		return "{$name}; ?>";
	}
	static private function parse_quote($var, $all=1){
		$var = str_replace("\\\"", "\"", preg_replace("/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\]/s", "['\\1']", $var));
		if (2===$all) return $var;
		if (!$all) return '{'.$var.'}';
		return '<?='.$var.';?>';
	}
    static private function parse_template($name){
		if (!getInstance('Lib_qctpl')->getfile($name)) return '';
		if (FALSE===strpos($name, '$')) $name = "'{$name}'";
		return '{eval include $this->tpl->template('.$name.');}';
	}
    static private function stripv_tags($expr, $statement='') {
		$expr = str_replace("\\\"", "\"", preg_replace("/\<\?(php)?\s?(echo|\=)\s?(\\\$.+?);\?\>/s", TRUE===$statement?"{\\3}" : "\\3", $expr));
		if (TRUE===$statement) $statement = '';
		$statement = str_replace("\\\"", "\"", $statement);
		return $expr.$statement;
	}
	static private function parse_transamp($str) {
		$str = str_replace('&', '&amp;', $str);
		$str = str_replace('&amp;amp;', '&amp;', $str);
		$str = str_replace('\"', '"', $str);
		return $str;
	}
	static private function replace_vars($str){
		$str = preg_replace('/#(.[^#]*?)#/ies',"self::parse_quote('$\\1', 0)",$str);
		$str = str_replace("\\\"", "\"", preg_replace("/\<\?(php)?\s?(echo|\=)\s?(\\\$.+?);\?\>/s", '{\\3}', $str));
		return preg_replace('/%(.[^%]*?)%/is','".\\1."', $str);
	}
	static private function str_to_array($str){
		if (function_exists('str_to_array')) return str_to_array($str);
		$args=array();
		if (empty($input)) return $args;
		if (is_scalar($input)) $input = explode($suffix,$input);
		$input = array_values($input);
		if (!empty($first)) $args[$first] = array_shift($input);
		$count = count($input);
		for ($i=0; $i<$count;$i+=2){
			$args[$input[$i]] = isset($input[$i+1]) ? $input[$i+1] : NULL;
		}
		$input = $args;
		return $args;
	}
	static private function explode_str($str){
		$arr = explode(' ', trim($str));
		$res = array();
		foreach ($arr as $row){
			$row = explode('=', trim($row));
			if (!$row OR !$row[0] OR !$row[1]) continue;
			$res[$row[0]] = $row[1];
		}
		return $res;
	}
}
?>