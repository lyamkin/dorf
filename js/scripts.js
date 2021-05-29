// Validation custom methods
// Name validator
jQuery.validator.addMethod(
  "userNameValidator",
  function (value, element) {
    return (
      this.optional(element) || /^[a-zA-Z0-9\ \\._\'"-]{4,50}$/.test(value)
    );
  },
  "Name must be 4-50 characters long and consist of letters, digits, spaces, dots, underscores, apostrophies, or minus sign."
);
// Postal code validator
jQuery.validator.addMethod(
  "postalCodeValidator",
  function (value, element) {
    return (
      this.optional(element) ||
      /^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z][ -]?\d[ABCEGHJ-NPRSTV-Z]\d$/i.test(
        value
      )
    );
  },
  "Postal code must be formatted like so: A1B 2C3"
);

// Phone number validator
jQuery.validator.addMethod(
  "phoneValidator",
  function (value, element) {
    return (
      this.optional(element) ||
      /^(\+\d{1,2}\s)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}$/.test(value)
    );
  },
  "Phone number must be at least 10 digits long, including the area code."
);

// Password validator
jQuery.validator.addMethod(
  "passwordValidator",
  function (value, element) {
    return (
      this.optional(element) ||
      (/[A-Z]/.test(value) && /[a-z]/.test(value) && /[0-9]/.test(value))
    );
  },
  "Password must contains at least one uppercase, one lowercase, and one digit in it"
);

// Adv title validator
jQuery.validator.addMethod(
  "adTitleValidate",
  function (value, element) {
    return this.optional(element) || /^[a-zA-Z0-9 ]+$/.test(value);
  },
  "Title must consist of letters and digits."
);

// Adv description validator
jQuery.validator.addMethod(
  "adDescriptionValidate",
  function (value, element) {
    return this.optional(element) || /^[a-zA-Z0-9\ \._\'"!?%*,-]+$/.test(value);
  },
  "Description must  and consist of letters and digits and special characters (. _ '  \" ! - ? % * ,)."
);
