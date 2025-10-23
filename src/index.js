import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';

registerBlockType('nsb/newsletter-signup', {
	edit: Edit,
	save() {
		// Dynamic block – markup is rendered server-side in PHP.
		return null;
	},
});