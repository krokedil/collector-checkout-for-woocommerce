module.exports = function(grunt) {
    grunt.loadNpmTasks('grunt-wp-i18n');
    grunt.initConfig({
        makepot: {
            target: {
                options: {
                    domainPath: '/languages',
                    mainFile: 'collector-checkout-for-woocommerce',
                    potFilename: 'collector-checkout-for-woocommerce.pot',
                    processPot(pot, options) {
                        // add header options
                        return pot;
                    },
                    type: 'wp-plugin',
                },
            },
        },
    });
};
