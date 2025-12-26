var plugin = {

    hosts: {'www.hostelworld.com': true, 'hostelworld-2021-production.eu.auth0.com': true,},

    getStartingUrl: function (params) {
        return "https://www.hostelworld.com/";
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = 'https://hostelworld.com/pwa/login?iss=https://www.hostelworld.com/pwa/account';
        });
    },

    loadLoginAccount: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.hostelworld.com/pwa/account';
        });
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn === 0) {
                clearInterval(start);
                return;
            }
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout();
                } else
                    plugin.loadLoginForm(params);
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
        if ($('.profile-avatar .avatar-image-container').length > 0) {
            browserAPI.log("LoggedIn");
            plugin.loadLoginAccount(params)
            return 0;
        }
        if ($('figcaption .avatar-title:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('button#header-login:visible').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }

        // mobile
        if (provider.isMobile) {
            var menu = $('.icon-core-menu-fill.header-option:visible');
            if (menu.length > 0) {
                menu.get(0).click();
                if ($('li.logout-action:contains("Logout"):visible').length > 0) {
                    browserAPI.log("LoggedIn");
                    plugin.loadLoginAccount(params)
                    return 0;
                }
                if ($('li#menu-login:contains("Sign In"):visible').length > 0) {
                    browserAPI.log('not logged in');
                    return false;
                }
            }
        }
        return null;
    },

    isSameAccount: function (account) {
        var name = util.beautifulName($('figcaption .avatar-title:visible').text());
        browserAPI.log("name: " + name);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name == account.properties.Name));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            if (provider.isMobile) {
                var logout = $('button[aria-label="Logout"]:contains("Logout")');
                if (logout.length) {
                    logout.get(0).click();
                }
            } else {
                var menu = $('.profile-avatar, .pill-content.icon-only[aria-label="User Menu"]');
                if (menu.length) {
                    menu.get(0).click();
                    setTimeout(function () {
                        var logout = $('li button:contains("Logout")');
                        if (logout.length) {
                            logout.get(0).click();
                        }
                    }, 500)
                }
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var btn = $('#btn-classic');
        if (btn.length) {
            btn.get(0).click();
        }
        var form = $('.classic-login');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#email').val(params.account.login);
            form.find('input#password').val(params.account.password);
            util.sendEvent(form.find('input#email').get(0), 'input');
            util.sendEvent(form.find('input#password').get(0), 'input');

            provider.setNextStep('checkLoginErrors', function () {

                setTimeout(function() {
                    var captcha = $('.captcha-challenge img');
                    provider.captchaMessageDesktop();
                    //browserAPI.log("waiting captcha -> " + captcha.attr('src'));
                    if (captcha.length > 0) {
                        browserAPI.log("waiting...");

                        var captchaDiv = document.createElement('div');
                        captchaDiv.id = 'captchaDiv';
                        document.body.appendChild(captchaDiv);

                        var canvas = document.createElement('CANVAS'),
                            ctx = canvas.getContext('2d'),
                            img = document.querySelector('.captcha-challenge img');

                        canvas.height = img.height;
                        canvas.width = img.width;
                        ctx.drawImage(img, 0, 0);
                        var dataURL = canvas.toDataURL('image/png');
                        browserAPI.send("awardwallet", "recognizeCaptcha", { captcha: dataURL, "extension": "jpg" }, function(response){
                            console.log(JSON.stringify(response));
                            if (response.success === true) {
                                browserAPI.log("Success: " + response.success);
                                form.find('input[name="captcha"]').val(response.recognized);
                                form.find('button#btn-login').click();
                                setTimeout(function () {
                                    plugin.checkLoginErrors(params);
                                }, 5000);
                            }// if (response.success === true))
                            if (response.success === false) {
                                browserAPI.log("Success: " + response.success);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }// if (response.success === false)
                        });
                    }// if (captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        form.find('button#btn-login').click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 5000);
                    }
                }, 1000)
            });
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var error = $('#error-message:visible');
        if (error.length > 0)
            provider.setError(util.filter(error.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'http://www.hostelworld.com/myworld/bookings/';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var link = $('a:contains("' + confNo + '")');
        if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function () {
                link.get(0).click();
            });
        } else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};
