$(function () {
  // Enable tooltips everywhere
  $('[data-toggle="tooltip"]').tooltip();

  // Initialize any plugins or custom scripts
});

// Example AJAX form handling
$(document).on("submit", ".ajax-form", function (e) {
  e.preventDefault();
  const form = $(this);
  $.ajax({
    url: form.attr("action"),
    type: form.attr("method"),
    data: form.serialize(),
    success: function (response) {
      if (response.success) {
        toastr.success(response.message);
        if (response.redirect) {
          setTimeout(() => {
            window.location.href = response.redirect;
          }, 1500);
        }
      } else {
        toastr.error(response.message);
      }
    },
    error: function (xhr) {
      toastr.error("An error occurred. Please try again.");
    },
  });
});
