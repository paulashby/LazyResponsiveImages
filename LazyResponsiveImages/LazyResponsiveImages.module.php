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
        $this->wire->addHook('Pageimages::hasOnly', $this, "hasOnlyExtension");
    }

    /**
     * Check that all images in PageImages array have the given extension
     *
     * @param  HookEvent $event
     *
     * @return Boolean $result
     */
    public function hasOnlyExtension($event) {

        $pageimages = $event->object;
        $extension = $event->arguments(0);

        // Check that all images have the given extension
        $result = $this->imageExtensionsMatch($pageimages, [$extension]);
        $event->return = $result;
    }

    /**
     * Iterate PageImages array to check file extensions match given value
     *
     * @param  PageImages $pageimages
     * @param  Array $extensions
     *
     * @return Boolean
     */
    private function imageExtensionsMatch($pageimages, $extensions) {

        foreach($pageimages as $image) {
            if (!in_array($image->ext, $extensions)) {
                return false;
            }
        }
        return true;
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
        $sanitized_image_fields_extra = $this->sanitizer->text($data["image_fields_extra"]);
        if (strlen($sanitized_image_fields_extra)) {
            $sanitized_image_fields .= "&$sanitized_image_fields_extra";
        }

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
        $image = $event->argumentsByName("pagefile");
        $is_gif = $image->ext === "gif";
        $exclude_gifs = $this->exclude_gifs === 1;

        if($is_gif && $exclude_gifs) {
            // As ProcessWire is not generating animated variations, the module config provides the exclude_gifs option.
            // This means alternative strategies can be used for gifs such as providing pre-sized animated variations in an image array
            return;
        }

        // Check whether this inputField requires responsive image variations
        foreach ($image_spec as $field_name => $sizes) {

            if ($inputfield->name == $field_name) {
                $variations = $sizes;
            }
        }

        // All done if no variations required
        if ($variations == false) {
            return;
        }

        // Make variations
        foreach ($variations as $width) {

            $image->size($width, 0);
        }
    }

    public function getMaxEager($context) {

        return $this["eager_load_spec"][$context];
    }

    public function renderImage($options) {
        $image = $options["image"];
        $art_directed = is_array($image);
        $webp = $options["webp"] ?? false;
        $alt_str = str_replace("'", "", $options["alt_str"]); // Remove apostrophes
        $options["img_class"] = $options["class"] ?? "";
        $variations = [];
        $has_source = $webp || $art_directed;
        $lazy_load = $options["lazy_load"] ?? false;
        $image_wrapper = $options["image_wrapper"] ?? false;
        $data_prfx = "";
        $use_aspect_ratio = array_key_exists("css_aspect_ratio", $options);

        // Style element is needed when art directed images need aspect ratio attributes -
        // this requires media queries so we can't simply use inline styles
        $style_elmt = "";
        $elmt_id = "";

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

                // Create style element to provide media queries if required
                // - needed when art directed image have aspect ratio attributes
                $style_elmt = $use_aspect_ratio ? "<style>" : "";

                foreach ($options["image"] as $field_name => $variant) {

                    if ($use_aspect_ratio) {
                        // Add media query for aspect ratio of this art directed image
                        $elmt_id = $this->getUniqueImageId($variant["image"]);
                        $style_elmt .= "@media screen and {$variant['media']} {
                            #$elmt_id {
                                aspect-ratio: {$variant['image']->ratio};
                            }
                        }";
                    }
                    $variations = $this["image_spec"][$field_name];
                    // Need variations in $options so we can set the src url for the image element
                    $options["variations"] = $variations;

                    $source_options = [
                        "image" => $variant["image"],
                        "media" => $variant["media"],
                        "sizes" => $variant["sizes"],
                        "data_prfx" => $data_prfx,
                        "variations" => $variations,
                        "is_animated_gif" => $this->isAnimatedGif($variant["image"], $webp)
                    ];
                    $source_markup .= $this->getSourceElmts($source_options, $webp, $art_directed);
                }
                $style_elmt .= $use_aspect_ratio ? "</style>" : "";
            } else {

                // get source for the single image with webp version
                $variations = $this["image_spec"][$options["field_name"]];
                $options["variations"] = $variations;
                $source_options = [
                    "image" => $image,
                    "media" => false,
                    "sizes" => $options["sizes"],
                    "data_prfx" => $data_prfx,
                    "variations" => $variations,
                    "is_animated_gif" => $this->isAnimatedGif($image, $webp)
                ];
                $source_markup = $this->getSourceElmts($source_options, $webp, $art_directed);
            }
            $image_attributes = $this->getImageAttributes($options, $art_directed);
            $src_url = $image_attributes["srcUrl"];

            // Art directed images have aspect ratio applied by media queries in a style block, so use empty string for inline styles
            $aspect_ratio = $use_aspect_ratio && !$art_directed ? "style='aspect-ratio:{$image_attributes["aspect_ratio"]}'" : "";

            $image_elmt = "<img alt='$alt_str' class='{$options["img_class"]}' {$data_prfx}src='$src_url' $aspect_ratio>";

            // Assemble style block and picture element
            $styled_picture = " $style_elmt
            <picture>
                $source_markup
                $image_elmt
            </picture>";

            // Wrap if required
            $picture_elmt  = $image_wrapper ? "<div id='$elmt_id' class='image-wrapper' $aspect_ratio>$styled_picture</div>" : $styled_picture;

            // Prepare noscript version
            $noscript_picture_elmt = str_replace([$data_prfx, "noscript-hidden"], "", $picture_elmt);

            // Return with noscript version
            return "$picture_elmt
                <noscript>
                    $noscript_picture_elmt
                </noscript>";
        }

        // Not art directed or webp - get standalone image markup
        $options["is_animated_gif"] = $this->isAnimatedGif($options["image"], $webp);
        $variations = $this["image_spec"][$options["field_name"]];
        $options["variations"] = $variations;
        $srcset = $this->getSrcset($options, $webp);
        $sizes = $options["sizes"];
        $image_attributes = $this->getImageAttributes($options, $art_directed);
        $src_url = $image_attributes["srcUrl"];
        $aspect_ratio = $use_aspect_ratio ? "style='aspect-ratio:{$image_attributes["aspect_ratio"]}'" : "";

        // Assemble standalone image element
        $image_markup = "<img alt='$alt_str' class='{$options["img_class"]}' {$data_prfx}srcset='$srcset' {$data_prfx}sizes='$sizes' {$data_prfx}src='$src_url' $aspect_ratio>";

        // Wrap if required
        $image_elmt = $image_wrapper ? "<div class='image-wrapper' $aspect_ratio>$image_markup</div>" : $image_markup;

        // Prepare noscript version
        $noscript_img_elmt = str_replace([$data_prfx, " class='noscript-hidden'"], "", $image_elmt);

        // Return with noscript version
        return "$image_elmt
            <noscript>
                $noscript_img_elmt
            </noscript>";
    }

    private function getUniqueImageId($image) {
        $base = $image->basename;
        $id_prefix = substr($base, 0, strpos($base, "."));
        return uniqid($id_prefix);
    }

    private function isAnimatedGif($image, $webp) {
        if(!$this->exclude_gifs || get_class($image) !== "ProcessWire\Pageimages") {
            // We're outputting static gifs
            // Or
            // we haven't been passed a Pageimages array, so we don't have pre-sized variation images
            return false;
        }

        /*
        * $image is a Pageimage array.
        * We only expect this for animated gifs, in which
        * case each image is a pre-sized variation.
        * This is necessary as ProcessWire's image sizer is
        * discarding all but the first frame of animated gifs.
        *
        */
        if ($image->hasOnly("gif")) {
            if ($webp) {
                throw new WireException("Pageimage array of gifs will output animated_gifs, but images are specified as webp.");
            }
            return true;
        } else {
            throw new WireException("Expected image options array, got ProcessWire PageImages array.");
        }
    }

    private function getImageAttributes($url_options, $art_directed = false) {
        $image = $art_directed ? end($url_options["image"])["image"] : $url_options["image"];
        $context = $url_options["context"] = $url_options["context"] ?? "";
        $fallbacks = $this["image_fallback_spec"] ?? false;

        $image_attributes = [
            "aspect_ratio" => $image->ratio()
        ];

        if (array_key_exists("is_animated_gif", $url_options) && $url_options["is_animated_gif"]) {
            $image_attributes["srcUrl"] = $image->first()->url;
        } elseif ($fallbacks && array_key_exists($context, $fallbacks) && strlen($fallbacks[$context])) {
            $image_attributes["srcUrl"] = $image->size($fallbacks[$context], 0)->url;
        } else {
            $image_attributes["srcUrl"] = $image->size(end($url_options["variations"]), 0)->url;
        }
        return $image_attributes;
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

        if ($srcset_options["is_animated_gif"]) {
            // Output animated gifs at natural sizes
            $srcset_options["image"]->sort("width");
            foreach ($srcset_options["image"] as $var_img) {
                $url = $var_img->url;
                $width = $var_img->width;
                $srcset .= "{$url} {$width}w, ";
            }
        } else {
            foreach ($srcset_options["variations"] as $size) {
                $var_img = $srcset_options["image"]->size($size, 0);
                if ($webp) {
                    $srcset .= $var_img->webp->url . " {$size}w, ";
                } else {
                    $srcset .= $var_img->url . " {$size}w, ";
                }
            }
        }
        return substr($srcset, 0, -2); // Remove trailing comma and space
    }
}