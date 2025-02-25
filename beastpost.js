jQuery(document).ready(function ($) {
  // Function to show the error modal.
  function showErrorModal(errorText) {
    // Create modal if it doesn't exist.
    if ($("#beastpost-error-modal").length === 0) {
      $("body").append(
        '<div id="beastpost-error-modal" style="position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center;">' +
          '<div style="background:#fff; padding:20px; max-width:600px; width:90%; box-shadow:0 0 10px rgba(0,0,0,0.5);">' +
          "<h2>Error</h2>" +
          '<pre id="beastpost-error-text" style="white-space: pre-wrap; word-wrap: break-word; background:#f7f7f7; padding:10px; border:1px solid #ccc; max-height:300px; overflow:auto;"></pre>' +
          '<button id="beastpost-download-error" class="button">Download Error Details</button> ' +
          '<button id="beastpost-close-error" class="button">Close</button>' +
          "</div>" +
          "</div>"
      );
      $("#beastpost-download-error").on("click", function () {
        downloadError($("#beastpost-error-text").text());
      });
      $("#beastpost-close-error").on("click", function () {
        $("#beastpost-error-modal").remove();
      });
    }
    $("#beastpost-error-text").text(errorText);
  }

  // Function to download error details as a text file.
  function downloadError(errorText) {
    var blob = new Blob([errorText], { type: "text/plain" });
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "beastpost_error.txt";
    link.click();
  }

  // Update API key when the pencil icon is clicked.
  $(".beastpost-edit-key").on("click", function (e) {
    e.preventDefault();
    var keyType = $(this).data("key-type");
    var newKey = prompt("Enter your " + keyType + " API key:");
    if (newKey) {
      $.post(
        beastpost.ajax_url,
        {
          action: "beastpost_update_key",
          key_type: keyType,
          key_value: newKey,
          nonce: beastpost.nonce,
        },
        function (response) {
          if (response.success) {
            location.reload();
          } else {
            alert("Error updating key: " + response.data);
          }
        }
      );
    }
  });

  // Handle the Create Post button click.
  $("#beastpost-create-button").on("click", function (e) {
    e.preventDefault();
    var subject = $("#beastpost-input").val();
    if (subject === "") {
      alert("Please enter a subject.");
      return;
    }
    var post_id = $("#post_ID").val();

    // Show progress indicator.
    if ($("#beastpost-progress").length === 0) {
      $("#beastpost-container").append(
        '<div id="beastpost-progress" style="margin-top:10px; color: #0073aa;">Creating post, please wait...</div>'
      );
    }
    $.post(
      beastpost.ajax_url,
      {
        action: "beastpost_create_post",
        subject: subject,
        post_id: post_id,
        nonce: beastpost.nonce,
      },
      function (response) {
        $("#beastpost-progress").remove();
        if (response.success) {
          alert(response.data.message);
          $("#editable-post-name").val(response.data.seo_link);
        } else {
          showErrorModal(response.data);
        }
      }
    ).fail(function (xhr, status, error) {
      $("#beastpost-progress").remove();
      var fullError =
        "AJAX error: " + status + "\n" + error + "\n" + xhr.responseText;
      showErrorModal(fullError);
    });
  });
});
