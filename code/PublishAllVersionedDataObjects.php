<?php

class PublishAllVersionedDataObjects extends WriteAllVersionedDataObjects {
	
	protected $description = 'Publishes all versioned data objects';
	
	function run($request) {
		$versioned_objects = $this->allVersionedObjectsByClass();
		foreach ($versioned_objects as $class => $objects) {
			echo "<h3>$class (count: {$objects->count()})</h3><ol>";
			foreach ($objects as $o) {
				$o->publish('Stage', 'Live');
				echo "<li>{$o->ID}<br>";
			}
			echo "</ol>";
		}
		echo 'done';
	}
}