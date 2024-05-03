<?php
	function fetch_fandom_page_content($page_title, $previous_checks=[]) {
		if(in_array($page_title, $previous_checks)) {
			return "";
		}
		
		$page = get_row("
			SELECT
				fandom_pages.*
			FROM `fandom_pages`
			WHERE
				fandom_pages.page_title = '". esc_sql($page_title) ."'
		");
		
		if(empty($page['contents'])) {
			return "";
		}
		
		if(preg_match("#\#redirect\s*\[\[(.*?)(?:\#(?:.*?))?\]\]#si", $page['contents'], $matches)) {
			return fetch_fandom_page_content($matches[1], array_merge($previous_checks, [$page_title]));
		}
		
		return $page;
	}
	
	function get_characters_regex() {
		global $character_names;
		
		$character_regex = [];
		
		$sorted_characters_list = $character_names;
		
		// sort character list by string length desc
		usort($sorted_characters_list, function ($a, $b) {
			return strlen($b) <=> strlen($a);
		});
		
		foreach($sorted_characters_list as $character) {
			$character_regex[] = preg_quote($character, "#");
		}
		
		return implode("|", $character_regex);
	}
	
	function get_bosses_regex() {
		global $boss_names;
		
		$sorted_bosses_list = $boss_names;
		
		// sort boss list by string length desc
		usort($sorted_bosses_list, function ($a, $b) {
			return strlen($b) <=> strlen($a);
		});
		
		foreach($sorted_bosses_list as $boss) {
			$boss_regex[] = preg_quote($boss, "#");
		}
		
		return implode("|", $boss_regex);
	}
	
	function parse_db_achievements() {
		$achievements = [];
		
		$raw_achievements = get_var("SELECT `contents` FROM `fandom_pages` WHERE `page_title` = 'Achievements'");
		
		if(empty($raw_achievements)) {
			return $achievements;
		}
		
		//try {
		//	$parser = new Jungle_WikiSyntax_Parser($raw_achievements, 'Achievements');
		//	$cargo = $parser->parse();
		//	
		//	die("<pre>". print_r($cargo, true));
		//} catch(Exception $e) {
		//	die("Error: ". $e->getMessage() . PHP_EOL);
		//}
		
		// certain fixes
		$raw_achievements = preg_replace("#\[\[([^\]]*?)(?:\#[^\|]*?)?\|([^\]]*?)\]\]#si", "\\1", $raw_achievements);
		$raw_achievements = preg_replace("#\[\[([^\]]*?)(?:\#[^\|]*?)?\]\]#si", "\\1", $raw_achievements);
		$raw_achievements = preg_replace_callback("#\{\{([A-Za-z])\|(.+?)\}\}#si", function($match) {
			return "[[". $match[1] .".". str_replace("|", ".", $match[2]) ."]]";
		}, $raw_achievements);
		$raw_achievements = preg_replace("#<br>{{plat|PS4}}&nbsp;[0-9]+#si", "", $raw_achievements);
		$raw_achievements = preg_replace("#<br>{{plat|PS4}}&nbsp;[0-9]+#si", "", $raw_achievements);
		$raw_achievements = preg_replace_callback("#\{\{\s*(dlc|machine)\|(.*?)\}\}#si", function($match) {
			return "[[". $match[1] .".". str_replace("|", ".", $match[2]) ."]]";
		}, $raw_achievements);
		$raw_achievements = preg_replace("#\{\{\s*(plat)\s*\|(.+?)\}\}#si", "[[\\1.\\2]]", $raw_achievements);
		
		if(!preg_match_all("#\{\{\s*infobox\s*achievement\s*\|(.+?)\|\s*id\s*=\s*([0-9]+)(.*?)\}\}#si", $raw_achievements, $matches)) {
			return $achievements;
		}
		
		foreach($matches[0] as $i => $match) {
			$achievement = [
				'name' => '',
				'link' => '',
				'description' => '',
				'unlocked by' => '',
				'id' => '',
				'character' => '',
				'boss' => '',
			];
			
			if(preg_match("#\|\s*name\s*=\s*(.*?)\s*\|#si", $match, $submatch)) {
				$achievement['name'] = trim($submatch[1]);
			}
			
			if(preg_match("#\|\s*link\s*=\s*(.*?)\s*\|#si", $match, $submatch)) {
				$achievement['link'] = trim($submatch[1]);
			}
			
			if(preg_match("#\|\s*description\s*=\s*(.*?)\s*\|#si", $match, $submatch)) {
				$achievement['description'] = trim($submatch[1]);
			}
			
			if(preg_match("#\|\s*unlocked by\s*=\s*(.*?)\s*\|#si", $match, $submatch)) {
				$achievement['unlocked by'] = trim($submatch[1]);
			}
			
			if(preg_match("#\|\s*id\s*=\s*([0-9]+)#si", $match, $submatch)) {
				$achievement['id'] = trim($submatch[1]);
			}
			
			if('' === $achievement['id']) {
				die("No ID found for: ". $achievement['name'] . PHP_EOL);
			}
			
			$achievements[
				$achievement['id']
			] = $achievement;
		}
		
		return $achievements;
	}
	
	function display_achievement_text($text) {
		//return $text;
		
		$text = preg_replace("#<\!\-\-.*?\-\->#si", "", $text);
		
		$ignore_texts = [
			"a",
			"a+",
			"na+",
			"na",
			"nr",
			"a+nr",
			"r",
			//"<br>",
		];
		
		$ignore_texts_regex = [];
		
		foreach($ignore_texts as $ignore_text) {
			$ignore_texts_regex[] = "dlc\.". preg_quote($ignore_text, "#");
			$ignore_texts_regex[] = preg_quote($ignore_text, "#");
		}
		
		$ignore_texts_regex = implode("|", $ignore_texts_regex);
		
		if(preg_match("#\(\s*\[\[(". $ignore_texts_regex .")\]\]\s*except #si", $text)) {
			// just remove for (except ...)
			$text = preg_replace("#\(\[\[(". $ignore_texts_regex .")\]\]\s*except #si", "(except ", $text);
		} else {
			$ignore_matches = preg_split("#\[\[(?:". $ignore_texts_regex .")\]\]#si", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
			
			if(count($ignore_matches) > 1) {
				$last_text = trim(array_pop($ignore_matches));
				
				if(!empty($last_text)) {
					$text = $last_text;
				}
			}
		}
		
		$text = preg_replace_callback("#\[\[(e|r|c|i|s|t|p)\.([^\]]*?)(?:\.([A-Za-z]))?\]\]#si", function($match) use ($ignore_texts) {
			if(in_array($match[2], $ignore_texts)) {
				return "";
			}
			
			//return "<code>". $match[2] ."</code>";
			return $match[2];
		}, $text);
		
		$text = preg_replace_callback("#\[(e|r|c|i|s|t|p)\.([^\]]*?)(?:\.([A-Za-z]))?\]#si", function($match) use ($ignore_texts) {
			if(in_array($match[2], $ignore_texts)) {
				return "";
			}
			
			//return "<code>". $match[2] ."</code>";
			return $match[2];
		}, $text);
		
		$text = preg_replace_callback("#\[\[(dlc|machine)\.([^\]]*?)(?:\.([A-Za-z]))?\]\]#si", function($match) use ($ignore_texts) {
			if(in_array($match[2], $ignore_texts)) {
				return "";
			}
			
			//return "<code>". $match[2] ."</code>";
			return $match[2];
		}, $text);
		
		return trim($text);
	}
	
	function sanitize_achievement_text($text) {
		$text = preg_replace("#<\!\-\-.*?\-\->#si", "", $text);
		$text = preg_replace("#\[\[(e|r|c|i|s|t|p)\.([^\]]*?)(?:\.([A-Za-z]))?\]\]#si", "\\2", $text);
		$text = preg_replace("#\[(e|r|c|i|s|t|p)\.([^\]]*?)(?:\.([A-Za-z]))?\]#si", "\\2", $text);
		$text = preg_replace("#\[\[(dlc|machine)\.([^\]]*?)(?:\.([A-Za-z]))?\]\]#si", "\\2", $text);
		
		return $text;
	}