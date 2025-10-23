import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import './editor.scss';

registerBlockType('nsb/newsletter-signup', {
	edit: Edit,
	save() {
		// Dynamic block – markup is rendered in PHP.
		return null;
	},
});