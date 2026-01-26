// Initialize AdminLTE pushmenu and enable dropdowns
document.addEventListener('DOMContentLoaded', function() {
  try {
    // Enable sidebar toggle (pushmenu)
    const pushToggles = document.querySelectorAll('[data-widget="pushmenu"]');
    pushToggles.forEach(btn => btn.addEventListener('click', function(e) {
      e.preventDefault();
      document.body.classList.toggle('sidebar-collapse');
    }));

    // Enable Bootstrap dropdowns where necessary
    var dropdownTriggerList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    dropdownTriggerList.forEach(function (dropdownToggleEl) {
      try { new bootstrap.Dropdown(dropdownToggleEl); } catch (err) {}
    });
  } catch (err) {
    console.error('scripts init error', err);
  }
});

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
