<?php

$prefix_file = $argv[2] ? $argv[2] : "VB";
$file_name = $argv[1] ? $argv[1] : "data.json";
$path_result = $argv[3] ? $argv[3] : "./objects";

$system_types = ["NSArray", "NSNumber", "BOOL", "NSString"];

$template_header_file = file_get_contents("templates/header.template");
$template_m_file = file_get_contents("templates/impl.template");
$template_collection_file = file_get_contents("templates/collection.template");
$template_object_file = file_get_contents("templates/object.template");

$global_clases = array();

function makeMetaData($class, $json) {
	$classObject = array();

	foreach ($json as $key => $object) {
		
		$propery = new stdClass();
		$propery->name = $key;
		$propery->is_collection = false;
		//$propery->debug = $object;

		if ( is_array($object) ) {
			$propery->type = className($key);
			$propery->is_collection = true;
			makeMetaData($key, array_values($object)[0]);
		} elseif (is_numeric($object)) {
			$propery->type = "NSNumber";
		} elseif (is_bool($object)) {
			$propery->type = "BOOL";
		} elseif (is_string($object)) {
			$propery->type = "NSString"; 
		} elseif (is_object($object)) {
			$propery->type = className($key);
			makeMetaData($key, $object);
		} else {
			$propery->type = "NSString"; 
		}	
		$classObject[] = $propery;		
	}

	addClassToMetaData($class, $classObject);	
}


function addClassToMetaData($class, $classObject) {
	$GLOBALS['global_clases'][$class] = $classObject;
}


function parameterName($meta) {
	$name = lcfirst($meta->name);
	if ( $meta->is_collection ) {
		if ( strtolower(substr($name, strlen($name) - 1, 1)) != 's' ) {
			$name .= "s";
		}
	}
	return $name;
}

function className($name) {
	return $GLOBALS['prefix_file'].$name."Object";
}


function saveHeaderFile($name, $meta_data_class) {
	$className = className($name);
	$fileName = $className.".h";
	$content = $GLOBALS['template_header_file'];

	$content = str_replace("#TEMPLATE_CLASS_NAME#", $className, $content);

	$properties = "";
	$classes = "";

	foreach ($meta_data_class as $meta) {
		$name = parameterName($meta);
		$currentClass = className($meta->name);
		$type = ($meta->is_collection == true) ? "NSArray" : $meta->type;
		$property = "";
		if ( $meta->type == 'BOOL' ) {
			$property = "@property (nonatomic, assign) BOOL {$name};\n";
		} else {
			$property = "@property (nonatomic, strong) {$type} * {$name};\n";
		}
		$properties .= $property;

		if ( !in_array($meta->type, $GLOBALS['system_types']) ) {
			$classes .= "@class {$currentClass};\n";
		}
	}	

	$content = str_replace("#TEMPLATE_PROPERTIES#", $properties, $content);
	$content = str_replace("#TEMPLATE_CUSTOM_CLASSES#", $classes, $content);

	file_put_contents($GLOBALS['path_result'].'/'.$fileName, $content, null);

}


function saveImplementFile($name, $meta_data_class) {
	$className = className($name);
	$fileName = $className.".m";
	$content = $GLOBALS['template_m_file'];
	$template_collection = $GLOBALS['template_collection_file'];
	$template_object = $GLOBALS['template_object_file'];

	$content = str_replace("#TEMPLATE_CLASS_NAME#", $className, $content);

	$properties = "";
	$classes = "";

	foreach ($meta_data_class as $meta) {
		$name = parameterName($meta);

		if ( $meta->is_collection  ) {
			$map = str_replace("#TEMPLATE_PROPERTY_NAME#", $name, $template_collection);
			$map = str_replace("#TEMPLATE_PROPERTY_CLASS#", $meta->type, $map);
			$map = str_replace("#TEMPLATE_PROPERTY_KEY#", $meta->name, $map);
			$properties .= "{$map},\n";
		} else {
			$map = str_replace("#TEMPLATE_PROPERTY_NAME#", $name, $template_object);
			$map = str_replace("#TEMPLATE_PROPERTY_KEY#", $meta->name, $map);
			$properties .= "{$map},\n";
		}

		// if ( !in_array($meta->type, $GLOBALS['system_types']) ) {
		// 	$classes .= "#import \"{$className}.h\";\n";
		// }
	}	

	$content = str_replace("#TEMPLATE_MAP_PROPERTIES#", $properties, $content);
	$content = str_replace("#TEMPLATE_IMPORT_CLASSES#", $classes, $content);

	file_put_contents($GLOBALS['path_result'].'/'.$fileName, $content, null);

}

//MAIN

if ( $path_result && !file_exists($path_result) ) {
	mkdir($path_result, 0777, true);
}

$string = file_get_contents($file_name);
$json = json_decode($string, false);

makeMetaData("Main", $json);

foreach ($global_clases as $name => $meta) {
	saveHeaderFile($name, $meta);
	saveImplementFile($name, $meta);
}

?>