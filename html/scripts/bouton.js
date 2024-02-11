$('#login-button').on("mouseenter", function() {
  $('.login-form').addClass('open');
});

$(".login-wrapper").on("mouseleave", function() {

  if (!$("#utilisateur, #mdp").is(":focus")) {
    $('.login-form').removeClass('open');
  }
});

$(document).ready(function() {
  $('*').each(function(index, element) {
    $(element).addClass("loaded");
  });
});

$("form.login-form").on("submit", function(event) {
  $("#utilisateur").val( window.btoa(encodeURIComponent($("#utilisateur").val())) );
  $("#mdp").val( window.btoa(encodeURIComponent($("#mdp").val())) );
});
