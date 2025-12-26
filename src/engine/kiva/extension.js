var plugin = {

    hosts: {
        'www.kiva.org': true,
        'login.kiva.org': true
    },

    getStartingUrl: function (params) {
        return 'https://www.kiva.org/login';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params.account);
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

    isLoggedIn: function (account) {
        browserAPI.log("isLoggedIn");
        if ($('#login-form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=logout]').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('div.lender-name').text();
        browserAPI.log("name: " + name);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm');
        document.location.href = 'http://www.kiva.org/logout';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login');
        document.location.href = plugin.getStartingUrl(params);
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#login-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
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
                '}'+
                'var email = document.querySelector(\'input[name="email"]\');' +
                'email.dispatchEvent(createNewEvent(\'input\')); email.dispatchEvent(createNewEvent(\'change\'));' +
                'var pass = document.querySelector(\'input[name="password"]\');' +
                'pass.dispatchEvent(createNewEvent(\'input\')); pass.dispatchEvent(createNewEvent(\'change\'));'
            );

            provider.setNextStep('checkLoginErrors');
            setTimeout(function () {
                $('#sign-in-button').click();
                // setTimeout(function () {
                //     plugin.checkLoginErrors(params);
                // }, 5000);
            }, 2000);
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $("li:contains(Login unsuccessful)");
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
};