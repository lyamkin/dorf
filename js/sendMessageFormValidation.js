$(document).ready(function () {
  // validate contact information form
  $("#sendMessageForm").validate({
    rules: {
      message: {
        required: true,
      },
    },
    submitHandler: function (form) {
      form.submit();
    },
  });

  // display file name in the input file field
  $("#image").on("change", function () {
    // get the file name
    let fileName = $(this).val();
    // replace the "Choose a file" label
    $(this).next(".custom-file-label").html(fileName);
  });
});
