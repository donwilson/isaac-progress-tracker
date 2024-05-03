<?php
	require_once(__DIR__ .'/../config.php');
	
	db_query("TRUNCATE TABLE `fandom_pages`");
	
	// open 'bindingofisaacre_gamepedia_pages_current.xml' for reading
	$filename = DATA_DIR .'bindingofisaacre_gamepedia_pages_current.xml';
	
	$contents = file_get_contents($filename);
	
	// parse the XML content
	$doc = new DOMDocument();
	$doc->loadXML($contents);
	
	// get the root element
	$root = $doc->documentElement;
	
	// get all elements with the tag name 'page'
	$pages = $root->getElementsByTagName("page");
	
	// iterate over all 'page' elements
	$count = 0;
	
	$acceptable_nses = [0, 10, 14];
	
	foreach($pages as $page) {
		$ns = $page->getElementsByTagName("ns")->item(0);
		
		if(!in_array($ns->nodeValue, $acceptable_nses)) {
			continue;
		}
		
		// get the 'title' element
		$title = $page->getElementsByTagName("title")->item(0);
		
		// get the 'text' element
		$text = $page->getElementsByTagName("text")->item(0);
		
		// output the title and the text
		//print "Title: ". $title->nodeValue ."\n";
		//print "Text: ". $text->nodeValue ."\n";
		
		//print "Title: ". $title->nodeValue ."\n";
		//print "Text: ". $text->nodeValue ."\n";
		
		//if(preg_match("#\{\{\s*Infobox\s+trinket\s*\|(.+?)unlocked\s*by\s*=\s*(.*?)\s*\}\}#si", $text->nodeValue, $matches)) {
		//	print_r($matches);
		//}
		
		db_query("
			INSERT INTO `fandom_pages`
			SET
				`page_title` = '". esc_sql($title->nodeValue) ."',
				`contents` = '". esc_sql($text->nodeValue) ."',
				`namespace` = '". esc_sql($ns->nodeValue) ."'
		");
		
		//if($count++ >= 100) {
		//	break;
		//}
	}