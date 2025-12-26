var plugin = {
    //keepTabOpen: true,
    hosts : {
        'flygbra.se'     : true,
        'www.flygbra.se' : true
    },

    getStartingUrl : function (params) {
        return 'https://www.flygbra.se/en';
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
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

    isLoggedIn : function () {
        browserAPI.log('isLoggedIn');
        if ($('.navbar-main__links-link:contains("Log in / Sign up")').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if ($('#logout:contains("Sign out")').length) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        return null;
    },

    isSameAccount : function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.trim($('li:contains("Member number ")').next('li').text());
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
        && (typeof(account.properties.MemberNumber) != 'undefined')
        && (account.properties.MemberNumber != '')
        && (number == account.properties.MemberNumber));
    },

    logout : function () {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            var logout = $('#logout:contains("Sign out")');
            if (logout.length)
                logout.get(0).click();
        });
    },

    login : function (params) {
        browserAPI.log('login');
        var loginForm = $('.navbar-main__links-link:contains("Log in / Sign up")');
        if (loginForm.length > 0)
            loginForm.get(0).click();
        setTimeout(function () {
            var form = $('.navbar-main__links-login__modal form');
            if (form.length) {
                $('input[name="userName"]', form).val(params.account.login);
                $('input[name="password"]', form).val(params.account.password);

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
                    '}'+
                    'var email = document.querySelector(\'input[name="userName"]\');' +
                    'email.dispatchEvent(createNewEvent(\'input\')); email.dispatchEvent(createNewEvent(\'change\'));' +
                    'var pass = document.querySelector(\'input[name="password"]\');' +
                    'pass.dispatchEvent(createNewEvent(\'input\')); pass.dispatchEvent(createNewEvent(\'change\'));'
                );

                provider.setNextStep('checkLoginErrors', function () {
                    browserAPI.log('login: submit');
                    setTimeout(function () {
                        var form = $('.navbar-main__links-login__modal form');
                        $('button[value="Submit"]', form).get(0).click();
                        setTimeout(function () {
                            plugin.checkLoginErrors();
                        }, 4000);
                    },1000);
                });

            } else {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        }, 2000);

    },

    checkLoginErrors : function () {
        browserAPI.log('checkLoginErrors');
        var $errors = $('p:contains("Invalid username or password")', '#login-form-error-messages');
        if ($errors.length) {
            provider.setError($errors.text());
        } else {
            provider.complete();
        }
    }

};