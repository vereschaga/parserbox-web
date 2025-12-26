
var plugin = {

    autologin: {
        url: "https://www.ryanair.com/gb/en/",
        clearCache: true,

        start: function () {            
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.autologin.isLoggedIn();
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.autologin.isSameAccount()) {
                            provider.complete();
                        } else {
                            plugin.autologin.logout();
                        }
                    } else {
                        plugin.autologin.login();
                    }
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
            if ($('button[aria-label = "My bookings & check-in"]').length > 0) {
                browserAPI.log("not logged in");
                return false;
            }
            if ($('a[aria-label = "See my profile"]').length > 0) {
                browserAPI.log("logged in");
                return true;
            }
            return null;
        },

        isSameAccount: function () {
            browserAPI.log('isSameAccount');
            return false;
        },

        login: function () {
            browserAPI.log('login');
            if ($('button:contains("Log in")').length === 0) {
                $('button[aria-label = "Open menu"]').click();
            }
            util.waitFor({
                selector: 'button:contains("Log in")',
                success: function(button) {
                    button.click();
                    plugin.autologin.login2();
                },
                fail: function () {
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
            });
        },

        login2: function () {
            browserAPI.log('login2');
            util.waitFor({
                selector: 'form.content__form',
                success: function (form) {
                    input1 = form.find('input[name = "email"]');
                    input2 = form.find('input[name = "password"]');
                    input1.val(params.account.login);
                    input2.val(params.account.password);
                    util.sendEvent(input1.get(0), 'input');
                    util.sendEvent(input2.get(0), 'input');
                    form.find('button[type = "submit"]').click();
                    setTimeout(function () {
                        plugin.autologin.checkLoginErrors();
                    }, 2000);
                },
                fail: function () {
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
            });
        },

        logout: function () {
            browserAPI.log('logout');
            $('button[aria-label = "Open menu"]').click();
            util.waitFor({
                selector: 'button[aria-label = "Log out"]',
                success: function (link) {
                    link.get(0).click();
                    setTimeout(function () {
                        plugin.autologin.start();
                    }, 3000);
                },
                fail: function () {
                    browserAPI.log('failed to log out');
                }
            });
        },

        checkLoginErrors: function () {
            browserAPI.log('checkLoginErrors');
            var errors = $('span._label--error');
            if (errors.length && '' != errors.text().trim()) {
                provider.setError(util.filter(errors.text()));
            } else {
                plugin.autologin.loginComplete();
            }
        },

        loginComplete: function () {
            browserAPI.log('loginComplete');
            provider.complete();
        }
    }
};
