// src/edit.js
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

      {/* Mirror frontend structure, but keep inputs disabled in editor */}
      <form className="nsb-form" onSubmit={(e) => e.preventDefault()} noValidate>
        <div className="nsb-field">
          <RichText
            tagName="label"
            className="nsb-label"
            value={ headline }
            onChange={ (v) => setAttributes({ headline: v }) }
            placeholder={ __( 'Subscribe to our newsletter', 'nsb' ) }
          />
          <input className="nsb-input" type="email" placeholder="you@example.com" disabled />
        </div>

        <div className="nsb-actions">
          <button className="nsb-button" type="button" disabled>
            { buttonLabel || __( 'Subscribe', 'nsb' ) }
          </button>
        </div>

        <p className="nsb-msg" aria-live="polite"></p>

        {/* Honeypot remains present but hidden via CSS */}
        <input
          type="text"
          name="company"
          className="nsb-hp"
          tabIndex="-1"
          autoComplete="off"
          aria-hidden="true"
        />
      </form>
    </div>
  );
}