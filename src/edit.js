import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { headline, buttonLabel } = attributes;
	const blockProps = useBlockProps({ className: 'nsb-signup' });

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Signup Settings', 'nsb' ) }>
					<TextControl
						label={ __( 'Button label', 'nsb' ) }
						value={ buttonLabel }
						onChange={ (v) => setAttributes({ buttonLabel: v }) }
					/>
				</PanelBody>
			</InspectorControls>

			<RichText
				tagName="label"
				className="nsb-label"
				value={ headline }
				onChange={ (v) => setAttributes({ headline: v }) }
				placeholder={ __( 'Subscribe to our newsletter', 'nsb' ) }
			/>

			<input className="nsb-input" type="email" placeholder="you@example.com" disabled />
			<button className="nsb-button" disabled>{ buttonLabel || __( 'Subscribe', 'nsb' ) }</button>
		</div>
	);
}