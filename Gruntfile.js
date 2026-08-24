module.exports = function(grunt) {
    'use strict';

    // This plugin's AMD sources (amd/src/*.js) are plain ES5 - no classes, arrow
    // functions or modules syntax - so unlike aiplacement_modgen's Gruntfile, there is
    // no Babel transpilation step here: uglify runs directly against each source file.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        uglify: {
            options: {
                sourceMap: true,
                sourceMapIncludeSources: true,
                banner: '/*! <%= pkg.name %> - v<%= pkg.version %> - ' +
                    '<%= grunt.template.today("yyyy-mm-dd") %>\n' +
                    ' * @license <%= pkg.license %> or later\n */\n',
                compress: {
                    sequences: true,
                    dead_code: true,
                    conditionals: true,
                    booleans: true,
                    unused: true,
                    if_return: true,
                    join_vars: true
                },
                mangle: true,
                output: {
                    comments: false
                }
            },
            dist: {
                files: [{
                    expand: true,
                    cwd: 'amd/src',
                    src: ['*.js'],
                    dest: 'amd/build',
                    ext: '.min.js'
                }]
            }
        },

        watch: {
            scripts: {
                files: ['amd/src/**/*.js'],
                tasks: ['uglify'],
                options: {
                    spawn: false,
                    livereload: true
                }
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');

    grunt.registerTask('default', ['uglify']);
    grunt.registerTask('build', ['uglify']);
    grunt.registerTask('dev', ['watch']);
};
