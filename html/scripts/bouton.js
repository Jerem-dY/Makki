$('#login-button').on("mouseenter", function() {
  $('.login-form').addClass('open');
});

$(".login-wrapper").on("mouseleave", function() {

  if (!$("#utilisateur, #mdp").is(":focus")) {
    $('.login-form').removeClass('open');
  }
});

$('#login-button_connecte').on("mouseenter", function() {
  $('.login-form_connecte').addClass('open_connecte');
});

$(".login-wrapper_connecte").on("mouseleave", function() {

  if (!$("#bouton_admin, #bouton_deco").is(":focus")) {
    $('.login-form_connecte').removeClass('open_connecte');
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
