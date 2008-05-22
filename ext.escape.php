<?php if (!defined('BASEPATH')) { exit('No direct script access allowed.'); }
/**
 * Access & Escape URL Segments, GET, POST, COOKIE, ENV and SERVER variables.
 *
 * @package		Escape
 * @category		Extension
 * @description		Access & Escape URL Segments, GET, POST, COOKIE, ENV and SERVER variables.
 * @copyright		Copyright (c) 2013 EpicVoyage
 * @link		https://www.epicvoyage.org/ee/escape/
 */

class Escape_ext {
	var $settings_exist = 'n';
	var $settings = array();
	var $return_data = '';

	var $docs_url = 'https://www.epicvoyage.org/ee/escape/';
	var $version = '2.0';

	function __construct() {
		$this->EE =& get_instance();
	}

	public function sessions_end() {
		if (!($order = strtolower(ini_get('variables_order')))) {
			# Create a default order, where none exists.
			$order = 'gpces';
		}

		# Loop through GET, POST, COOKIE, ENV, SERVER variables in the requested order.
		foreach (str_split($order) as $v) {
			if ($v == 'g') {
				$this->_load($_GET, 'get', true);
			} elseif ($v == 'p') {
				$this->_load($_POST, 'post', true);
			} elseif ($v == 'c') {
				$this->_load($_COOKIE, 'cookie', true);
			} elseif ($v == 'e') {
				$this->_load($_ENV, 'env', true);
			} elseif ($v == 's') {
				$this->_load($_SERVER, 'server', true);
			}
		}
	}

	function _load($arr, $name, $kill_gpc) {
		foreach ($arr as $k => $v) {
			if (!is_array($v)) {
				if ($kill_gpc) {
					$v = $this->_kill_gpc_magic($v);
				}

				# Mitigate hacking attempts...
				if (!isset($this->EE->config->_global_vars[$k])) {
					$this->EE->config->_global_vars[$k] = htmlspecialchars($v);
				}
				$this->EE->config->_global_vars[$name.':'.$k] = htmlspecialchars($v);
				$this->EE->config->_global_vars[$name.':'.$k.':raw'] = $this->EE->config->_global_vars[$k.':raw'] = $v;
				$this->EE->config->_global_vars[$name.':'.$k.':sql'] = $this->EE->config->_global_vars[$k.':sql'] = preg_replace("/^'(.*)'$/", '\1', $this->EE->db->escape($v));
			}
		}
	}

	function _kill_gpc_magic($var) {
		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
			$var = stripslashes($var);
		}

		return $var;
	}

	function activate_extension() {
		$data = array(
			'class' => __CLASS__,
			'settings' => serialize($this->settings),
			'priority' => 1,
			'version' => $this->version,
			'enabled' => 'y',

			'hook' => 'sessions_end',
			'method' => 'sessions_end'
		);

		$this->EE->db->insert('extensions', $data);
	}

	function update_extension($current = '') {
		$this->activate_extension();
	}

	function disable_extension() {
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');
	}
}
