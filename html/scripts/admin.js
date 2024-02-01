

$(".delete_trad").click(function() {

    var form = $(this);

    $.ajax({
        type: 'DELETE',
        url: form.attr('data-url'),
        data: {
            "date" : form.attr('data-date'),
            "file" : form.attr('data-file'),
            "lang" : form.attr('data-lang'),
            "type" : 'delete_trad'
        },
        contentType: 'json',
        success: function(data){
            console.log('Submission was successful.');
            console.log(data);
            form.parents("tr").remove();
        },
        error: function(data){
            console.log('An error occurred.');
            console.log(data);
        },
    });
});