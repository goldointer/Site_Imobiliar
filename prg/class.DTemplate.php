<?php
define('DT_VERSION', '1.3.0h');
// +----------------------------------------------------------------------+
// | DTemplate original version 1.1.3                                     |
// +----------------------------------------------------------------------+
// | Copyright (c) 2001-2004 Peter Mallett                                |
// +----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or        |
// | modify it under the terms of the GNU General Public License          |
// | as published by the Free Software Foundation; either version 2       |
// | of the License, or (at your option) any later version.               |
// +----------------------------------------------------------------------+
// | Authors:                                                             |
// |    Perl module CGI::FastTemplate - Jason Moore <jmoore@sober.com>    |
// |    PHP3 port by CDI <cdi@thewebmasters.net>                          |
// | DTemplate:                                                           |
// |    Peter Mallett <pmallett@desolatewaste.com>                        |
// +----------------------------------------------------------------------+

class DTemplate
{
// Settings vars
	var $DYN_START  = '<!-- BEGIN DYNAMIC BLOCK: %s -->';   //format of dynamic block delimiters in template files
	var $DYN_END    = '<!-- END DYNAMIC BLOCK: %s -->';
	//var $DYN_START  = '<DTDYN:%s>';
	//var $DYN_END    = '</DTDYN:%s>';

	var $COMMENT_START  = '<!-- ';                          //comment format for template output
	var $COMMENT_END    = ' -->';

	var $WIN32  = false;        //set to true if running on win32 server, used in determining directory path separator
	var $STRICT = false;        //when true unresolved fields will generate warnings in output
	var $HTMLENTITIES = 'ISO8859-1';   //when set converts all applicable characters to HTML entities

// Program vars
	var $FILELIST   = array();  //array of template file names;                 $FILELIST[$templatetag]
	var $TEMPLATE   = array();  //array of unparsed template file contents;     $TEMPLATE[$templatetag]
	var $OUTPUT     = array();  //array of parsed template output;              $OUTPUT[$templatetag]

	var $DYNINFO    = array();  //array of dynamic blocks' locations            $DYNINFO[$dynamictag] == $parenttag
	var $BLOCKINFO  = array();  //array of blocks that contain dynamic blocks;  $BLOCKINFO[$parenttag][0..n] == $dynamictag
	var $DYNAMIC    = array();  //array of unparsed dynamic block contents;     $DYNAMIC[$dynamictag]
	var $DYNOUTPUT  = array();  //array of parsed dynamic output;               $DYNOUTPUT[$dynamictag]

	var $PARSEVARS  = array();  //array of template field values;               $PARSEVARS[$fieldname]

	var $ROOT = '';             //path to template files

	var $fCopyrightOutput = 0;  //switch for copyright output


//-------------------------------------------------------------
	public function __construct($templatepath = '')
	{
		if (!empty($templatepath)) {
			$this->set_root($templatepath);
		}
	}
	
	public function DTemplate($templatepath)
	{
		self::__construct($templatepath);
	}


//-------------------------------------------------------------
	function set_root($root)
	{
		$trailer = substr($root, -1);

		if ($this->WIN32) {
			if ($trailer != "\\") {
				$root .= "\\";
			}
		} else {
			if ($trailer != '/') {
				$root .= '/';
			}
		}

		if (is_dir($root)) {
			$this->ROOT = $root;
		} else {
			$this->ROOT = '';
			$this->error("Specified ROOT dir [$root] is not a directory");
		}
	} // end set_root()


//-------------------------------------------------------------
//  Strict template checking, if true prints warnings about unresolved fields
// (fields that appear in the template, but are not assigned a value by your code)
	function strict($arg = true)
	{
		$this->STRICT = $arg;
	}


//-------------------------------------------------------------
//  Convert all applicable characters to HTML entities
	function htmlentities($encoding)
	{
		$this->HTMLENTITIES = $encoding;
	}


//-------------------------------------------------------------
// read template file into large string
	function get_template($ttag)
	{
		if (empty($this->ROOT)) {
			$this->error('Can not get_template. ROOT not valid.', 1);
		}

		$filename = $this->ROOT . $this->FILELIST[$ttag];

		$fp = fopen($filename, 'r');
		if ($fp == false) {
			$this->error("get_template() failure: [$filename] $php_errormsg", 1);
		}
		$this->TEMPLATE[$ttag] = fread($fp, filesize($filename));
		fclose($fp);
	}


//-------------------------------------------------------------
// check to see if template is loaded, if not, load it
	function check_template($ttag)
	{
		if (!isset($this->TEMPLATE[$ttag])) {
			$this->get_template($ttag);
		}
	}


//-------------------------------------------------------------
// setup relationship between $ttag and template file names
// filelist should be an array of file names referenced by $ttag's
	function define_templates($filelist)
	{
		if (!is_array($filelist)) {
			$this->error("define_templates() failure: \$filelist is not an array.", 1);
		}

		foreach ($filelist as $ttag => $filename) {
			$this->FILELIST[$ttag] = $filename;
		}
	}


//-------------------------------------------------------------
// Assign values for template fields.
// assign() has dual functionality:
//
// assign(array(KEY => 'value', ...))
//      where KEY matches the name of a template field and each pair in the array is assign
//
// assign(KEY, 'value')
//  for assigning a value to one template field
//
	function assign($field, $single_val = '')
	{
		if (is_array($field)) {
			foreach ($field as $key => $val) {
				if (!(empty($key))) {
					//  can not have empty keys
					$this->PARSEVARS[$key] = $val;
				}
			}
		} else {
			// since $field_array is NOT an array, assume it's a single key
			if (!empty($field)) {
				$this->PARSEVARS[$field] = $single_val;
			}
		}
	} // end assign()

