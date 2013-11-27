<?php

class WriteAllVersionedDataObjects extends BuildTask {
	
	protected $description = 'Writes all versioned data objects (creating a new version)';
	
	function run($request) {
		$versioned_objects = $this->allVersionedObjectsByClass();
		foreach ($versioned_objects as $class => $objects) {
			echo "<h3>$class (count: {$objects->count()})</h3><ol>";
			foreach ($objects as $o) {
				$o->write(false, false, true); // force write even w/o a change
				echo "<li>{$o->ID}<br>";
			}
			echo "</ol>";
		}
		echo 'done';
	}
	
	protected function allVersionedObjectsByClass() {
		$do_classes = array_values(ClassInfo::subclassesFor('DataObject'));
		$callback = function($class) {
			return Object::has_extension($class, 'VersionedDataObjects');
		};
		$result = array();
		$versioned_classes = array_values(array_filter($do_classes, $callback));
		foreach ($versioned_classes as  $versioned_class) {
			$objects = $versioned_class::get();
			$result[$versioned_class] = $objects;
		}
		return $result;
	}
}