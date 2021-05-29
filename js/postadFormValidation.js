$(document).ready(function () {
  // validate contact information form
  $("#postAdForm").validate({
    rules: {
      title: {
        required: true,
        minlength: 6,
        maxlength: 250,
        adTitleValidate: true,
      },
      category: {
        required: true,
      },
      description: {
        required: true,
        minlength: 6,
        maxlength: 250,
        adDescriptionValidate: true,
      },
      price: {
        required: true,
        number: true,
      },
      image: {
        required: true,
      },
    },

    messages: {
      title: {
        required: "Title is required",
      },
      category: {
        required: "Category is required",
      },
      description: {
        required: "Description is required",
      },
      price: {
        required: "Price is required",
      },
      image: {
        required: "Image is required",
      },
    },
    submitHandler: function (form) {
      form.submit();
    },
  });
});
// display file name in the input file field
$("#image").on("change", function () {
  // get the file name
  let fileName = $(this).val();
  // replace the "Choose a file" label
  $(this).next(".custom-file-label").html(fileName);
});
