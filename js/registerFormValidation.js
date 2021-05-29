$(document).ready(function () {
  // validate contact information form
  $("#registerUserForm").validate({
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
      pass1: {
        required: true,
        minlength: 6,
        maxlength: 100,
        passwordValidator: true,
      },
      pass2: {
        required: true,
        minlength: 6,
        maxlength: 100,
        equalTo: "#pass1",
        passwordValidator: true,
      },
      postal: {
        required: true,
        postalCodeValidator: true,
      },
      phone: {
        required: true,
        phoneValidator: true,
      },
      image: {
        required: true,
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
      pass1: {
        required: "New password is required",
      },
      pass2: {
        required: "Re-enter the password",
        equalTo: "Re-entered password is not match to a new password",
      },
      postal: {
        required: "Postal code is required",
      },
      phone: {
        required: "Phone is required",
      },
      image: {
        required: "Picture required",
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
