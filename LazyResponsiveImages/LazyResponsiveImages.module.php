<?php
namespace ProcessWire;

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
        if ($class !== $this->className || $page_path !== wire("urls")->admin . "module/")
            return;

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
        if (strlen($fallbacksStr)) {
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

            if ($inputfield->name == $field_name) {
                $variations = $sizes;
            }
        }

        // All done if no variations required
        if ($variations == false)
            return;

        // Make variations
        $image = $event->argumentsByName("pagefile");

        foreach ($variations as $width) {

            $image->size($width, 0);
        }
    }

    public function getMaxEager($context) {

        return $this["eager_load_spec"][$context];
    }

    public function renderImage($options) {
        bd($options);
        $image = $options["image"];
        $options["alt_str"] = str_replace("'", "", $options["alt_str"]); // Remove apostrophes
        $options["aspect_ratio"] = $image->ratio();
        $options["img_class"] = $options["class"] ?? "";
        $product_data_attributes = $options["product_data_attributes"] ?? "";
        $extra_attributes = $options["extra_attributes"] ?? "";
        $options["custom_attributes"] = "$product_data_attributes $extra_attributes";
        $variations = $this["image_spec"][$options["field_name"]];

        // Set fallback src image
        $context = $options["context"] ?? "";
        $fallbacks = $this["image_fallback_spec"] ?? false;
        if ($fallbacks && array_key_exists($context, $fallbacks) && strlen($fallbacks[$context])) {
            $options["src_url"] = $image->size($fallbacks[$context], 0)->url;
        } else {
            $options["src_url"] = $image->size(end($variations), 0)->url;
        }

        $srcset = "";
        $webp_srcset = "";

        foreach ($variations as $size) {
            $var_img = $image->size($size, 0);
            $webp_srcset .= $var_img->webp->url . " {$size}w,";
            $srcset .= $var_img->url . " {$size}w,";
        }

        $options["srcset"] = substr($srcset, 0, -1); // Remove trailing comma
        $options["webp_srcset"] = substr($webp_srcset, 0, -1);

        $lazy_load = $options["lazy_load"] ?? false;
        if ($lazy_load) {
            $options["data_prfx"] = "data-";
            $options["img_class"] .= " lazy noscript-hidden";
        } else {
            $options["data_prfx"] = "";
        }

        $options["img_markup"] = $this->getImageMarkup($options);

        $webp = array_key_exists("webp", $options) && $options["webp"];

        if ($webp) {
            return $this->renderPictureElement($options);
        }
        return $this->renderImageElement($options);
    }

    private function renderPictureElement($options) {

        return "<picture class='noscript-hidden'>
                <source type='image/webp' {$options["data_prfx"]}srcset='{$options["webp_srcset"]}' {$options["data_prfx"]} sizes='{$options["sizes"]}'/>
                {$options["img_markup"]["scripton"]}
            </picture>
            <noscript>
                <picture>
                <source type='image/webp' srcset='{$options["webp_srcset"]}'/>
                {$options["img_markup"]["noscript"]}
                </picture>
            </noscript>";

    }
    private function renderImageElement($options) {

        return "{$options["img_markup"]["scripton"]}
            <noscript>
                {$options["img_markup"]["noscript"]}
            </noscript>";
    }
    private function getImageMarkup($options) {
        return [
            "scripton" => "<img alt='{$options["alt_str"]}' class='{$options["img_class"]}' style='aspect-ratio: {$options["aspect_ratio"]}' {$options["data_prfx"]}src='{$options["src_url"]}' {$options["data_prfx"]}srcset='{$options["srcset"]}' {$options["data_prfx"]}sizes='{$options["sizes"]}' {$options["custom_attributes"]}/>",
            "noscript" => "<img alt='{$options["alt_str"]}' class='{$options["class"]}' style='aspect-ratio: {$options["aspect_ratio"]}' sizes='{$options["sizes"]}' src='{$options["src_url"]}' {$options["custom_attributes"]}/>"
        ];
    }
}