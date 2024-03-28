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
        $image = $options["image"];
        $webp = $options["webp"] ?? false;
        $alt_str = str_replace("'", "", $options["alt_str"]); // Remove apostrophes
        $options["img_class"] = $options["class"] ?? "";
        $variations = [];
        $art_directed = is_array($options["image"]);
        $has_source = $webp || $art_directed;
        $lazy_load = $options["lazy_load"] ?? false;
        $data_prfx = "";
        $aspect_ratio = array_key_exists("css_aspect_ratio", $options) ? "style='aspect-ratio:$image->ratio'" : "";

        if ($lazy_load) {
            $data_prfx = "data-";
            $options["img_class"] .= " lazy noscript-hidden";
        }
        $options["data_prfx"] = $data_prfx;

        if ($has_source) {
            // art directed or webp
            $source_markup = "";

            if ($art_directed) {
                // Iterate art directed images and get source elements
                foreach ($options["image"] as $field_name => $variant) {

                    $variations = $this["image_spec"][$field_name];
                    // Need variations in $options so we can set the src url for the image element
                    $options["variations"] = $variations;

                    $source_options = [
                        "image" => $variant["image"],
                        "media" => $variant["media"],
                        "sizes" => $variant["sizes"],
                        "data_prfx" => $data_prfx,
                        "variations" => $variations
                    ];

                    $source_markup .= $this->getSourceElmts($source_options, $webp, $art_directed);
                }
            } else {
                // get source for the single image with webp version
                $variations = $this["image_spec"][$options["field_name"]];
                $options["variations"] = $variations;

                $source_options = [
                    "image" => $image,
                    "media" => false,
                    "sizes" => $options["sizes"],
                    "data_prfx" => $data_prfx,
                    "variations" => $variations
                ];

                $source_markup = $this->getSourceElmts($source_options, $webp, $art_directed);
            }

            $src_url = $this->getSrcUrl($options, $art_directed);

            $picture_elmt = "<picture>
                $source_markup
                <img alt='$alt_str' class='{$options["img_class"]}' {$data_prfx}src='$src_url' $aspect_ratio>
            </picture>";

            $noscript_picture_elmt = str_replace([$data_prfx, "noscript-hidden"], "", $picture_elmt);

            return "$picture_elmt
                <noscript>
                    $noscript_picture_elmt
                </noscript>";
        }

        // Not art directed or webp - get standalone image markup
        $variations = $this["image_spec"][$options["field_name"]];
        $options["variations"] = $variations;
        $srcset = $this->getSrcset($options, $webp);
        $sizes = $options["sizes"];
        $src_url = $this->getSrcUrl($options, $art_directed);
        $img_elmt = "<img alt='$alt_str' class='{$options["img_class"]}' {$data_prfx}srcset='$srcset' {$data_prfx}sizes='$sizes' {$data_prfx}src='$src_url' $aspect_ratio>";
        $noscript_img_elmt = str_replace([$data_prfx, " class='noscript-hidden'"], "", $img_elmt);

        return "$img_elmt
                <noscript>
                    $noscript_img_elmt
                </noscript>";
    }

    private function getSrcUrl($url_options, $art_directed = false) {

        $image = $art_directed ? end($url_options["image"])["image"] : $url_options["image"];
        $context = $url_options["context"] = $url_options["context"] ?? "";
        $fallbacks = $this["image_fallback_spec"] ?? false;

        if ($fallbacks && array_key_exists($context, $fallbacks) && strlen($fallbacks[$context])) {
            return $image->size($fallbacks[$context], 0)->url;
        }
        return $image->size(end($url_options["variations"]), 0)->url;
    }

    private function getSourceElmts($source_options, $webp, $art_directed = false) {
        $data_prfx = $source_options["data_prfx"];
        $media_str = !$art_directed ? "" : "media='{$source_options["media"]}'";
        $sizes = $source_options["sizes"];
        $source_elmts = "";
        // Source for webp
        if ($webp) {
            $webp_srcset = $this->getSrcset($source_options, $webp);
            $source_elmts .= "<source type='image/webp' $media_str {$data_prfx}srcset='$webp_srcset' {$data_prfx}sizes='$sizes'>";
        }

        // Source for regular image type
        $srcset = $this->getSrcset($source_options, false);
        $source_elmts .= "<source $media_str {$data_prfx}srcset='$srcset' {$data_prfx}sizes='$sizes'>";

        return $source_elmts;
    }

    public function getSrcset($srcset_options, $webp) {
        $srcset = "";

        foreach ($srcset_options["variations"] as $size) {
            $var_img = $srcset_options["image"]->size($size, 0);
            if ($webp) {
                $srcset .= $var_img->webp->url . " {$size}w,";
            } else {
                $srcset .= $var_img->url . " {$size}w,";
            }
        }
        return substr($srcset, 0, -1); // Remove trailing comma
    }
}