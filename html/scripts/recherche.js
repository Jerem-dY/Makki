function update_row(){
  var btn_ajt = $(this).parents(".top").find(".ajout");

  if ($(this).val() == "") {
    $(btn_ajt).addClass("hidden");
  }
  else {
    $(btn_ajt).removeClass("hidden");
  }
}

$(document).on("keyup", ".champ", update_row);
$(document).on("change", ".champ", update_row);


$(".ajout").on("click", function(){
  var node = $(this).parent().find(".crit").last().clone();

  $(this).before(node);
});


$(document).on("click",".del", function(){
  var len = $(this).parents(".top").find(".crit").length;

  if (len > 1) {
    $(this).parent().remove();
  }
  else {
    $(this).parent().find("input").val('').change();
  }
});


$("#search-form").on("submit", function(event) {
  $("input[type=text]").each(function(i, e) {
    if ($(e).val() == "") {
      $(e).remove();
    }
  });
});