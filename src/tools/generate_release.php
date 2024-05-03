<?php
	require_once(__DIR__ .'/../config.php');
	
	if(!defined('RELEASE_DIR') || !RELEASE_DIR) {
		die('RELEASE_DIR not defined or found'. PHP_EOL);
	}
	
	if(!is_dir(RELEASE_DIR)) {
		mkdir(RELEASE_DIR, 0755, true);
	}
	
	if(!defined('PUBLIC_DIR') || !PUBLIC_DIR || !is_dir(PUBLIC_DIR)) {
		die('PUBLIC_DIR not defined or found'. PHP_EOL);
	}
	
	if(!defined('DEV_PUBLIC_URI') || !DEV_PUBLIC_URI) {
		die('DEV_PUBLIC_URI not defined'. PHP_EOL);
	}
	
	// empty the release folder
	function empty_release_folder() {
		$fh = opendir(RELEASE_DIR);
		
		while($file = readdir($fh)) {
			if($file == '.' || $file == '..') {
				continue;
			}
			
			unlink(RELEASE_DIR . $file);
		}
		
		closedir($fh);
	}
	
	// copy specific files
	function copy_initial_public_files() {
		$fh = opendir(PUBLIC_DIR);
		
		while($file = readdir($fh)) {
			if($file == '.' || $file == '..') {
				continue;
			}
			
			if(!preg_match("#\.(css|js|png|jpg|jpeg|gif|ico|svg|webp|webmanifest|json)$#i", $file)) {
				continue;
			}
			
			if(!is_file(PUBLIC_DIR . $file)) {
				continue;
			}
			
			copy(PUBLIC_DIR . $file, RELEASE_DIR . $file);
		}
		
		closedir($fh);
	}
	
	// fetch the index html
	function fetch_index_html() {
		$ch = curl_init();
		
		curl_setopt_array($ch, [
			CURLOPT_URL => DEV_PUBLIC_URI,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 3,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36 Edge/16.16299',
			CURLOPT_TIMEOUT => 10
		]);
		
		$index_html = curl_exec($ch);
		
		curl_close($ch);
		
		return $index_html;
	}
	
	function save_release_index_html() {
		$index_html = fetch_index_html();
		
		if(!$index_html) {
			die('Failed to fetch index html'. PHP_EOL);
		}
		
		// prepare the html (replace dev uri with release uri)
		if(defined('RELEASE_URI') && RELEASE_URI && defined('DOCKER_PUBLIC_URI') && DOCKER_PUBLIC_URI) {
			$index_html = str_replace(DOCKER_PUBLIC_URI, RELEASE_URI, $index_html);
		}
		
		// save
		file_put_contents(RELEASE_DIR .'index.html', $index_html);
	}
	
	// do the thing
	empty_release_folder();
	copy_initial_public_files();
	save_release_index_html();
	
	print 'Release generated'. PHP_EOL;