# Github Flavored Markdown WordPress Plugin

The plugin uses the Github Render API ( http://developer.github.com/v3/markdown/ ) for converting. However, it limits requests to 60 per hour for unauthenticated requests.

Alternatives Render API that works on heroku is here:
https://github.com/makotokw/ruby-markdown-render-api

## Usage

### Entry

    [makrdown]
    # code
    
    ```ruby
    require 'redcarpet'
    markdown = Redcarpet.new("Hello World!")
    puts markdown.to_html
    ```
    [/makrdown]

### Result

![Result](https://dl.dropbox.com/u/8932138/screenshot/wp-gfm/wp-gfm_2013-01-15_1927.png)
