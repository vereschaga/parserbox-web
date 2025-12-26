var plugin = {
	autologin: {
		url: "https://m.gha.com/user/login?mobile",

        start: function () {
			if(this.isLoggedIn())
                if(this.isSameAccount())
                    api.complete();
                else
                    this.logout();
            else
                this.login();
        },

        isLoggedIn: function () {
            if($('form[name = "loginform"]').length > 0){
                return false;
            }

            if($('input[name = "Login"]').length > 0){
                return false;
            }

            if($('.account-name').length > 0){
                return true;
            }

            if($('.log-out').length > 0){
                return true;
            }

            throw "can't determine login state";
        },

        login: function () {
            var form = $('form[name = "loginform"]');
            if(form.length == 1){
                form.find('input[name = "Login"]').val(params.login);
                form.find('input[name = "Password"]').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    form.submit();
                });
            } else {
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Name) !== 'undefined')
                && ($('.account-name').text().toLowerCase().indexOf(params.properties.Name.toLowerCase()) !== -1)
        },

        checkLoginErrors: function () {
            var error = $('.warning ul');
            if(error.length > 0){
                api.error(error.eq(0).text().trim());
            } else {
                this.finish();
            }
        },

        logout: function (){
            api.setNextStep('toLoginPage', function () {
                document.location.href = $('.log-out').attr('href');
            });
        },

        finish: function () {
            api.complete();
        }
	}
};