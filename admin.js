(function($) {
    if (typeof $ == "undefined") return;
    $(document).ready(function(){
        var i = edButtons.length;
        edButtons[i] = new edButton(
                'ed_markdown',
                'p',
                '[markdown]\n',
                '[/markdown]\n',
                'p');
        var $button = $('<input/>')
            .attr({type:'button',
                id: edButtons[i].id,
                className: 'ed_button',
                value: 'markdown',
                title: 'Markdown'
            }).click(function(e) {
                edInsertTag(edCanvas, i);
            })
            ;
        $('#ed_code').after($button);
    });
})(jQuery)
