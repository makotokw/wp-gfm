# GitHub Flavored Markdown for WordPress

wp-gfm is the WordPress plugin that converts from GitHub Flavored Markdown by using the PHP-Markdown.

## Dependencies

 * WordPress 5.0+
 * PHP 7.4+

## How to work

``[markdown]`` as shortcode for PHP-Markdown, convert by using ``\Michelf\Markdown`` class **inside WordPress**.

## Installation

Download from https://github.com/makotokw/wp-gfm/releases and upload to /path/to/wp-content/plugins/wp-gfm

## PHP-Markdown (Recommended)

This conversion depends on [PHP Markdown Lib 2.0.0](http://michelf.ca/projects/php-markdown/).

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

Supported an embed Markdown file by ``[embed_markdown]`` shortcode.

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

## LICENSE

The MIT License

## Current Version

The line below is used for the updater API, please leave it untouched unless bumping the version up :)

~Current Version:0.11~
