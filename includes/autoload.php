<?php

if ( ! defined( 'ABSPATH' ) )	exit;

// include the generic functions file.
require_once BRIDGERPAY_DIR . 'includes/functions.php';

spl_autoload_register( function ( $class ) {

	// project-specific namespace prefix
	$allowed_prefixes = array(
		array(
			'namespace' => 'Bridgerpay',
			'base_dir' => BRIDGERPAY_DIR.'includes'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR,
		),
		// array(
		// 	'namespace' => 'BBWP\General',
		// 	'base_dir' => BRIDGERPAY_DIR.'classes/general/',
		// ),
	);

	foreach($allowed_prefixes as $prefix){

		// If the specified $class does not include our namespace, duck out.
		if ( false === strpos( $class, $prefix['namespace'] ) ) {
			continue; 
		}

		// does the class use the namespace prefix?
		$len = strlen( $prefix['namespace'] );

		// get the relative class name
		$relative_class_string = substr( $class, $len+1 );
		
		$relative_class_dir = explode('\\', $relative_class_string);
		$relative_class = array_pop($relative_class_dir);
		
		$relative_dir = '';
		if($relative_class_dir && is_array($relative_class_dir) && count($relative_class_dir) >= 1)
			$relative_dir = implode(DIRECTORY_SEPARATOR, $relative_class_dir);
		
		$dir_path = strtolower(
			preg_replace(
				['/([a-z])([A-Z])/', '/_/', '/\\\/'],
				['$1$2', '-', DIRECTORY_SEPARATOR],
				$relative_dir
			)
		);
		if($dir_path)
			$file = $prefix['base_dir'].$dir_path.DIRECTORY_SEPARATOR.$relative_class.'.php';
		else
			$file = $prefix['base_dir'].$relative_class.'.php';
			
		//db($file);
		// if the file exists, require it
		if ( file_exists( $file ) ) {
			include_once( $file );
			break;
		}

	}
	
} );