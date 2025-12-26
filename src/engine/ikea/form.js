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
        if (fieldName == 'login2') {
            switch (form.getValue('login2')) {
                case 'Singapore':
                    form.setFieldCaption("login", "Card #");
                    break;
                default:
                    form.setFieldCaption("login", "Email / Phone Number");
                    break;
            }
        }
    };

    /**
     * will be called when form loaded and ready
     * @param {FormInterface} form
     * @param {string} fieldName in lower case
     */
    extension.onFormReady = function (form, fieldName) {
        this.onFieldChange(form, 'login2');
    };

}

