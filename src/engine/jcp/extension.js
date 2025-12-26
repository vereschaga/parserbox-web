var plugin = {

    hosts: {'www.jcpenney.com': true, 'm.jcpenney.com': true},

    getStartingUrl: function (params) {
        return 'https://www.jcpenney.com/account/dashboard/personal/info';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        // var manage = $('button:contains("Sign in to manage your account")');
        // if (manage.length) {
        //     manage.get(0).click();
        //     plugin.start(params);
        // }
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params.account);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loadDashboard();
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
        }, 1000);
    },

    isLoggedIn: function (account) {
        browserAPI.log("isLoggedIn");
        if ($('.mainContainer form:visible, button:contains("Sign in To your Account"):visible, div[data-automation-id="sign_in_slider_login_container"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#newEmail:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const email = $('#newEmail').val();
        browserAPI.log("email: " + email);
        return typeof(account.login) !== 'undefined' && email.toLowerCase() === account.login.toLowerCase();
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            const logout = $('button:contains("Sign Out")');
            if (logout.length) {
                logout.get(0).click();
                setTimeout(function () {
                    plugin.loadLoginForm(params);
                }, 5000);
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        setTimeout(function () {
            let form = $('.signinWrapper form:visible');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                // reactjs
                provider.eval(`
                    function triggerInput(selector, enteredValue) {
                          let input = document.querySelector(selector);
                          input.dispatchEvent(new Event('focus', { bubbles: true }));
                          input.dispatchEvent(new Event('click', { bubbles: true }));
                          input.dispatchEvent(new KeyboardEvent('keydown',{'key':'a'}));
                          input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));
                          let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                          nativeInputValueSetter.call(input, enteredValue);
                          input.dispatchEvent(new Event('input', { bubbles: true }));
                          input.dispatchEvent(new Event('change', { bubbles: true }));
                          input.dispatchEvent(new Event('blur', { bubbles: true }));
                    }
                    triggerInput('#loginEmail', '${params.account.login}');
                    triggerInput('#signin-password', '${params.account.password}');
                `);
                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(() => {
                        let btn = form.find('button[type=submit]').get(0);
                        btn.dispatchEvent(new Event('mousedown', {bubbles: true})); // do NOT use click method, only mousedown
                    }, 1000);
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 8000)
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 2000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('p[data-automation-id="signin_error-title"]:visible');

        if (errors.length > 0) {
            provider.setError(util.filter(errors.last().text()));
            return;
        }

        plugin.loadDashboard();
    },

    loadDashboard: function() {
        provider.setNextStep('loginComplete', function () {
            document.location.href = 'https://www.jcpenney.com/rewards/rewards/dashboard';
        });
    },

    loginComplete: function () {
        provider.complete();
    }
};