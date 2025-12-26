var plugin = {

    hosts: {'www.norwegian.com': true},

    getStartingUrl: function (params) {
        return 'https://www.norwegian.com/uk/my-travels/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    start: function(params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (plugin.isLoggedIn()) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }
            if (counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if ($('a[href *= "logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('.nas-element-login-box form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        //var number = util.findRegExp( , //i);
        //browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            //&& (number == account.properties.Number));
            && ($('li:contains("' + account.properties.Number +'")').length > 0));
    },

    logout: function (params) {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href *= "logout"]').get(0).click();
        });
    },

    login: function(params){
        browserAPI.log("login");
        let form = $('.nas-element-login-box form:visible');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        /*
        form.find('input[name = "username"]').val(params.account.login);
        form.find('input[name = "password"]').val(params.account.password);
        */
        // reactjs
        provider.eval(
            "function triggerInput(selector, enteredValue) {\n" +
            "      let input = document.querySelector(selector);\n" +
            "      input.dispatchEvent(new Event('focus'));\n" +
            "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
            "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
            "      nativeInputValueSetter.call(input, enteredValue);\n" +
            "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
            "      input.dispatchEvent(inputEvent);\n" +
            "}\n" +
            "triggerInput('#nas-element-login-box-0-username', '" + params.account.login + "');\n" +
            "triggerInput('#nas-element-login-box-0-password', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function() {
            form.find('#nas-element-login-box-0-login-button').get(0).click();
            setTimeout(function() {
                plugin.checkLoginErrors(params);
            }, 5000)
        });
    },

    checkLoginErrors: function(params) {
        browserAPI.log("checkLoginErrors");
        let error = $('div.nas-info__content--error:visible li');
        if (error.length > 0) {
            provider.setError(error.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log('loginComplete');
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            //provider.setNextStep('toItineraries');
            //document.location.href = 'https://www.norwegian.com/ssl/uk/my-travels/#/mytravels';
            plugin.toItineraries(params);
            return;
        }

        plugin.itLoginComplete(params);
    },

    toItineraries: function(params) {
        browserAPI.log('toItineraries');
        let counter = 0;
        let confNo = params.account.properties.confirmationNumber;
        let toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let link = $('p:contains("Booking reference:") a[href *= "pnr=' + params.account.properties.confirmationNumber + '"]');
            if (link.length > 0) {
                clearInterval(toItineraries);
                return provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                    plugin.itLoginComplete(params);
                });
            }
            if (counter > 30) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
