# LazyResponsiveImages

  [<img src="https://img.shields.io/badge/License-MIT-yellow.svg">](https://opensource.org/licenses/MIT)

  ## Table of Contents

  [Description](#description)<br />[Requirements](#requirements)<br />[Installation](#installation)<br />[Configuration](#configuration)<br />[Usage](#usage)<br />[Contributing](#contributing)<br />[Tests](#tests)<br />[License](#license)<br />[Questions](#questions)<br />

  ## Description

 Module for the [Processwire CMS](https://processwire.com). Creates image variations and renders markup for HTML5 image srcsets.

  ## Requirements

In order to use LazyResponsiveImages, your site will need to be running on [Processwire](https://processwire.com). The module also requires [Andrea Verlicchi](https://github.com/verlok)'s [vanilla-lazyload script](https://github.com/verlok/vanilla-lazyload).
  
  ## Installation
  
  Having installed the latest version of [Processwire](https://processwire.com), download [lazyload.esm.min.js](https://github.com/verlok/vanilla-lazyload) and copy to the /site/templates/js/vendor directory. Also download the LazyResponsiveImages folder and place in your /site/modules directory.<br /><br />Log into your site as an admin and go the Modules page. Select the Site tab and click the Install button on the LazyResponsiveImages module entry.

  ## Configuration

  - **Image widths** - enter image field name with comma-separated list of the sizes required for use in related srcsets. Use ampersand to separate field entries:<br /> ```product_shot=100,200,300&hero=1920,1500,960```<br /> The module will generate the correct variations each time an image is uploaded to an included field.
  - **Number of eager load images to use in each context** - ampersand separated key value pairs:<br />```home=4&subcat=6```<br />
  The keys are simply identifiers allowing for more granular image settings. If, for example, you wanted only four eager loading images on your home page, you could create a "home" context by including ```home=4``` in this field. You could then pass this context name as an argument to the module's ```getMaxEager``` method to retrieve the value.
  - **Width of image to use as src for older browsers** - ampersand separated key value pairs:<br />```home=1200&subcat=800```<br />
  These sizes will be used for legacy browsers that don't support srcset
  
  ## Usage
  Load the module in your php file<br />```$lazyImages = $modules->get("LazyResponsiveImages");```<br /><br />Retrieve the max_eager value for the current context if you have provided this in [the module settings](#configuration). Use this to set an appropriate boolean value for the the lazy_load option.<br /><br />Configure the options array for your image:<br />
 - **alt_str** -  image alt attribute
 - **class** -  image class attribute
 - **context** - use if you have set *Number of eager load images to use in each context* as described in [Configuration](#configuration)
 - **image** - either
    - a reference to a Processwire image field:<br />
    ```$page->product_shot->first();```
    - or, if providing several art directed images for different breakpoints, an array whose keys are the names of fields configured in [the module settings](#configuration) and whose values are options arrays:
<br />
<br />

        ```php
        [
            "hero_image" => [
                "image" => $page->hero_image->first(),
                "media" => "(min-width: 650px)",
                "sizes" => "(min-width: 1200px) 1130px, (min-width: 660px) 100vw"
            ],
            "hero_image_narrow" => [
                "image" => $page->hero_image_narrow->first(),
                "media" => "(max-width: 649px)",
                "sizes" => "100vw"
            ]
        ]
        ```

 - **sizes** - string to be used directly in the "sizes" attribute of the img tag:<br />
 ```(max-width: 600px) 480px, 800px```
 - **product_data_attributes** - string of data attributes to add to the img tag:<br />
 ```data-day='1' data-month='jan'```
 - **extra_attributes** - string of additional attributes to add to the image tag - note that these should be valid HTML5 attributes:<br />
 ```crossorigin='use-credentials' decoding='async'```
 - **lazy_load** - boolean<br />Note: you can use <br />
 ```$lazyImages->getMaxEager($context);```<br />if you've set it up for the current context. This will allow you to determine when to switch to lazy loading (see [Configuration](#configuration))
 - **webp** - boolean<br />

 Then render your image with<br />
  ```$lazyImages->renderImage($options);```
    
  ## Contributing
  
  If you would like to make a contribution to the app, simply fork the repository and submit a Pull Request. If I like it, I may include it in the codebase.
  
  ## Tests
  
  N/A
  
  ## License
  
  Released under the [MIT](https://opensource.org/licenses/MIT) license.
  
  ## Questions
  
  Feel free to [email me](mailto:paul@primitive.co?subject=LazyResponsiveImages%20query%20from%20GitHub) with any queries.