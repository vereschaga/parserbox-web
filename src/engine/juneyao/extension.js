var plugin = {

    hosts: {
        'global.juneyaoair.com': true
    },

    getStartingUrl: function (params) {
        return 'https://global.juneyaoair.com/u/flights/flights-order';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('span.loginText:contains("Login")').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('span.loginText:contains("Logout")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $("div.top-info-box").find("div.cardNo").text().trim();
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            &&  number
            && (number == account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        $('span.loginText:contains("Logout")').click();
      //  provider.setNextStep('start', function () {
            setTimeout(function () {
                $('button[type="button"] > span:contains("Confirm")').click();
                setTimeout(function () {
                    plugin.start(params);
                }, 500);
            }, 500);
      //  });
    },

    login: function (params) {
        browserAPI.log("login");
        $('div.user-box').find('span.loginText:contains("Login")').click();
        setTimeout(function () {
            var form = $('form.el-form');
            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            browserAPI.log("submitting saved credentials");
            form.find('input[type="text"]').val(params.account.login);
            form.find('input[type="password"]').val(params.account.password);

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
                'var email = document.querySelector(\'input.el-input__inner[type="text"]\');' +
                'email.dispatchEvent(createNewEvent(\'input\')); email.dispatchEvent(createNewEvent(\'change\'));' +
                'var pass = document.querySelector(\'input.el-input__inner[type="password"]\');' +
                'pass.dispatchEvent(createNewEvent(\'input\')); pass.dispatchEvent(createNewEvent(\'change\'));'
            );

         //   provider.setNextStep('checkLoginErrors', function () {
                $('span:contains("Sign In")').click();
        //    });
            setTimeout(function () {
                plugin.checkLoginErrors();
            }, 10000);
        }, 3000);
    },
    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.el-form-item__error:visible');
        if (errors.length === 0) {
            errors = $(".el-message.el-message--error.is-closable:visible").find(".el-message__content:visible");
        }
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },

};