<?php namespace ProcessWire;

/**
 * Optional config file for PageMaker.module
 *
 * When present, the module will be configurable and the configurable properties
 * described here will be automatically populated to the module at runtime.  
 */
$config = array(
	
	'imageFields' => array(
		'name'=> 'image_fields',
		'type' => 'text', 
		'label' => 'Image widths',
		'description' => 'Ampersand separated image field entries with comma separated width values, eg "product_shot=100,150,330&product_shot_back=330"', 
		'value' => '', 
		'required' => true 
	),
	'eagerLoadsByContext' => array(
		'name'=> 'eager_loads_by_context',
		'type' => 'text', 
		'label' => 'Number of eager load images to use in each context',
		'description' => 'Ampersand separated context names with single integer value, eg "topcat=8&subcat=10"', 
		'value' => '', 
		'required' => true 
	),
	'fallbacksByContext' => array(
		'name'=> 'fallbacks_by_context',
		'type' => 'text', 
		'label' => 'Width of image to use as src for older browsers',
		'description' => 'Ampersand separated context names with single width value, eg "listing=100&lightbox=330"', 
		'value' => '', 
		'required' => false 
	)
);