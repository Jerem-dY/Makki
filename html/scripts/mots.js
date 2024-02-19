

$("#export_data_select").on("change", function(){
    $("#export_data_btn").attr("href", $("#export_data_btn").attr("data-url") + "&mime=" + $("#export_data_select").val());
});