import 'code-prettify';
import './styles/codeprettify/github.scss';

document.addEventListener('DOMContentLoaded', function(event) {
	if (PR) {
		if (typeof PR.prettyPrint === 'function') {
			PR.prettyPrint();
		}
	}
});
