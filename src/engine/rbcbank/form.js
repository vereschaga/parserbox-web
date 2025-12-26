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
                case 'USA':
                    form.setFieldCaption("login", "User Name");
                    break;
                case 'Caribbean':
                    form.setFieldCaption("login", "Username");
                    break;
                default:
                    form.setFieldCaption("login", "Client Card or Username");
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

