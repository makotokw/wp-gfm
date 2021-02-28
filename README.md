# GitHub Flavored Markdown for WordPress

``wp-gfm`` is the WordPress plugin that convert from GitHub Flavored Markdown by using the ``PHP-Markdown``.

## Dependencies

 * WordPress 3.1+
 * PHP 5.5+

## Installation

Download from https://github.com/makotokw/wp-gfm/releases and upload to /path/to/wp-content/plugins/wp-gfm

## PHP-Markdown (Recommended)

This conversion depends on [PHP Markdown Lib 1.9.0](http://michelf.ca/projects/php-markdown/).

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

## Result

![Result](https://raw.githubusercontent.com/makotokw/wp-gfm/master/screenshot-1.png)


## Development

```
npm install
npm run start
```

## LICENSE

The MIT License

## Current Version

The line below is used for the updater API, please leave it untouched unless bumping the version up :)

~Current Version:0.11~
