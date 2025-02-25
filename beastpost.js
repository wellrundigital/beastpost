jQuery(document).ready(function ($) {
  // Update OpenAI API key when link is clicked.
  $("#beastpost-openai-link").on("click", function (e) {
    e.preventDefault();
    var newKey = prompt("Enter your OpenAI API key:");
    if (newKey) {
      $.post(
        beastpost.ajax_url,
        {
          action: "beastpost_update_key",
          key_type: "openai",
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

  // Update Pexels API key when link is clicked.
  $("#beastpost-pexels-link").on("click", function (e) {
    e.preventDefault();
    var newKey = prompt("Enter your Pexels API key:");
    if (newKey) {
      $.post(
        beastpost.ajax_url,
        {
          action: "beastpost_update_key",
          key_type: "pexels",
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
    // Retrieve the current post ID from the hidden field.
    var post_id = $("#post_ID").val();

    $.post(
      beastpost.ajax_url,
      {
        action: "beastpost_create_post",
        subject: subject,
        post_id: post_id,
        nonce: beastpost.nonce,
      },
      function (response) {
        if (response.success) {
          alert(response.data.message);
          // Update the permalink field with the SEO optimized link.
          $("#editable-post-name").val(response.data.seo_link);
        } else {
          alert("Error: " + response.data);
        }
      }
    );
  });
});
