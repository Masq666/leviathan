<?php
	/**
	* Plugin Handler
	* 
	* @package Leviathan
	* @subpackage plugins
	* @version 2.0.0
	* @copyright 2005-2013 Philipe Rubio / Koppin22 Media DA
	* @author Philipe Rubio
	*/
	
	/*
		This is a list of do_action tags/positions. (incomplete list)
		
		index.php
			core_start				-	First pos, before all output and data retrieval from db.
			core_end				-	Last pos, after all output
			
		WPL.php
			get_header				-	Triggered when get_header is called from a theme file.
			get_footer
			get_sidebar
			get_sidebar_end			-	Triggered after the sidebar is included.
			
			wp_meta
			wp_head
			wp_footer
			
		ADMIN:
		sidebar.php
			adm_sidebar_widgets		-	At the bottom of the sidebar after Navigation.
	*/
	
	// Do NOT allow direct access to this file.
	defined('_KO22_VALID') or die();
	
	// Hmm, should clean up all these GLOBAL variables.
	$plugins = array();


	if(file_exists(P_CACHE.DS.(int)defined('IS_ADMIN').'plugins.php')){
		include(P_CACHE.DS.(int)defined('IS_ADMIN').'plugins.php');
	}else{
		// Fetch active plugins from db.
		$db->setQuery('SELECT name, pfile, params FROM #__plugins WHERE (is_adm='.(int)defined('IS_ADMIN').' AND published=1)');
		$__plg = $db->loadAssocList();
	}
	
	// Include the Plugins located in the plugins directory.
	foreach($__plg as $plugin){
		include_once P_PLUGINS.DS.$plugin['pfile'];
	}

	// $__plg will not be used beyond this point, so let's get rid of it.
	unset($__plg);

	/**
	 * Calls functions added to $plugins by add_action.
	 * @param string $tag Name/Tag/Position to run.
	 * @param mixed $arg Arguments to send to the called function.
	 */
	function do_action($tag, $arg=null){
		global $plugins;
		
		// Do not run the loop unless there are functions to run.
		if(isset($plugins[$tag]) and ($tag != 'module')){
			ksort($plugins[$tag]);
			foreach($plugins[$tag] as $t){
				foreach($t as $plugin){
					if(is_array($plugin) && class_exists($plugin[0],false)){
						$c = new $plugin[0](isset($plugin[2]) ? $plugin[2] : null);
						$c->$plugin[1]($arg);
						unset($c);
					}else{				
						if(function_exists($plugin)){
							// Call function added by plugin.
							$plugin($arg);
						}
					}
				}
			}
		}elseif(($tag === 'module') and isset($plugins['module'][$arg])){
			$m = $plugins['module'][$arg][0];
			
			$c = new $m[0](isset($m[2]) ? $m[2] : null);
			return $c->$m[1]();
		}
	}

	/**
	 * Adds a function to the $plugins array to be called later by do_action.
	 */
	function add_action($tag, $function, $pri=0){	
		global $plugins;
		$plugins[$tag][$pri][] = $function;
	}
?>