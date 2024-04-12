<?php namespace ProcessWire;

$config = array(

	'imageFields' => array(
		'name'=> 'image_fields',
		'type' => 'text',
		'label' => 'Image widths',
		'description' => 'Ampersand separated image field entries with comma separated width values, eg "product_shot=100,150,330&product_shot_back=330"', 
		'value' => '',
		'required' => true
	),
    'imageFieldsExtra' => array(
		'name'=> 'image_fields_extra',
		'type' => 'text',
		'label' => 'Image widths - additional entries',
		'description' => 'Ampersand separated image field entries with comma separated width values, eg "product_shot=100,150,330&product_shot_back=330"', 
		'value' => '',
		'required' => false
	),
    'excludeGifs' => array(
		'name'=> 'exclude_gifs',
		'type' => 'checkbox',
		'label' => 'Exclude gifs from variations',
		'description' => 'Useful if employing alternative strategy for animated gifs since Processwire is currently generating only single frame variations',
		'autocheck' => 0,
		'checkedValue' => 1,
		'uncheckedValue' => 0,
		'value' => $this->exclude_gifs,
		'required' => false
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