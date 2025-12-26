/**
 * @param {AbstractFormExtension} extension
 */
function addFormExtension(extension) {

  /**
   * will be called on field change
   * @param {FormInterface} form
   * @param {string} fieldName in lower case
   */
  extension.onFieldChange = function (form, fieldName) {
    // testprovider form.js injected
  };

  /**
   * will be called when form loaded and ready
   * @param {FormInterface} form
   * @param {string} fieldName in lower case
   */
  extension.onFormReady = function (form, fieldName) {};

}

