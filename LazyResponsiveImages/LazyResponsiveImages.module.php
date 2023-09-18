<?php namespace ProcessWire;

class LazyResponsiveImages extends WireData implements Module {

	public static function getModuleInfo() {

		return [
			'title' => 'Lazy Responsive Images', 
			'summary' => 'Creates image variations and renders markup for HTML5 image srcsets.',
			'author' => 'Paul Ashby, primitive.co', 
			'version' => 1.1,
			'singular' => true,
      'autoload' => true
			];
	}

  public function ready() {
		$this->addHookBefore("Modules::saveConfig", $this, "customSaveConfig");
	}
	public function init() {

		$this->addHookAfter('InputfieldFile::fileAdded', $this, 'sizeImage');
	}
	/**
 * Store info for created elements and pass to completeInstall function
 *
 * @param  HookEvent $event
 */
  public function customSaveConfig($event) {

    $class = $event->arguments(0);
    $page_path = $this->page->path();
    if($class !== $this->className || $page_path !== wire("urls")->admin . "module/") return;
    
    // Config input
    $data = $event->arguments(1);
    $modules = $event->object;

    // Get details of fields with responsive image sizes
    $sanitized_image_fields = $this->sanitizer->text($data["image_fields"]);

    //Convert to associative array of field_name properties with comma-space delimited size string values
    parse_str($sanitized_image_fields, $image_field_spec);

    // Convert size strings to arrays
    foreach ($image_field_spec as $field_name => &$sizes) {

    	$sizes = explode(",", $sizes);
    }
    unset($field_name);
    unset($sizes);

    // Save $image_spec array
    $data["image_spec"] = $image_field_spec;
    
    $fallbacksStr = $this->sanitizer->text($data["fallbacks_by_context"]);
    if(strlen($fallbacksStr)) {
      $data["image_fallback_spec"] = $this->configStringToArray($fallbacksStr);
    }

    $data["eager_load_spec"] = $this->configStringToArray($this->sanitizer->text($data["eager_loads_by_context"]));
    $event->arguments(1, $data);

  }
	/*
		Convert configuration entry to array
	*/  
	public function configStringToArray($str) {

		parse_str($str, $arr);
		return $arr;
	}

  public function sizeImage($event) {

    	$inputfield = $event->object;
    	$image_spec = $this->image_spec;
    	$variations = false;

    	// Check whether this inputField requires responsive image variations
    	foreach ($image_spec as $field_name => $sizes) {
    		
    		if($inputfield->name == $field_name) {
    			$variations = $sizes;
    		}
    	}

    	// All done if no variations required
    	if($variations == false) return;

    	// Make variations
    	$image = $event->argumentsByName("pagefile");

    	foreach ($variations as $width) {
        
        	$image->size( $width, 0);
        }
    }

    public function getMaxEager($context) {

    	return $this["eager_load_spec"][$context];
    }
    
    public function renderImage($options) {

      $alt_str = $options["alt_str"];
      $img_class = $base_class = $options["class"] ?? "";
      $context = $options["context"] ?? "";
      $variations = $this["image_spec"][$options["field_name"]];
      $image = $options["image"];
      $aspect_ratio = $image->ratio();
      $product_data_attributes = $options["product_data_attributes"] ?? "";
      $sizes = $options["sizes"];
      $lazy_load = $options["lazy_load"] ?? false;
      $fallbacks = $this["image_fallback_spec"] ?? false;
      $extra_attributes = "";

      if(array_key_exists("extra_attributes", $options)) {
        $extra_attributes = $options["extra_attributes"];
      }
      
      // Set fallback src image
      if($fallbacks && array_key_exists($context, $fallbacks) && strlen($fallbacks[$context])){
        $src_url = $image->size($fallbacks[$context], 0)->url;
      } else {
        $src_url = $image->size(end($variations), 0)->url;
      }

      $srcset = "";
      $webp_srcset = "";
      
      foreach ($variations as $size) {
        $var_img = $image->size($size, 0);
        $webp_srcset .= $var_img->webp->url . " {$size}w,";
        $srcset .= $var_img->url . " {$size}w,";
      }

      // Remove trailing comma
      $srcset = substr($srcset, 0, -1);
      $webp_srcset = substr($webp_srcset, 0, -1);
      $data_prfx = "";

      if($lazy_load) {
        $data_prfx = "data-";
        $img_class .= " lazy noscript-hidden";
      }

      if(array_key_exists("webp", $options) && $options["webp"]) {
        return "<picture class='noscript-hidden'>
            <source type='image/webp' {$data_prfx}srcset='$webp_srcset' {$data_prfx}sizes='$sizes'/>
            <img alt='$alt_str' class='$img_class' style='aspect-ratio: $aspect_ratio' {$data_prfx}src='$src_url' {$data_prfx}srcset='$srcset' {$data_prfx}sizes='$sizes' $product_data_attributes $extra_attributes/>
        </picture>
        <noscript>
            <picture>
            <source type='image/webp' srcset='$webp_srcset'/>
            <img alt='$alt_str' class='$base_class' style='aspect-ratio: $aspect_ratio' sizes='$sizes' src='$src_url' $product_data_attributes $extra_attributes/>
            </picture>
        </noscript>";
      }
      return "<img alt='$alt_str' class='$img_class' {$data_prfx}src='$src_url' {$data_prfx}srcset='$srcset' {$data_prfx}sizes='$sizes' $product_data_attributes>
      <noscript>
        <img alt='$alt_str' class='$base_class' src='$src_url' srcset='$srcset' sizes='$sizes' $product_data_attributes>
      </noscript>";
    }
}
