# GitHub Flavored Markdown for WordPress

wp-gfm is the WordPress plugin that convert from GitHub Flavored Markdown by using the PHP-Markdown or [GitHub Render API](http://developer.github.com/v3/markdown/).

## Dependencies

 * WordPress 3.1+
 * PHP 5.3+
 * Optional: Render API: [GitHub Render API](http://developer.github.com/v3/markdown/) or https://github.com/makotokw/ruby-markdown-render-api

## How to work

The plugin has two conversions.

``[markdown]`` as shortcode for PHP-Markdown, convert by using ``\Michelf\Markdown`` class **inside WordPress**.

``[gfm]`` as shortcode for GitHub Flavored Markdown, convert by using the Render API **outside WordPress**. Default Render API is GitHub Render API, limits requests to 60 per hour for unauthenticated requests. Alternatives Render API that works on heroku is here: https://github.com/makotokw/ruby-markdown-render-api


## Installation

Download from https://github.com/makotokw/wp-gfm/releases and upload to /path/to/wp-content/plugins/wp-gfm

## PHP-Markdown (Recommended)

This conversion depends on [PHP Markdown Lib 1.7.0](http://michelf.ca/projects/php-markdown/).

### Usage

Use ``[markdown][/markdown]`` as shortcode on entry.

Example:

    [markdown]
    | First Header  | Second Header |
    | ------------- | ------------- |
    | Content Cell  | Content Cell  |
    | Content Cell  | Content Cell  |
    [/markdown]

#### Fenced code blocks

Example:

    [markdown]
    ```ruby
    require 'redcarpet'
    markdown = Redcarpet.new("Hello World!")
    puts markdown.to_html
    ```
    [/markdown]

#### Table of content

left aligned toc.

    [TOC]

right aligned toc.

    [>TOC]

Example:

```
[markdown]
# headLineOne

## something

[TOC]

## something more
[/markdown]
```

#### Embed content

Supported embed markdown file by ``[embed_markdown]`` shortcode.

```
[embed_markdown url="https://raw.githubusercontent.com/makotokw/wp-gfm/master/README.md"]
```

### Option

 Admin > Settings > WP GFM

* AutoLink (default: no)
* Code block template
 * (default: ``<pre class="prettyprint lang-{{lang}}" title="{{title}}">{{codeblock}}</pre>`` )

```
<pre class="prettyprint lang-ruby">require 'redcarpet'
markdown = Redcarpet.new("Hello World!")
puts markdown.to_html
</pre>
```

You can use [google-code-prettify](https://code.google.com/p/google-code-prettify/) if you want to allow syntax highlighting.

## GitHub Render API

This way is not good. If there are 5 shortcodes in page, the plugin require 5 HTTP requests.

### Setup

 * Open ``WP GFM`` Settings and set ``Render URL``


### Usage

Use ``[gfm][/gfm]`` as shortcode on entry.

    [gfm]
    ```ruby
    require 'redcarpet'
    markdown = Redcarpet.new("Hello World!")
    puts markdown.to_html
    ```
    [/gfm]


## Result

![Result](https://raw.githubusercontent.com/makotokw/wp-gfm/master/screenshot-1.png)


## Development

```
npm install
grunt debug
```

## LICENSE

The MIT License

## Current Version

The line below is used for the updater API, please leave it untouched unless bumping the version up :)

~Current Version:0.9~
