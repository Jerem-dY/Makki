

$("#export_data_select").on("change", function(){
    $("#export_data_btn").attr("href", $("#export_data_btn").attr("data-url") + "&mime=" + $("#export_data_select").val());
});


window.onscroll = function() {
    if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
        $("#scroll_top_btn").css("display", "block");
    } else {
        $("#scroll_top_btn").css("display", "none");
    }
};

$("#scroll_top_btn").on("click", function(){
    document.body.scrollTop = 0;
    document.documentElement.scrollTop = 0;
});