<?php
/*
=====================================================
Access & Escape URL Segments, GET, POST, COOKIE, ENV and SERVER variables.
-----------------------------------------------------
v0.1: Escape values.
=====================================================
*/

if (!defined('BASEPATH')) {
	exit('No direct script access allowed.');
}

$plugin_info = array(
	'pi_name' => 'Escape',
	'pi_version' => '0.1',
	'pi_author' => 'EpicVoyage',
	'pi_author_url' => 'http://www.epicvoyage.org/ee/escape',
	'pi_description' => 'Access & Escape URL Segments, GET, POST, COOKIE, ENV and SERVER variables.',
	'pi_usage' => Escape::usage()
);

class Escape {
	var $return_data = '';

	function __construct() {
		$this->EE =& get_instance();

		# If no escaping method is used, just return the raw value (not recommended).
		$this->return_data = $this->_value();

		# Allow filtration accoring to a Regex pattern. Returns 1 or 0 if used.
		if ($regex = $this->EE->TMPL->fetch_param('regex')) {
			$this->return_data = preg_match($regex, $this->return_data) ? 1 : 0;
		}

		return;
	}

	# Escape the value for a database query. If this is for {exp:query}, use
	# {exp:query sql="..." parse="inward"}
	function sql() {
		return $this->return_data = $this->EE->db->escape($this->return_data);
	}

	# Escape for HTML output.
	function html() {
		# Detect the PHP version (htmlspecialchars() has features by PHP version).
		$version = explode('.', PHP_VERSION);
		$php_version = $version[0] * 10000 + $version[1] * 100 + $version[2];

		# Allow customization of flags parameter.
		$flags = $this->EE->TMPL->fetch_param('flags');
		$my_flags = 0;
		$avail = array(
			'ENT_COMPAT'	=> ENT_COMPAT,
			'ENT_QUOTES'	=> ENT_QUOTES,
			'ENT_NOQUOTES'	=> ENT_NOQUOTES,
			'ENT_IGNORE'	=> ($php_version >= 50300) ? constant('ENT_IGNORE') : 0,
			'ENT_SUBSTITUTE'=> ($php_version >= 50400) ? constant('ENT_SUBSTITUTE') : 0,
			'ENT_DISALLOWED'=> ($php_version >= 50400) ? constant('ENT_DISALLOWED') : 0,
			'ENT_HTML401'	=> ($php_version >= 50400) ? constant('ENT_HTML401') : 0,
			'ENT_XML1'	=> ($php_version >= 50400) ? constant('ENT_XML1') : 0,
			'ENT_XHTML'	=> ($php_version >= 50400) ? constant('ENT_XHTML') : 0,
			'ENT_HTML5'	=> ($php_version >= 50400) ? constant('ENT_HTML5') : 0
		);

		# Split $flags and add the values together.
		if (!empty($flags)) {
			foreach (explode('|', $flags) as $k) {
				$k = strtoupper(trim($k));
				if (isset($avail[$k])) {
					$my_flags |= $avail[$k];
				}
			}
		} else {
			# Otherwise, fall back to the default settings.
			$my_flags = ENT_COMPAT;
			if ($php_version >= 50400) {
				$my_flags |= constant('ENT_HTML401');
			}
		}

		$this->return_data = htmlspecialchars($this->return_data, $my_flags);

		return $this->return_data;
	}

	# Load a superglobal value, according to user-specified (tag) configuration.
	function _value() {
		# Load settings...
		$ret = $this->_fetch_param('default', '');
		$order = $this->EE->TMPL->fetch_param('order');
		$request = $this->_fetch_param('request');

		# Support for SolSpace's SuperSearch format...
		$get = &$_GET;
		if (empty($get)) {
			foreach ($this->EE->uri->segments as $v) {
				$segs = explode('&', $v);
				if ($segs[0] == 'search') {
					array_shift($segs);
					$get = array();
					foreach ($segs as $seg) {
						$k = explode('=', $seg);
						$get[$k[0]] = $k[1];
					}
				}
			}
		}

		# Pack the requested key(s) into an array for easier reference.
		$vars = array(
			'g' => $this->_fetch_param('get', $request),
			'p' => $this->_fetch_param('post', $request),
			'c' => $this->_fetch_param('cookie', $request),
			'e' => $this->_fetch_param('env'),
			's' => $this->_fetch_param('server'),
			't' => $this->_fetch_param('segment')
		);

		# Make sure any requested variables can be accessed.
		foreach ($vars as $k => $v) {
			# We may need to fall back on the PHP variable ordering, if none was provided...
			if ($v && (!empty($order) || $order = ini_get('variables_order')) && (strpos($order, $k) === false)) {
				$order .= $k;
			}
		}

		# If no ordering or variables were provided... assume we are a tag pair...
		if (!$order) {
			$ret = $this->EE->TMPL->tagdata;

		# Otherwise, try to identify the best variable to return.
		} else {
			# Loop through GET, POST, COOKIE, ENV, SERVER variables in the requested order.
			foreach (str_split($order) as $v) {
				$v = strtolower($v);

				# Try to load the requested variable type.
				if (($ret === '') && isset($vars[$v]) && $vars[$v]) {
					if ($v == 'g') {
						$ret = isset($_GET[$vars[$v]]) ? $this->_kill_gpc_magic($_GET[$vars[$v]]) : '';
					} elseif ($v == 'p') {
						$ret = isset($_POST[$vars[$v]]) ? $this->_kill_gpc_magic($_POST[$vars[$v]]) : '';
					} elseif ($v == 'c') {
						$ret = isset($_COOKIE[$vars[$v]]) ? $this->_kill_gpc_magic($_COOKIE[$vars[$v]]) : '';
					} elseif ($v == 'e') {
						$ret = isset($_ENV[$vars[$v]]) ? $_ENV[$vars[$v]] : '';
					} elseif ($v == 's') {
						$ret = isset($_SERVER[$vars[$v]]) ? $_SERVER[$vars[$v]] : '';
					} elseif ($v == 't') {
						$ret = $this->EE->uri->segment($vars[$v]);
					}

					if ($ret !== '') {
						break;
					}
				}
			}
		}

		return $ret;
	}

	# EE's function replaces "y" with "yes". What happens when we have a
	# GET variable named "y"?
	function _fetch_param($which, $default = FALSE) {
		return !isset($this->EE->TMPL->tagparams[$which]) ? $default : $this->EE->TMPL->tagparams[$which];
	}

	function _kill_gpc_magic($var) {
		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
			$var = stripslashes($var);
		}

		return $var;
	}

	function usage() {
		return <<<EOF
Access & Escape URL Segments, GET, POST, COOKIE, ENV and SERVER variables.

Examples:
{exp:escape get="hello"} - "'world>"
{exp:escape:sql get="hello"} - "\'world>"
{exp:escape:html get="hello"} - "'world&gt;"
{exp:escape:html get="hello" post="hello" server="PHP_SELF" order="SPG"} = "/path/to/index.php"
{exp:escape:html get="hello" flags="ENT_NOQUOTES|ENT_HTML5"} - "'world&gt;"
{exp:escape:html}'world>{/exp:escape:html flags="ENT_QUOTES"} - "&aquot;world&gt;"
EOF;
	}

}
