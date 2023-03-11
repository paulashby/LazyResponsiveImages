# LazyResponsiveImages

  [<img src="https://img.shields.io/badge/License-MIT-yellow.svg">](https://opensource.org/licenses/MIT)

  ## Table of Contents

  [Description](#description)<br />[Installation](#installation)<br />[Usage](#usage)<br />[Contributing](#contributing)<br />[Tests](#tests)<br />[License](#license)<br />[Questions](#questions)<br />

  ## Description

  Processwire module. Creates image variations and renders markup for HTML5 image srcsets.
  
  ## Installation
  
  Firstly, download and install the latest version of [Processwire](https://processwire.com). Download the LazyResponsiveImages folder and place in your /site/modules directory.<br /><br />Log into your site as an admin and go the Modules page. Select the Site tab and click the Install button on the LazyResponsiveImages module entry.

  The configuration page makes use of contexts. These are nothing more than identifiers allowing for more granular image settings. If, for example, you wanted only four eager loading images on your home page, you could create a "home" context by including "home=4" in the *Number of eager load images to use in each context* field. You could then pass this context name as an argument to the module's ```getMaxEager``` method to retrieve the value. With that in mind, configuration options are as follows:
  - **Image widths** - enter image field name with comma-separated list of the sizes required for use in related srcsets. Use ampersand to separate field entries:<br />eg "product_shot=100,200,300&hero=1920,1500,960"<br /> The module will generate the correct variations each time an image is uploaded to an included field.
  - **Number of eager load images to use in each context** - ampersand separated key value pairs:<br />eg "home=4&subcat=6"
  - **Width of image to use as src for older browsers** - ampersand separated key value pairs:<br />eg "home=1200&subcat=800"<br />
  These sizes will be used for legacy browsers that don't support srcset
  
  ## Usage
  Load the module in your php file<br />```$lazyImages = $modules->get("LazyResponsiveImages");```<br /><br />Retrieve the max_eager value for the current context if you have provided this in the module settings (see [Installation](#installation)). Use this to set an appropriate boolean value for the the lazy_load option.<br /><br />Configure the options array for your image:<br />
 - **alt_str** -  image alt attribute
 - **class** -  image class attribute
 - **context** - use if you have set fallbacks for older browsers as described in [Installation](#installation)
 - **image** - reference to a Processwire image field<br />
 eg ```$page->product_shot->first();```
 - **sizes** - string to be used directly in the "sizes" attribute of the img tag<br />
 eg "(max-width: 600px) 480px, 800px"
 - **product_data_attributes** - string of data attributes to add to the img tag<br />
 eg "data-day='1' data-month='jan'"
 - **extra_attributes** - string of additional attributes to add to the image tag - note that these should be valid HTML5 attributes<br />
 eg "crossorigin='use-credentials' decoding='async'"
 - **lazy_load** - boolean<br />Note: you can use <br />
 ```$lazyImages->getMaxEager($context);```<br />if you've set it up for the current context. This will allow you to determine when to switch to lazy loading (see [Installation](#installation))
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