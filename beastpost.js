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

  // Function to download post content as a text file
  function downloadPost(content) {
    var blob = new Blob([content], { type: "text/plain" });
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "generated_post.txt";
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
  $("#beastpost-create-button").on("click", async function (e) {
    e.preventDefault();
    const subject = $("#beastpost-input").val();
    if (subject === "") {
      alert("Please enter a topic for the post.");
      return;
    }
    const post_id = $("#post_ID").val();

    // Show content generation progress
    if ($("#beastpost-progress").length === 0) {
      $("#beastpost-container").append(
        '<div id="beastpost-progress" style="margin-top:10px; color: #0073aa;">' +
          '<span class="spinner is-active" style="float:none; margin-right:10px;"></span>' +
          '<span id="beastpost-status">Generating post content...</span></div>'
      );
    }

    try {
      // Disable inputs
      $("#beastpost-create-button").prop("disabled", true);
      $("#beastpost-input").prop("disabled", true);

      // First request: Get post content
      const contentResponse = await $.post(beastpost.ajax_url, {
        action: "beastpost_create_post",
        mode: "content",
        subject: subject,
        post_id: post_id,
        nonce: beastpost.nonce,
      });

      if (!contentResponse.success) {
        throw new Error(contentResponse.data);
      }

      // Update editor with content
      if (
        typeof wp !== "undefined" &&
        wp.data &&
        wp.data.dispatch("core/editor")
      ) {
        wp.data.dispatch("core/editor").editPost({
          title: contentResponse.data.title,
          content: contentResponse.data.content,
        });
        wp.data
          .dispatch("core/editor")
          .resetBlocks(wp.blocks.parse(contentResponse.data.content));
      }

      // Update SEO link
      $("#editable-post-name").val(contentResponse.data.seo_link);

      // Update progress status
      $("#beastpost-status").text("Setting featured image...");

      // Second request: Get and set featured image
      const imageResponse = await $.post(beastpost.ajax_url, {
        action: "beastpost_create_post",
        mode: "image",
        post_id: post_id,
        image_description: contentResponse.data.image_description,
        nonce: beastpost.nonce,
      });

      if (!imageResponse.success) {
        throw new Error(imageResponse.data);
      }

      // Update featured image in Gutenberg editor
      if (
        typeof wp !== "undefined" &&
        wp.data &&
        wp.data.dispatch("core/editor")
      ) {
        wp.data.dispatch("core/editor").editPost({
          featured_media: imageResponse.data.image_id,
        });
      }

      // Add download button
      $("#beastpost-container").append(
        '<button type="button" id="beastpost-download-post" class="button" style="margin-top:10px;">Download Post</button>'
      );

      // Add click handler for download button
      $("#beastpost-download-post").on("click", function () {
        downloadPost(contentResponse.data.content);
      });

      // Show success message
      alert("Post created successfully!");
    } catch (error) {
      const errorText =
        `Error occurred at ${new Date().toISOString()}\n\n` +
        `Error Details: ${error.message}\n\n`;
      showErrorModal(errorText);
    } finally {
      // Clean up
      $("#beastpost-progress").remove();
      $("#beastpost-create-button").prop("disabled", false);
      $("#beastpost-input").prop("disabled", false);
    }
  });
});
