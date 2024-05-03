<?php
	require_once(__DIR__ ."/../config.php");
	
	$character_regex = get_characters_regex();
	
	$boss_regex = get_bosses_regex();
	
	// load all unlocks
	if(!file_exists(DATA_DIR ."isaac-unlocks-full.json")) {
		die("Missing full unlocks data file.". PHP_EOL);
	}
	
	$unlocks = json_decode(file_get_contents(DATA_DIR ."isaac-unlocks-full.json"), true);
	
	// unlock categories
	if(!file_exists(DATA_DIR ."isaac-unlocks-categories.json")) {
		die("Missing unlock categories data file.". PHP_EOL);
	}
	
	$raw_categories = json_decode(file_get_contents(DATA_DIR ."isaac-unlocks-categories.json"), true);
	$categories = [];
	
	foreach($raw_categories as $category) {
		$categories[ $category['id'] ] = $category['name'];
	}
	
	// sort remaining unlocks by percentage descending
	usort($unlocks, function($a, $b) {
		return $b['percentage'] <=> $a['percentage'];
	});
	
	$db_achievements = parse_db_achievements();
	
	// update the unlocks with the database achievements
	$created_categories = [];
	foreach($unlocks as $key => $unlock) {
		// fetch the achievement details from the database
		$related_achievement = $db_achievements[ $unlock['name'] ] ?? false;
		
		// generate link
		$link = urlencode(str_replace(" ", "_", $unlock['displayName']));
		
		if(!empty($related_achievement['link'])) {
			$link = urlencode(str_replace(" ", "_", $related_achievement['link']));
		}
		
		$unlocks[ $key ]['link'] = $link;
		
		// parse description
		$description_original = $unlock['description'] ?? '';
		$description = $unlock['description'] ?? '';
		
		if(!empty($related_achievement['description'])) {
			$description_original = sanitize_achievement_text($related_achievement['description']);
			$description = display_achievement_text($related_achievement['description']);
		}
		
		// category from certain descriptions
		$cat_nth = $unlock['category'] ?? false;
		$category_texted = false;
		
		$empty_the_description = false;
		
		if("Unlocked a new challenge." === $description) {
			$category_texted = "Challenges";
			$empty_the_description = true;
		} elseif("Unlocked a new character." === $description) {
			$category_texted = "Characters";
			$empty_the_description = true;
		} elseif("Unlocked a new item." === $description) {
			$category_texted = "Items";
			$empty_the_description = true;
		} elseif("Unlocked a new starting item." === $description) {
			$category_texted = "Starting Items";
			$empty_the_description = true;
		} elseif("Unlocked a new co-player baby." === $description) {
			$category_texted = "Co-Player Babies";
			$empty_the_description = true;
		} elseif("???" === $description) {
			$category_texted = "Secrets";
			$empty_the_description = true;
		} elseif("Unlocked a new area." === $description) {
			$category_texted = "Areas";
			$empty_the_description = true;
		} elseif(preg_match("#Complete Challenge#si", $description)) {
			$category_texted = "Challenges";
			$empty_the_description = true;
		}
		
		// if "Repentance" is in the description, look up specific page
		//if("20" == $unlocks[ $key ]['category']) {
			// find page
			$page = fetch_fandom_page_content($unlock['displayName']);
			$page_title = $page['page_title'] ?? '';
			$page_contents = $page['contents'] ?? '';
			
			if(empty($page_contents) && !empty($related_achievement['link']) && ($unlock['displayName'] !== $related_achievement['link'])) {
				$page = fetch_fandom_page_content($related_achievement['link']);
				$page_title = $page['page_title'] ?? '';
				$page_contents = $page['contents'] ?? '';
			}
			
			$page_contents = preg_replace("#<br\s*/?>#si", " ", $page_contents);
			$page_contents = preg_replace("#&nbsp;#si", " ", $page_contents);
			
			if(!empty($related_achievement['link']) && ("Runes" === $related_achievement['link'])) {
				$category_texted = "Items: Rune";
			} elseif(!empty($related_achievement['link']) && ("Cards" === $related_achievement['link'])) {
				$category_texted = "Items: Card";
			} elseif("Cards and Runes" === $page_title) {
				$category_texted = "Items: Card";
			} elseif("Co-op" === $page_title) {
				$category_texted = "Co-op Baby";
			} elseif(preg_match("#\{\{\s*infobox\s*activated\s*collectible#si", $page_contents)) {
				$category_texted = "Items: Active";
			} elseif(preg_match("#\{\{\s*infobox\s*passive\s*collectible#si", $page_contents)) {
				$category_texted = "Items: Passive";
			} elseif(preg_match("#\{\{\s*infobox\s*pickup#si", $page_contents)) {
				$category_texted = "Items: Pickup";
			} elseif(preg_match("#\{\{\s*infobox\s*trinket#si", $page_contents)) {
				$category_texted = "Items: Trinket";
			} elseif(preg_match("#\{\{\s*infobox\s*stage#si", $page_contents)) {
				$category_texted = "Chapters";
			} elseif(preg_match("#\{\{\s*infobox\s*characters?#si", $page_contents)) {
				$category_texted = "Characters";
			} elseif(preg_match("#\{\{\s*infobox\s*challenge#si", $page_contents)) {
				$category_texted = "Challenges";
			} elseif(preg_match("#\{\{\s*infobox\s*achievement#si", $page_contents)) {
				$category_texted = "Achievements";
			} elseif(preg_match("#\{\{\s*disambig\s*msg(?:[^\}]*?)title\s*=\s*coin(?:[^\}]*?)\}\}#si", $page_contents)) {
				$category_texted = "Items: Coin";
			} elseif(preg_match("#\{\{\s*disambig\s*msg(?:[^\}]*?)title\s*=\s*key(?:[^\}]*?)\}\}#si", $page_contents)) {
				$category_texted = "Items: Key";
			} elseif(preg_match("#\{\{\s*disambig\s*msg(?:[^\}]*?)title\s*=\s*heart(?:[^\}]*?)\}\}#si", $page_contents)) {
				$category_texted = "Items: Heart";
			}
			
			$unlocks[ $key ]['category_texted'] = $category_texted;
			$unlocks[ $key ]['page'] = $page;
		//}
		
		if(false !== $category_texted) {
			if(in_array($category_texted, $categories)) {
				$cat_nth = array_search($category_texted, $categories);
			} else {
				if(in_array($category_texted, $created_categories)) {
					$cat_nth = $created_categories[ $category_texted ];
				} else {
					$cat_nth = count($categories) + 1;
					
					$created_categories[ $category_texted ] = $cat_nth;
					$categories[ $cat_nth ] = $category_texted;
				}
			}
			
			if($empty_the_description) {
				$description = "";
			}
		}
		
		$unlocks[ $key ]['description'] = $description;
		$unlocks[ $key ]['category'] = $cat_nth;
		
		// parse unlock method
		$unlock_method_original = $unlock['unlockedBy'] ?? '';
		$unlock_method = $unlock['unlockedBy'] ?? '';
		
		if(!empty($related_achievement['unlocked by'])) {
			$unlock_method_original = sanitize_achievement_text($related_achievement['unlocked by']);
			$unlock_method = display_achievement_text($related_achievement['unlocked by']);
		}
		
		$unlocks[ $key ]['unlockMethod'] = $unlock_method;
		
		// character-specific stuff
		$unlocks[ $key ]['as'] = false;
		
		if(preg_match("#\s+(?:with|as)\s+(". $character_regex .")(?:\b|$|\s)#si", $unlock_method_original, $match)) {
			$unlocks[ $key ]['as'] = $match[1];
		} elseif(preg_match("#\s+(?:with|as)\s+(". $character_regex .")(?:\b|$|\s)#si", $description_original, $match)) {
			$unlocks[ $key ]['as'] = $match[1];
		}
		
		// boss-specific stuff
		$unlocks[ $key ]['boss'] = false;
		
		if(preg_match("#(?:Defeat|Complete the)\s+(". $boss_regex .")(?:\b|$|\s)#si", $description_original, $match)) {
			$unlocks[ $key ]['boss'] = $match[1];
		} elseif(preg_match("#(?:Defeat|Complete the)\s+(". $boss_regex .")(?:\b|$|\s)#si", $unlock_method_original, $match)) {
			$unlocks[ $key ]['boss'] = $match[1];
		}
		
		// achievement
		$unlocks[ $key ]['achievement'] = $related_achievement;
		
		$unlocks[ $key ]['description_original'] = $description_original;
		$unlocks[ $key ]['unlock_method_original'] = $unlock_method_original;
	}
	
	// sanitize unlocks
	foreach($unlocks as $key => $unlock) {
		// replace http:// with https:// in image URLs
		if(!empty($unlock['icon'])) {
			$unlocks[ $key ]['icon'] = str_replace("http://", "https://", $unlock['icon']);
		}
		
		// remove empty descriptions
		if(!empty($unlock['description']) && !empty($unlock['unlockMethod']) && (strtolower(trim($unlock['description'], " \t\r\n.")) === strtolower(trim($unlock['unlockMethod'], " \t\r\n.")))) {
			$unlocks[ $key ]['description'] = "";
		}
		
		unset($unlocks[ $key ]['defaultvalue']);
		unset($unlocks[ $key ]['category_texted']);
		unset($unlocks[ $key ]['page']);
		unset($unlocks[ $key ]['description_original']);
		unset($unlocks[ $key ]['unlock_method_original']);
		unset($unlocks[ $key ]['achievement']);
		unset($unlocks[ $key ]['image']);
		unset($unlocks[ $key ]['icongray']);
		unset($unlocks[ $key ]['hidden']);
	}
	
	// sort categories by name
	asort($categories);
	
	// create the data to save
	$save_data = [
		'unlocks' => $unlocks,
		'character_names' => $character_names,
		'boss_names' => $boss_names,
		'categories' => $categories,
		'generated' => time(),
	];
	
	// save to internal data file
	file_put_contents(DATA_DIR ."isaac-unlocks-prod.json", json_encode($save_data));
	
	// save to public data directory
	file_put_contents(PUBLIC_DIR ."unlocks.json", json_encode($save_data));