import hljs from 'highlight.js';
import 'highlight.js/styles/github.css';

document.addEventListener('DOMContentLoaded', (event) => {
	document.querySelectorAll('pre code').forEach((block) => {
		hljs.highlightBlock(block);
	});
});
