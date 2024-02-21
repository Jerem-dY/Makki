

$(".delete_trad").click(function() {

    var form = $(this);

    $.ajax({
        type: 'DELETE',
        url: form.attr('data-url'),
        data: {
            "date" : form.attr('data-date'),
            "file" : form.attr('data-file'),
            "lang" : form.attr('data-lang'),
            "type" : 'delete_trad',
            "nonce" : $('html').attr('data-nonce')
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



$(".delete_data, .delete_contact").click(function() {

    var form = $(this);
    var className = this.className.match(/delete_[\w]+/)[0];

    $.ajax({
        type: 'DELETE',
        url: form.attr('data-url'),
        data: {
            file: form.attr('data-file'),
            type: className,
            nonce: $('html').attr('data-nonce')
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