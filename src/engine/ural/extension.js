var plugin = {

    hosts: {'www.uralairlines.ru': true},

    getStartingUrl: function (params) {
        return 'https://www.uralairlines.ru/cabinet/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
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
                        plugin.logout(params);
                } else
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
        if ($('#logout_submit').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#login_cabinet_from_id').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $('div[name = "form_auth"]').find('div.myinfo__card').text();
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber !== '')
            && number
            && (number === account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('button.private_cabinet_new__btn').get(0).click();
            util.waitFor({
                selector: '#logout_submit',
                timeout: 10,
                success: function () {
                    $('#logout_submit').get(0).click();
                }
            });
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#login_cabinet_from_id');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);

            // vue.js
            provider.eval(
                'function createNewEvent(eventName) {' +
                'var event;' +
                'if (typeof(Event) === "function") {' +
                '    event = new Event(eventName);' +
                '} else {' +
                '    event = document.createEvent("Event");' +
                '    event.initEvent(eventName, true, true);' +
                '}' +
                'return event;' +
                '}' +
                'var email = document.querySelector(\'input[name="username"]\');' +
                'email.dispatchEvent(createNewEvent(\'input\'));' +
                'email.dispatchEvent(createNewEvent(\'change\'));' +
                'var pass = document.querySelector(\'input[name="password"]\');' +
                'pass.dispatchEvent(createNewEvent(\'input\'));' +
                'pass.dispatchEvent(createNewEvent(\'change\'));'
            );
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#auth_submit_cabinet').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000);
            });
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('.uk-notification');
        if (errors.length === 0) {
            errors = $('.uan-field__error');
        }
        if (errors.length > 0)
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    }
};