	function assign_htmlentities($field_array, $single_val = '')
	{
		if (is_array($field_array)) {
			foreach ($field_array as $key => $val) {
				if (!(empty($key))) {
					//  can not have empty keys
					$this->PARSEVARS[$key] = empty($this->HTMLENTITIES) ? $val : htmlentities($val, ENT_COMPAT, $this->HTMLENTITIES);
				}
			}
		} else {
			// since $field_array is NOT an array, assume it's a single key
			if (!empty($field_array)) {
				$single_key = $field_array;     //just for code clarity
				$this->PARSEVARS[$single_key] = empty($this->HTMLENTITIES) ? $single_val : htmlentities($single_val, ENT_COMPAT, $this->HTMLENTITIES);
			}
		}
	} // end assign()



//-------------------------------------------------------------
//  Clears all variables set by assign()
	function clear_assign()
	{
		if (!empty($this->PARSEVARS)) {
			reset($this->PARSEVARS);
			while (list($key, $val) = each ($this->PARSEVARS))
			{
				unset($this->PARSEVARS[$key]);
			}
		}
	}


//-------------------------------------------------------------
// Clears template output created by parse_template()
	function clear_output($ttag)
	{
		if (empty($ttag)) {
			// Clears all
			if (!empty($this->OUTPUT)) {
				foreach ($this->OUTPUT as $key => $val) {
					unset($this->OUTPUT[$key]);
				}
			}
		}
		else
			// Clear one
			$this->OUTPUT[$ttag] = "";
	}


//-------------------------------------------------------------
// Clears dynamic output created by parse_dynamic
	function clear_dynamic_output($ttag)
	{
		if (empty($ttag)) {
			// Clears all
			if (!empty($this->DYNOUTPUT)) {
				foreach ($this->DYNOUTPUT as $ttag => $val) {
					unset($this->DYNOUTPUT[$ttag]);
				}
			}
		} else
			// Clear one
			$this->DYNOUTPUT[$ttag] = "";
	}


//-------------------------------------------------------------
//  Prints the warnings for unresolved fields
//  in template file. Used if STRICT is true
	function show_unknowns($txt)
	{
		preg_match_all('/\{[A-Z0-9_]+\}/', $txt, $matches);
		foreach ($matches[0] as $key => $val) {
			if (!(empty($val))) {
				$this->warning("no value found for variable: $val\n");
			}
		}
	} // end show_unknowns()


//-------------------------------------------------------------
//  This function is called by parse() and does the field to value conversion
	function parse_template($ttag, $desttag, $f_append)
	{
		if (!$desttag) {
			$desttag = $ttag;
		}
		$output = $this->TEMPLATE[$ttag];

		//fill in field values
		foreach ($this->PARSEVARS as $key => $val) {
			$key = '{'. $key .'}';
			$output = str_replace($key, $val, $output);
		}

		//check for dynamic content
		if (isset($this->BLOCKINFO[$ttag])) {
			//this template contains dynamic block(s), fill in dynamic content
			foreach ($this->BLOCKINFO[$ttag] as $key => $val) {
				if (isset($this->DYNOUTPUT[$val]))
				{
					$dynamictag = '{'. $val .'}';
					$output = str_replace($dynamictag, $this->DYNOUTPUT[$val], $output);
				}
			}
		}

		if ($this->STRICT) {
			// Warn about unresolved template fields
			if (preg_match('/\{[A-Z0-9_]+\}/', $output)) {
				$this->show_unknowns($output);
			}
		}

		if (($f_append) && (!empty($this->OUTPUT[$desttag]))) {
			$this->OUTPUT[$desttag] .= $output;     //append to output
		} else {
			$this->OUTPUT[$desttag] = $output;      //overwrite output
		}
	} // end parse_template()


//-------------------------------------------------------------
	function parse_dynamic($dynamictag, $f_append)
	{
		if (!isset($this->DYNAMIC[$dynamictag]))
			return;
		$output = $this->DYNAMIC[$dynamictag];

		//fill in vars
		foreach ($this->PARSEVARS as $key => $val) {
			$key = '{'. $key .'}';
			$output = str_replace($key, $val, $output);
		}

		//check for nested blocks
		if (isset($this->BLOCKINFO[$dynamictag])) {
			//this block contains other dynamic blocks
			//fill in dynamic content
			foreach ($this->BLOCKINFO[$dynamictag] as $key => $val) {
				if (isset($this->DYNOUTPUT[$val]))
				{
					$nestedblock = '{'. $val .'}';
					$output = str_replace($nestedblock, $this->DYNOUTPUT[$val], $output);
				}
			}
		}

		if (($f_append) && (!empty($this->DYNOUTPUT[$dynamictag]))) {
			$this->DYNOUTPUT[$dynamictag] .= $output;   //append to output
		} else {
			$this->DYNOUTPUT[$dynamictag] = $output;    //overwrite output
		}
	} // end parse_dynamic()


//-------------------------------------------------------------
// if $ttag is a template: checks template and calls parse_template
// if $ttag is a dynamic block: checks block and calls parse_dynamic
	function parse($ttag, $desttag = NULL)
	{
		global $ModelCharset;
		
		$this->assign('MODEL_CHARSET', ($ModelCharset == CHARSET_HTML) ? CHARSET_ISO8859_1 : $ModelCharset);
		$this->assign('SESSID', session_id());
		
		if ((substr($ttag, 0, 1)) == '.') {
			// Append this to a previous OUTPUT
			$ttag = substr($ttag, 1);    //strip off period
			$append = true;
		} else {
			$append = false;
		}

		if (isset($this->FILELIST[$ttag])) {
			//$ttag is a template
			//make sure template is loaded
			$this->check_template($ttag);
			$this->parse_template($ttag, $desttag, $append);
		} else if(isset($this->DYNINFO[$ttag])) {
			//$ttag is a dynamic block
			//make sure block is loaded
			$this->check_dynamic($ttag);
			$this->parse_dynamic($ttag, $append);
		} else {
			//$ttag is an unknown block
			$this->error("parse(): '$ttag' is an undefined tag", 1);
		}
	} // end parse()


//-------------------------------------------------------------
// verify if exists this dynamic block
	function exists_dynamic($dynamictag)
	{
		return (isset($this->DYNINFO[$dynamictag]));
	}


//-------------------------------------------------------------
// setup relationship between dynamic block name and the parent block that it is nested in
	function define_dynamic($dynamictag, $parenttag)
	{
		$this->DYNINFO[$dynamictag] = $parenttag;
		$this->BLOCKINFO[$parenttag][] = $dynamictag;
		//make sure template is loaded if parenttag is a template
		if (isset($this->FILELIST[$parenttag])) {
			$this->check_template($parenttag);
		}
	}


//-------------------------------------------------------------
// extract the code of the dynamic block from the template or
// block and replace it with a field
	function get_dynamic($dynamictag)
	{
		$start = sprintf($this->DYN_START, $dynamictag);
		$end = sprintf($this->DYN_END, $dynamictag);
		$endlen = strlen($end);

		$parenttag = $this->DYNINFO[$dynamictag];
		if (isset($this->TEMPLATE[$parenttag])) {
			$parent_block = &$this->TEMPLATE[$parenttag];
		} else {
			$parent_block = &$this->DYNAMIC[$parenttag];
		}
		$s = strpos($parent_block, $start);
		if ($s === false) {
			if ($this->STRICT)
				$this->error("get_dynamic(): $dynamictag not found in $parenttag", 1);
			return;
		}

		$e = strpos($parent_block, $end, $s);
		if ($e === false) {
			if ($this->STRICT)
				$this->error("get_dynamic(): no end tag for dynamic block: $dynamictag", 1);
			return;
		}

		//get code for dynamic block
		$dynstart = $s + strlen($start);
		$dynlen = $e - $dynstart;
		$this->DYNAMIC[$dynamictag] = substr($parent_block, $dynstart, $dynlen);

		//replace dynamic block with field
		$dynend = $e + $endlen;
		$dynlen = $dynend - $s;
		$field = '{'. $dynamictag .'}';
		$parent_block = substr_replace($parent_block, $field, $s, $dynlen);
	} // end get_dynamic()


//-------------------------------------------------------------
// check to see if dynamic block is loaded, if not, load it
	function check_dynamic($dynamictag)
	{
		$parenttag = $this->DYNINFO[$dynamictag];
		if (!isset($this->FILELIST[$parenttag])) {
			$this->check_dynamic($parenttag);       //make sure the parent dynamic block is loaded
		}

		if (!isset($this->DYNAMIC[$dynamictag])) {
			$this->get_dynamic($dynamictag);
		}
	}


//-------------------------------------------------------------
// remove any output for the block and strip the code out of the template
	function clear_dynamic($dynamictag)
	{
		//see if dynamic block is loaded
		if (!isset($this->DYNAMIC[$dynamictag])) {
			//template was loaded in define_dynamic since we may be inside a nested block here
			$this->get_dynamic($dynamictag);
		}

		$parenttag = $this->DYNINFO[$dynamictag];
		if (isset($this->TEMPLATE[$parenttag])) {
			$parent_block = &$this->TEMPLATE[$parenttag];
		} else {
			$parent_block = &$this->DYNAMIC[$parenttag];
		}
		$field = '{'. $dynamictag .'}';
		$parent_block = str_replace($field, '', $parent_block);

		foreach ($this->BLOCKINFO[$parenttag] as $key => $val) {
			if ($val == $dynamictag) {
				unset($this->BLOCKINFO[$parenttag][$key]);
			}
		}
		unset($this->DYNAMIC[$dynamictag]);
		unset($this->DYNINFO[$dynamictag]);
		unset($this->DYNOUTPUT[$dynamictag]);
	} // end clear_dynamic()


//-------------------------------------------------------------
	function printCopyright()
	{
		if (!$this->fCopyrightOutput) {
			if (!headers_sent()) {
				$result = false;
				$headers = headers_list();
				foreach ($headers as $hdr) {
					if (stripos($hdr, 'Content-Type:') !== false) {
						$result = true;
						break;
					}
				}

				if ($result == false)
					header('Content-Type: text/html');
			}
			print("DTemplate version ". DT_VERSION ."<BR><BR>\n");
			$this->fCopyrightOutput = 1;
		}
	}


//-------------------------------------------------------------
	function getCopyrightComment($ttag)
	{
		if (!$this->fCopyrightOutput) {
			if (isset($this->FILELIST[$ttag]))
				$file = ': '.$this->FILELIST[$ttag];
			else
				$file = ' ';
			$out = $this->COMMENT_START. "DTemplate version ". DT_VERSION. $file. $this->COMMENT_END. "\n";
			$this->fCopyrightOutput = 1;
		} else {
			$out = '';
		}
		return($out);
	}


//-------------------------------------------------------------
// alias to DPrint() for compatibility with FastTemplate
	function FastPrint($ttag)
	{
		$this->DPrint($ttag);
	}

//-------------------------------------------------------------
	function DPrint($ttag)
	{
		if( (!isset($this->OUTPUT[$ttag])) || (empty($this->OUTPUT[$ttag])) ) {
			$this->error('no parsed data for '. $ttag);
		} else {
			global $UsingXmlModel;
			if (!$UsingXmlModel)
				print($this->getCopyrightComment($ttag));
			print($this->OUTPUT[$ttag]);
		}
	}


//-------------------------------------------------------------
	function fetch($ttag)
	{
		if((!isset($this->OUTPUT[$ttag])) || (empty($this->OUTPUT[$ttag]))) {
			$this->error('no parsed data for '. $ttag);
			return('');
		}

		global $UsingXmlModel;
		if ($UsingXmlModel)
			$out = '';
		else
			$out = $this->getCopyrightComment($ttag);
		$out .= $this->OUTPUT[$ttag];
		return($out);
	}


//-------------------------------------------------------------
	function warning($errorMsg)
	{
		global $UsingXmlModel;
		if (!$UsingXmlModel)
			print($this->getCopyrightComment());
		print("[DTemplate] Warning: $errorMsg \n");
	}


//-------------------------------------------------------------
	function error($errorMsg, $die = 0)
	{
		global $UsingXmlModel;
		if ($die != 0 || !$UsingXmlModel)
			$this->printCopyright();
		print("[DTemplate] ERROR: $errorMsg \n");
		if ($die != 0)
			exit($die);
	}

} // class DTemplate

?>
