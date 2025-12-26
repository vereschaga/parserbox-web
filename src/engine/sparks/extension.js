var plugin = {

    hosts: {
        'marksandspencer.com': true,
        'www.marksandspencer.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.marksandspencer.com/MSResLogin?langId=-24&storeId=10151';
    },

    start: function (params) {
        browserAPI.log('start');
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
                        plugin.logout(params);
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
        browserAPI.log('isLoggedIn');
        if ($('#loginForm:visible, form:has(#usernameInput):visible').length > 0) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if (!$('a:contains("Sign in"):visible', 'span.sign-link').length || '' != util.trim($('#headerWelcomeMsg').text())) {
            browserAPI.log('isLoggedInd: true');
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        if ('undefined' != typeof account.properties && 'undefined' != typeof account.properties.Name)
            return ($('#headerWelcomeMsg:contains("' + account.properties.Name.split(' ')[0] + '")').length ? true : false);
        return false;
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = $('a[href*="/Logoff"]', 'li.logout').attr('href');
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log('login');
        const form = $('#loginForm, form:has(#usernameInput)');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('#loginEmail, #usernameInput').val(params.account.login);
        form.find('#loginPassword, #passwordInput').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button[class *= "__signIn-btn"], button#submitButton').get(0).click();
        });
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        let error = $('p.address-overlay__bfpo-msg');

        if (error.length === 0) {
            error = $('div.fielditem__msg--error, div.my-account__error-msg');
        }

        if (error.length && '' !== util.filter(error.text())) {
            provider.setError(util.filter(error.text()));
            return;
        }

        provider.complete();
    }

};