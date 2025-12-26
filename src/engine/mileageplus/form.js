/**
 * @param {AbstractFormExtension} extension
 */
function addFormExtension(extension) {
    var listQuestions = {}, selectAnswer;

    function isSubmitted(form, count) {
        var fields = ['unitedquest', 'unitedanswer'];
        for (var i = 1; i <= count; i++) {
            for (var j = 0; j < fields.length; j++) {
                if (form.getValue(fields[j] + i) !== '') return true;
            }
        }

        return false;
    }

    /**
     * will be called on field change
     * @param {FormInterface} form
     * @param {string} fieldName in lower case
     */
    extension.onFieldChange = function(form, fieldName) {
        if (0 === fieldName.indexOf('unitedquest')) {
            var key = form.getValue(fieldName), options = [],
                answers = fieldName.replace('quest', 'answer');

            if ('' == key || '0' == key || 'undefined' === typeof listQuestions[key]) {
                return form.setOptions(answers, selectAnswer);
            }

            for (var i in listQuestions[key].items)
                options.push({value : i, label : listQuestions[key].items[i]});
            form.setOptions(answers, options);
        }
    };

    /**
     * will be called when form loaded and ready
     * @param {FormInterface} form
     * @param {string} fieldName in lower case
     */
    extension.onFormReady = function(form, fieldName) {
        listQuestions = JSON.parse(form.getValue('_questions'));
        if ('object' === typeof listQuestions && null !== listQuestions) {
            var i, options,
                count  = 5, // count questions
                stored = JSON.parse(form.getValue('_stored')),
                submitted = isSubmitted(form, count);

            selectAnswer = [{value : '0', label: listQuestions[Object.keys(listQuestions)[1]].items['0']}];
            for (i = 1; i <= count; i++) {
                options = [];
                for (var k in listQuestions)
                    options.push({value : listQuestions[k].value, label : listQuestions[k].label});
                form.setOptions('unitedquest' + i, options);

                if (submitted) {
                    this.onFieldChange(form, 'unitedquest' + i);
                } else {
                    form.setOptions('unitedanswer' + i, selectAnswer);
                }
            }

            if (!submitted) {
                i = 0;
                for (k in stored) {
                    form.setValue('unitedquest' + (++i), k);
                    this.onFieldChange(form, 'unitedquest' + i);
                    form.setValue('unitedanswer' + i, stored[k]);
                }
            }
        }
    };

}
