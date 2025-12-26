/**
 * @param {AbstractFormExtension} extension
 */
function addFormExtension(extension){

    /**
     * will be called on field change
     * @param {FormInterface} form
     * @param {string} fieldName in lower case
     */
    extension.onFieldChange = function (form, fieldName){
        if(fieldName == 'login3'){
          var visible = ['GermanyNew', 'India', 'Italy', 'Mexico'].indexOf(form.getValue('login3')) === -1;
          form.showField("login2", visible);
          form.requireField("login2", visible);
        }
    };

    /**
     * will be called when form loaded and ready
     * @param {FormInterface} form
     * @param {string} fieldName in lower case
     */
    extension.onFormReady = function(form, fieldName){
        this.onFieldChange(form, 'login3');
    };

}

