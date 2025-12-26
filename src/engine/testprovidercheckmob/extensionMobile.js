var plugin = {
    autologin: {
        url: 'about:blank',

        start: function(){
            this.finish();
        },

        finish: function(){
            api.complete();
        }
    }
};