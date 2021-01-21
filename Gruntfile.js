module.exports = function (grunt) {
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
	});

	grunt.registerTask('phpcs', [
		'exec:phpcs_plugin',
		'exec:phpcs_src'
	]);

	grunt.registerTask('default', ['phpcs']);
};
