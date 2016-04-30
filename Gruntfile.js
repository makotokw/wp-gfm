module.exports = function(grunt) {
	require('load-grunt-tasks')(grunt);
	grunt.initConfig({
		exec: {
			phpcs_plugin: {
				cmd: 'phpcs --standard=WordPress-Core markdown.php --report-width=200 -s'
			},
			phpcs_src: {
				cmd: 'phpcs --standard=PSR2 src --report-width=200 -s'
			}
		},
		compass: {
			options: {
				basePath: 'sass',
				httpPath: '/',
				cssDir: '../css',
				sassDir: '.',
				imagesDir: '../images',
				javascriptsDir: '../js',
				outputStyle: 'expanded'
			},
			prod: {
				options: {
					noLineComments: true,
					force: true
				}
			},
			dev: {
				options: {
					noLineComments: false,
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
