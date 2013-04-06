# Github Flavored Markdown for WordPress

wp-gfm is the WordPress plugin that convert from Github Flavored Markdown by using the [Github Render API](http://developer.github.com/v3/markdown/).

## Dependencies

 * WordPress
 * Git
 * PHP 5.3+
 * [Composer](http://getcomposer.org/)
 * Render API: [Github Render API](http://developer.github.com/v3/markdown/) or https://github.com/makotokw/ruby-markdown-render-api

## How to work

The plugin has two convertion. 

``[markdown]`` as shortcode for PHP-Markdown, convert by using ``\Michelf\Markdown`` class inside WordPress. 

``[gfm]`` as shortcode for Github Flavored Markdown, convert by using the Render API outside WordPress. Default Render API is Github Render API, limits requests to 60 per hour for unauthenticated requests. Alternatives Render API that works on heroku is here:
https://github.com/makotokw/ruby-markdown-render-api


## Installation

Use git

    cd /path/to/wp-content/plugins
    git clone git://github.com/makotokw/wp-gfm.git

## PHP-Markdown

### Setup

Use Composer.

    cd /path/to/wp-content/plugins/wp-gfm
    composer install

### Usage

Use ``[markdown][/markdown]`` as shortcode on entry.

    [markdown]
    | First Header  | Second Header |
    | ------------- | ------------- |
    | Content Cell  | Content Cell  |
    | Content Cell  | Content Cell  |
    [/markdown]

## Github Flavored Markdown

### Setup

    cd /path/to/wp-content/plugins/wp-gfm
    cp -p config.php.sample config.php

Edit config.php.

### Usage

Use ``[gfm][/gfm]`` as shortcode on entry.

    [gfm]
    # code
    
    ```ruby
    require 'redcarpet'
    markdown = Redcarpet.new("Hello World!")
    puts markdown.to_html
    ```
    [/gfm]

![Result](https://dl.dropbox.com/u/8932138/screenshot/wp-gfm/wp-gfm_2013-01-15_1927.png)

## LICENSE

The MIT License