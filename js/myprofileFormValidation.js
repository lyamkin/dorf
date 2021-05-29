$(document).ready(function () {
  // validate contact information form
  $("#contactEditForm").validate({
    rules: {
      name: {
        required: true,
        userNameValidator: true,
      },
      email: {
        required: true,
        email: true,
        remote: {
          url: "/isemailvalid",
          type: "post",
          data: {
            email: function () {
              return $("#email").val();
            },
          },
        },
      },
      postal: {
        required: true,
        postalCodeValidator: true,
      },
      phone: {
        required: true,
        phoneValidator: true,
      },
    },

    messages: {
      name: {
        required: "Name is required",
      },
      email: {
        required: "Email is required",
        email: "Please enter a valid email",
        remote: "Email already registered",
      },
      postal: {
        required: "Postal code is required",
      },
      phone: {
        required: "Phone is required",
      },
    },
    submitHandler: function (form) {
      form.submit();
    },
  });
  // validate password change form
  $("#passwordEditForm").validate({
    rules: {
      oldpassword: {
        required: true,
        minlength: 6,
        maxlength: 100,
        passwordValidator: true,
      },
      newpassword: {
        required: true,
        minlength: 6,
        maxlength: 100,
        passwordValidator: true,
      },
      newpasswordrepeat: {
        required: true,
        minlength: 6,
        maxlength: 100,
        equalTo: "#newpassword",
        passwordValidator: true,
      },
    },

    messages: {
      oldpassword: {
        required: "Old password is required",
      },
      newpassword: {
        required: "New password is required",
      },
      newpasswordrepeat: {
        required: "Re-enter the password",
        equalTo: "Re-entered password is not match to a new password",
      },
    },
    submitHandler: function (form) {
      form.submit();
    },
  });
  // validate image change form
  $("#imageEditForm").validate({
    rules: {
      image: {
        required: true,
      },
    },

    messages: {
      image: {
        required: "New picture required",
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
