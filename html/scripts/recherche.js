$(".champ").on("keyup", function(){
  var btn_ajt = $(this).parents(".top").find(".ajout");

  if ($(this).val() == "") {
    $(btn_ajt).addClass("hidden");
  }
  else {
    $(btn_ajt).removeClass("hidden");
  }
});


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
    $(this).parent().find("input").val('');
  }
});


$("#search-form").on("submit", function(event) {
  $("input[type=text]").each(function(i, e) {
    if ($(e).val() == "") {
      $(e).remove();
    }
  });
});