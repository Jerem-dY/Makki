$('#login-button').on("mouseenter", function() {
  $('.login-form').addClass('open');
});

$(".login-wrapper").on("mouseleave", function() {

  if (!$("#utilisateur, #mdp").is(":focus")) {
    $('.login-form').removeClass('open');
  }
});