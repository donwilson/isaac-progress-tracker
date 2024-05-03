<?php
	$data = file_get_contents('/data/achievements.html');
	
	$achievements = [];
	
	if(preg_match_all("#<div class=\"achieveRow(?:[^\"]*?)\">\s*(.+?)\s*<div style=\"clear\: both;\"></div>#si", $data, $matches)) {
		foreach($matches[0] as $row) {
			$achievement = [
				'image' => '',
				'name' => '',
				'description' => '',
				'percentage' => '',
			];
			
			if(preg_match("#<img(?:[^>]*?)src=\"(.+?)\"#si", $row, $match)) {
				$achievement['image'] = trim($match[1]);
			} else {
				die("No image found for: ". $achievement['name'] . PHP_EOL);
			}
			
			if(preg_match("#<h3(?:[^>]*?)>\s*(.+?)\s*</h3>#si", $row, $match)) {
				$achievement['name'] = trim($match[1]);
			} else {
				die("No name found for: ". $achievement['name'] . PHP_EOL);
			}
			
			if(preg_match("#<h5(?:[^>]*?)>\s*(.+?)\s*</h5>#si", $row, $match)) {
				$achievement['description'] = trim($match[1]);
			}
			
			if(preg_match("#<div(?:[^>]*?)class=\"(?:[^\"]*?)achievePercent(?:[^\"]*?)\"(?:[^>]*?)>\s*([0-9\.]+)%\s*</div>#si", $row, $match)) {
				$achievement['percentage'] = trim($match[1]);
			} else {
				die("No percentage found for: ". $achievement['name'] . PHP_EOL);
			}
			
			$achievements[] = $achievement;
		}
	}
	
	//print_r($achievements);
	
	// match and merge with isaac-unlocks.json
	$unlocks = json_decode(file_get_contents('/data/isaac-unlocks.json'), true);
	
	foreach($achievements as $achievement) {
		foreach($unlocks as &$unlock) {
			if($unlock['displayName'] == $achievement['name']) {
				$unlock['image'] = $achievement['image'];
				
				if('' !== $achievement['description']) {
					$unlock['description'] = $achievement['description'];
				}
				
				$unlock['percentage'] = (float)$achievement['percentage'];
			}
		}
	}
	
	//file_put_contents('/data/isaac-unlocks-pretty.json', json_encode($unlocks, JSON_PRETTY_PRINT));
	file_put_contents('/data/isaac-unlocks-full.json', json_encode($unlocks));
	
	// check any that don't have a match
	foreach($unlocks as $unlock) {
		if(!isset($unlock['image'])) {
			echo 'No match for: '. $unlock['name'] . PHP_EOL;
		}
	}