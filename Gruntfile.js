module.exports = function(grunt) {
	require('load-grunt-tasks')(grunt);
	grunt.initConfig({
		exec: {
			phpcs_plugin: {
				cmd: 'phpcs --standard=WordPress *.php'
			},
			phpcs_src: {
				cmd: 'phpcs --standard=PSR2 src'
			}
		},
		compass: {
			prod: {
				options: {
					basePath: 'sass',
					environment: 'production',
					force: true
				}
			},
			dev: {
				options: {
					basePath: 'sass',
					environment: 'development',
					force: true,
					trace: true
				}
			}
		},
		watch: {
			sass_dev: {
				files: ['sass/*.scss'],
				tasks: ['compass:dev']
			},
			livereload: {
				options: {
					livereload: true
				},
				files: ['css/*.css']
			}
		}
	});

	grunt.registerTask('phpcs', [
		'exec:phpcs_plugin',
		'exec:phpcs_src'
	]);

	grunt.registerTask('build', [
		'compass:prod'
	]);

	grunt.registerTask('debug', [
		'compass:dev',
		'watch'
	]);

	grunt.registerTask('default', ['build']);
};
