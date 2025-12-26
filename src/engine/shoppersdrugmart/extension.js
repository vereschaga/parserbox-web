var plugin = {

    hosts: {'www.shoppersdrugmart.ca': true, 'www1.shoppersdrugmart.ca': true},

    getStartingUrl: function (params) {
        return 'https://www1.shoppersdrugmart.ca/en/optimum-new/my-optimum';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout();
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href *= "Logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('div#OptLandForm').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('h3:contains("Optimum Card Number") + p:eq(0)').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a[href *= "Logout"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('div#OptLandForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            var login = util.findRegExp(params.account.login, /^603207(.+)/);
            if (login === null)
                login = params.account.login;
            form.find('input[name = "cn"]').val(login);
            form.find('input[name = "pwd"]').val(params.account.password).trigger('change');
            // refs #11326
            util.sendEvent(form.find('input[name = "pwd"]').get(0), 'input');
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function() {
                    var captcha = form.find('div.wg-ol-recaptcha-container:visible');
                    //browserAPI.log("waiting captcha -> " + captcha);
                    if (captcha && captcha.length > 0) {
                        var submitButton = form.find('input[type = "submit"]');
                        provider.reCaptchaMessage();
                        if (provider.isMobile) {
                            var fakeButton = submitButton.clone();
                            form.find('div.wg-ol-form-row:has(input[type = "submit"])').append(fakeButton);
                            submitButton.hide();
                            fakeButton.unbind('click mousedown mouseup tap tapend');
                            fakeButton.bind('click', function (event) {
                                event.preventDefault();
                                event.stopPropagation();
                                browserAPI.log("captcha entered by user");
                                provider.setNextStep('checkLoginErrors', function () {
                                    submitButton.click();
                                });
                            });
                        }
                        else
                            waiting();
                    }// if (captcha && captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        submitButton.click();
                        waiting();
                    }
                }, 2000)
            });

            function waiting() {
                browserAPI.log("waiting...");
                var counter = 0;
                var login = setInterval(function () {
                    browserAPI.log("waiting... " + counter);
                    var error = form.find('.wg-ol-invalid-field-message:visible:eq(0)');
                    if (error.length == 0)
                        error = form.find('p.wg-ol-row-instructions:visible');
                    if (error.length > 0 && util.filter(error.text()) != '') {
                        clearInterval(login);
                        if (error.text().indexOf('Please complete the reCAPTCH') === -1)
                            provider.setError(error.text(), true);
                        else
                            provider.setError([error.text(), util.errorCodes.providerError], true);
                    }// if (error.length > 0 && error.text().trim() != '')
                    if (counter > 120) {
                        clearInterval(login);
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }
                    counter++;
                }, 500);
            }
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('.wg-ol-invalid-field-message:visible:eq(0)');
        if (errors.length == 0)
            errors = $('p.wg-ol-row-instructions:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }

}